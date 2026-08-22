<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_text_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_title( $value ): string {
	$value = strtolower( sanitize_text_field( $value ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
}

function wp_kses_post( $value ): string {
	return (string) $value;
}

function esc_url_raw( $value ): string {
	return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : '';
}

function absint( $value ): int {
	return abs( (int) $value );
}

function flz_ags_sanitize_allowed_grades( $value ): string {
	$grades = array_filter(
		array_map( 'trim', explode( ',', (string) $value ) ),
		static fn( string $grade ): bool => in_array( $grade, array( '7', '8', '9', '10', '11', '12' ), true )
	);
	return implode( ',', $grades );
}

$service_file = dirname( __DIR__ ) . '/includes/class-flz-ags-course-csv.php';
if ( ! is_file( $service_file ) ) {
	throw new RuntimeException( 'Der versionierte AG-/Slot-CSV-Service fehlt.' );
}
require_once $service_file;

function flz_ags_csv_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$course = (object) array(
	'id'                => 17,
	'school_year'       => '2026/2027',
	'title'             => 'Schach AG',
	'slug'              => 'schach-ag',
	'short_description' => 'Strategisch spielen.',
	'description'       => '<p>Für Einsteiger*innen.</p>',
	'image_url'         => 'https://example.test/schach.jpg',
	'detail_page_id'    => 23,
	'category'          => 'Spiele',
	'leader_name'       => 'Max Muster',
	'allowed_grades'    => '7,8',
	'only_grade_7'      => 0,
	'is_active'         => 1,
	'is_visible'        => 1,
	'registration_open' => 1,
	'sort_order'        => 4,
);
$slot = (object) array(
	'weekday'         => 2,
	'start_time'      => '14:00:00',
	'end_time'        => '15:30:00',
	'room'            => 'B 201',
	'max_participants'=> 16,
	'is_active'       => 1,
	'sort_order'      => 0,
);

$rows = FLZ_AGS_Course_CSV::export_rows(
	array( $course ),
	static fn( int $course_id ): array => 17 === $course_id ? array( $slot ) : array(),
	static fn( int $page_id ): string => 23 === $page_id ? 'ags/schach-ag' : ''
);
$parsed = FLZ_AGS_Course_CSV::parse( array_merge( array( FLZ_AGS_Course_CSV::header() ), $rows ) );

flz_ags_csv_assert( count( $rows ) === 2, 'Der Export enthält nicht genau einen AG- und einen Slot-Datensatz.' );
flz_ags_csv_assert( isset( $parsed['2026/2027|schach-ag'] ), 'Der Roundtrip erhält den portablen AG-Schlüssel nicht.' );
flz_ags_csv_assert( count( $parsed['2026/2027|schach-ag']['slots'] ) === 1, 'Der Roundtrip erhält den AG-Slot nicht.' );
flz_ags_csv_assert( $parsed['2026/2027|schach-ag']['course']['title'] === 'Schach AG', 'Der Roundtrip verändert den AG-Titel.' );
flz_ags_csv_assert( $parsed['2026/2027|schach-ag']['course']['detail_page_path'] === 'ags/schach-ag', 'Der Roundtrip erhält den portablen Detailseitenpfad nicht.' );
flz_ags_csv_assert( ! in_array( 'detail_page_id', FLZ_AGS_Course_CSV::header(), true ), 'Der CSV-Vertrag exportiert weiterhin installationsabhängige Seiten-IDs.' );

$course_only_rows = FLZ_AGS_Course_CSV::export_rows(
	array( $course ),
	static fn( int $course_id ): array => array(),
	static fn( int $page_id ): string => 'ags/schach-ag'
);
$course_only = FLZ_AGS_Course_CSV::parse_tolerant(array_merge(array(FLZ_AGS_Course_CSV::header()), $course_only_rows));
flz_ags_csv_assert( count( $course_only_rows ) === 1, 'Der Export ohne Termine enthält weiterhin Slot-Zeilen.' );
flz_ags_csv_assert( count( $course_only['courses']['2026/2027|schach-ag']['slots'] ) === 0, 'Der Import erkennt eine AG-CSV ohne Slots nicht automatisch.' );

$remapped = FLZ_AGS_Course_CSV::remap_school_year($tolerant = array(
	'courses' => $parsed,
	'warnings' => array(),
), '2027/2028');
flz_ags_csv_assert( isset( $remapped['courses']['2027/2028|schach-ag'] ), 'Der Import kann AGs nicht in ein ausgewähltes Schuljahr übertragen.' );
flz_ags_csv_assert( $remapped['courses']['2027/2028|schach-ag']['course']['school_year'] === '2027/2028', 'Das Zielschuljahr wird nicht an der AG gespeichert.' );
flz_ags_csv_assert( $remapped['courses']['2027/2028|schach-ag']['slots'][0]['school_year'] === '2027/2028', 'Das Zielschuljahr wird nicht an den Slots gespeichert.' );
try {
	FLZ_AGS_Course_CSV::remap_school_year($tolerant, '2027/2029');
	throw new RuntimeException( 'Ein nicht aufeinanderfolgendes Zielschuljahr wurde akzeptiert.' );
} catch ( UnexpectedValueException $error ) {
	flz_ags_csv_assert( str_contains( $error->getMessage(), 'Zielschuljahr' ), 'Ein ungültiges Zielschuljahr liefert keine verständliche Meldung.' );
}
$collision_item = $parsed['2026/2027|schach-ag'];
$collision_item['course']['school_year'] = '2025/2026';
$collision_item['course']['title'] = 'Schach Alt';
$collision_input = array(
	'courses' => $parsed + array('2025/2026|schach-ag' => $collision_item),
	'warnings' => array(),
);
$collision_result = FLZ_AGS_Course_CSV::remap_school_year($collision_input, '2027/2028');
flz_ags_csv_assert( count( $collision_result['courses'] ) === 1, 'Kollidierende Slugs aus mehreren Quellschuljahren werden nicht einzeln übersprungen.' );
flz_ags_csv_assert( str_contains( implode( "\n", $collision_result['warnings'] ), 'doppelt' ), 'Eine Schuljahrkollision wird nicht verständlich gemeldet.' );
flz_ags_csv_assert( $collision_result['courses']['2027/2028|schach-ag']['course']['school_year'] === '2027/2028', 'Bei einer Schuljahrkollision bleibt das Zielschuljahr nicht konsistent.' );
flz_ags_csv_assert( $collision_result['courses']['2027/2028|schach-ag']['course']['title'] === 'Schach AG', 'Bei einer Schuljahrkollision gewinnt nicht das neueste Quellschuljahr.' );

$invalid_version = array_merge( array( FLZ_AGS_Course_CSV::header() ), $rows );
$invalid_version[1][0] = 'flz_ags_courses_v999';
try {
	FLZ_AGS_Course_CSV::parse( $invalid_version );
	throw new RuntimeException( 'Eine unbekannte CSV-Version wurde akzeptiert.' );
} catch ( UnexpectedValueException $error ) {
	flz_ags_csv_assert( str_contains( $error->getMessage(), 'Version' ), 'Die falsche Version liefert keinen verständlichen Fehler.' );
}

$orphan_slot = array( FLZ_AGS_Course_CSV::header(), $rows[1] );
try {
	FLZ_AGS_Course_CSV::parse( $orphan_slot );
	throw new RuntimeException( 'Ein Slot ohne zugehörige AG wurde akzeptiert.' );
} catch ( UnexpectedValueException $error ) {
	flz_ags_csv_assert( str_contains( $error->getMessage(), 'AG-Datensatz' ), 'Der verwaiste Slot liefert keinen verständlichen Fehler.' );
}

$duplicate_course = array_merge( array( FLZ_AGS_Course_CSV::header() ), $rows, array( $rows[0] ) );
try {
	FLZ_AGS_Course_CSV::parse( $duplicate_course );
	throw new RuntimeException( 'Ein doppelter AG-Schlüssel wurde akzeptiert.' );
} catch ( UnexpectedValueException $error ) {
	flz_ags_csv_assert( str_contains( $error->getMessage(), 'mehrfach' ), 'Der doppelte AG-Schlüssel liefert keinen verständlichen Fehler.' );
}

$repairable_course = $rows[0];
$repairable_course[4] = '';
$repairable_course[8] = 'keine-url';
$broken_slot = $rows[1];
$broken_slot[19] = '99:00';
$tolerant = FLZ_AGS_Course_CSV::parse_tolerant(array(
	FLZ_AGS_Course_CSV::header(),
	$repairable_course,
	$broken_slot,
	$rows[1],
	$rows[1],
));
flz_ags_csv_assert( isset( $tolerant['courses']['2026/2027|schach-ag'] ), 'Der fehlertolerante Import verwirft eine reparierbare AG.' );
flz_ags_csv_assert( str_contains( $tolerant['courses']['2026/2027|schach-ag']['course']['title'], 'Ohne Titel' ), 'Ein fehlender Titel erhält keinen verständlichen Platzhalter.' );
flz_ags_csv_assert( $tolerant['courses']['2026/2027|schach-ag']['course']['image_url'] === '', 'Eine ungültige optionale Bild-URL wird nicht sicher geleert.' );
flz_ags_csv_assert( count( $tolerant['courses']['2026/2027|schach-ag']['slots'] ) === 1, 'Fehlerhafte oder doppelte Slots werden nicht einzeln übersprungen.' );
flz_ags_csv_assert( count( $tolerant['warnings'] ) >= 4, 'Der fehlertolerante Import meldet Korrekturen und ausgelassene Zeilen nicht vollständig.' );
flz_ags_csv_assert( str_contains( implode( "\n", $tolerant['warnings'] ), 'Zeile 2' ), 'Importhinweise enthalten keine nachvollziehbaren Zeilennummern.' );

$missing_identity = $rows[0];
$missing_identity[2] = '';
$missing_identity[4] = 'Robotik AG';
$missing_identity[5] = '';
$missing_identity[12] = '7,unbekannt';
$reconstructed = FLZ_AGS_Course_CSV::parse_tolerant(array(FLZ_AGS_Course_CSV::header(), $missing_identity));
flz_ags_csv_assert( isset( $reconstructed['courses']['2026/2027|robotik-ag'] ), 'Ein fehlender technischer AG-Schlüssel wird nicht aus Schuljahr und Titel rekonstruiert.' );
flz_ags_csv_assert( $reconstructed['courses']['2026/2027|robotik-ag']['course']['allowed_grades'] === '7', 'Ungültige Zielgruppenanteile werden nicht einzeln bereinigt.' );
flz_ags_csv_assert( count( $reconstructed['warnings'] ) >= 3, 'Rekonstruierte Identität und bereinigte Zielgruppe werden nicht vollständig gemeldet.' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-list.php' );
if ( false === $plugin_source || false === $template_source ) {
	throw new RuntimeException( 'Controller oder AG-Listen-Template konnte nicht gelesen werden.' );
}

flz_ags_csv_assert( str_contains( $plugin_source, "add_action('admin_post_flz_ags_export_courses_csv'" ), 'Der geschützte AG-CSV-Export-Handler ist nicht registriert.' );
flz_ags_csv_assert( str_contains( $plugin_source, "add_action('admin_post_flz_ags_import_courses_csv'" ), 'Der geschützte AG-CSV-Import-Handler ist nicht registriert.' );
$export_start = strpos( $plugin_source, 'public function handle_export_courses_csv(): void' );
$export_end = strpos( $plugin_source, 'public function handle_import_courses_csv(): void', $export_start ?: 0 );
flz_ags_csv_assert( false !== $export_start && false !== $export_end, 'Der AG-CSV-Export-Handler konnte nicht isoliert geprüft werden.' );
$export_source = substr( $plugin_source, $export_start, $export_end - $export_start );
flz_ags_csv_assert( str_contains( $export_source, "isset(\$_GET['include_slots'])" ), 'Der AG-CSV-Export wertet die Slot-Checkbox nicht aus.' );
$import_start = strpos( $plugin_source, 'public function handle_import_courses_csv(): void' );
$import_end = strpos( $plugin_source, 'public function handle_export_csv(): void', $import_start ?: 0 );
flz_ags_csv_assert( false !== $import_start && false !== $import_end, 'Der AG-CSV-Import-Handler konnte nicht isoliert geprüft werden.' );
$import_source = substr( $plugin_source, $import_start, $import_end - $import_start );
flz_ags_csv_assert( str_contains( $import_source, '$this->assert_admin_permission();' ), 'Der AG-CSV-Import prüft keine Berechtigung.' );
flz_ags_csv_assert( str_contains( $import_source, "check_admin_referer('flz_ags_import_courses_csv')" ), 'Der AG-CSV-Import prüft keinen Nonce.' );
flz_ags_csv_assert( ! str_contains( $import_source, 'submit_csv_dry_run' ), 'Der AG-CSV-Import enthält weiterhin einen irritierenden Dry-Run.' );
flz_ags_csv_assert( ! str_contains( $import_source, 'hash_equals(' ), 'Der direkte AG-CSV-Import verlangt weiterhin einen Dry-Run-Nachweis.' );
flz_ags_csv_assert( str_contains( $import_source, 'FLZ_AGS_Course_CSV::parse_tolerant(' ), 'Der AG-CSV-Import verwendet nicht die fehlertolerante Aufbereitung.' );
flz_ags_csv_assert( str_contains( $import_source, "\$school_year_mode = isset(\$_POST['school_year_mode'])" ), 'Der Import wertet die Wahl des Schuljahrmodus nicht aus.' );
flz_ags_csv_assert( str_contains( $import_source, 'FLZ_AGS_Course_CSV::remap_school_year(' ), 'Der Import überträgt CSV-Daten nicht in das ausgewählte Zielschuljahr.' );
flz_ags_csv_assert( str_contains( $import_source, "'school_year' => 'replace' === \$school_year_mode ? \$target_school_year : \$school_year" ), 'Nach einer Schuljahrübertragung zeigt die AG-Liste nicht das Zielschuljahr.' );
flz_ags_csv_assert( str_contains( $import_source, '$this->assert_course_csv_upload();' ), 'Fehlende oder abgebrochene CSV-Uploads erhalten keine gezielte Prüfung.' );
flz_ags_csv_assert( str_contains( $import_source, 'set_transient($this->course_csv_report_key(), $report,' ), 'Der Import speichert keinen datensparsamen Ergebnisbericht.' );
flz_ags_csv_assert( ! str_contains( $import_source, 'set_transient($this->course_csv_report_key(), $plan,' ), 'Der Import speichert unnötig den vollständigen Importplan.' );
flz_ags_csv_assert( str_contains( $import_source, 'FLZ_AGS_Model::transaction(' ), 'Der AG-CSV-Import ist nicht atomar abgesichert.' );
flz_ags_csv_assert( str_contains( $template_source, 'AG-CSV hochladen' ), 'Die AG-Liste bietet keinen verständlichen direkten Upload an.' );
flz_ags_csv_assert( ! str_contains( $template_source, 'Dry-Run' ), 'Die AG-Liste erwähnt weiterhin einen Dry-Run.' );
flz_ags_csv_assert( str_contains( $template_source, "\$ui->input('file'" ) && str_contains( $template_source, "'name'   => 'course-csv'" ), 'Die AG-Liste zeigt keinen eindeutigen CSV-Dateiupload an.' );
flz_ags_csv_assert( substr_count( $template_source, "array('name' => 'submit_csv')" ) === 1, 'Die AG-Liste zeigt mehr als eine CSV-Upload-Aktion an.' );
flz_ags_csv_assert( str_contains( $template_source, 'flz-ags-csv-report' ), 'Die AG-Liste zeigt keinen eigenen verständlichen Importbericht an.' );
flz_ags_csv_assert( str_contains( $template_source, 'Hinweise zum letzten CSV-Import' ), 'Der Importbericht besitzt keine verständliche Überschrift.' );
flz_ags_csv_assert( str_contains( $template_source, "'name' => 'include_slots'" ), 'Das Exportformular bietet keine Checkbox für Termine/Slots.' );
flz_ags_csv_assert( str_contains( $template_source, 'Termine/Slots mit exportieren' ), 'Die Export-Checkbox ist nicht verständlich beschriftet.' );
flz_ags_csv_assert( str_contains( $template_source, "'name' => 'school_year_mode'" ), 'Das Importformular bietet keine Wahl für den Umgang mit dem Schuljahr.' );
flz_ags_csv_assert( str_contains( $template_source, 'Schuljahre aus CSV behalten' ), 'Der Beibehalten-Modus ist nicht verständlich beschriftet.' );
flz_ags_csv_assert( str_contains( $template_source, 'Alle AGs in folgendes Schuljahr importieren' ), 'Der Zielschuljahr-Modus ist nicht verständlich beschriftet.' );

echo "OK: flz_ags course CSV roundtrip smoke test\n";
