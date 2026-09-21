<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$service_file = dirname( __DIR__ ) . '/includes/class-flz-ags-registration-csv.php';
if ( ! is_file( $service_file ) ) {
	throw new RuntimeException( 'Der versionierte Anmeldungs-CSV-Service fehlt.' );
}
require_once $service_file;

function flz_ags_registration_csv_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$registration = (object) array(
	'school_year'       => '2026/2027',
	'status'            => 'withdrawn',
	'class_name'        => '8.2',
	'grade_key'         => '8',
	'student_last_name' => 'Muster',
	'student_first_name'=> 'Mia',
	'student_email'     => 'mia@example.test',
	'consent_privacy'   => 1,
	'slug'              => 'schach-ag',
	'title'             => 'Schach AG',
	'weekday'           => 2,
	'start_time'        => '14:00:00',
	'end_time'          => '15:30:00',
	'room'              => 'B 201',
	'withdrawn_at'      => '2026-09-20 10:00:00',
	'withdrawn_reason'  => 'Wechsel',
	'created_at'        => '2026-09-01 08:15:00',
	'updated_at'        => '2026-09-20 10:00:00',
);

$rows = FLZ_AGS_Registration_CSV::export_rows( array( $registration ) );
$parsed = FLZ_AGS_Registration_CSV::parse_tolerant(
	array_merge( array( FLZ_AGS_Registration_CSV::header() ), $rows )
);

flz_ags_registration_csv_assert( count( $parsed['registrations'] ) === 1, 'Der Anmeldungs-CSV-Roundtrip verliert den Datensatz.' );
$restored = $parsed['registrations'][0];
flz_ags_registration_csv_assert( $restored['course_slug'] === 'schach-ag', 'Der portable AG-Schlüssel geht verloren.' );
flz_ags_registration_csv_assert( $restored['status'] === 'withdrawn', 'Der fachliche Status geht verloren.' );
flz_ags_registration_csv_assert( $restored['consent_privacy'] === 1, 'Die dokumentierte Einwilligung geht verloren.' );
flz_ags_registration_csv_assert( $restored['withdrawn_reason'] === 'Wechsel', 'Widerrufsdaten gehen verloren.' );
flz_ags_registration_csv_assert( ! in_array( 'course_id', FLZ_AGS_Registration_CSV::header(), true ), 'Der Backupvertrag enthält installationsabhängige AG-IDs.' );
flz_ags_registration_csv_assert( ! in_array( 'slot_id', FLZ_AGS_Registration_CSV::header(), true ), 'Der Backupvertrag enthält installationsabhängige Slot-IDs.' );

$repairable = $rows[0];
$repairable[2] = 'unbekannt';
$repairable[3] = '';
$repairable[5] = '';
$repairable[6] = '';
$repairable[7] = 'keine-mail';
$repairable[8] = 'vielleicht';
$repaired = FLZ_AGS_Registration_CSV::parse_tolerant( array( FLZ_AGS_Registration_CSV::header(), $repairable ) );
flz_ags_registration_csv_assert( count( $repaired['registrations'] ) === 1, 'Ein reparierbarer Anmeldedatensatz wird vollständig verworfen.' );
flz_ags_registration_csv_assert( $repaired['registrations'][0]['status'] === 'cancelled', 'Ein unbekannter Status erhält keinen sicheren Platzhalter.' );
flz_ags_registration_csv_assert( $repaired['registrations'][0]['class_name'] === 'Unbekannt', 'Eine fehlende Klasse erhält keinen Platzhalter.' );
flz_ags_registration_csv_assert( $repaired['registrations'][0]['student_first_name'] === 'Ohne Vorname', 'Ein fehlender Vorname erhält keinen Platzhalter.' );
flz_ags_registration_csv_assert( $repaired['registrations'][0]['student_email'] === '', 'Eine ungültige optionale E-Mail wird nicht sicher geleert.' );
flz_ags_registration_csv_assert( count( $repaired['warnings'] ) >= 5, 'Automatische Reparaturen werden nicht vollständig gemeldet.' );
flz_ags_registration_csv_assert( str_contains( implode( "\n", $repaired['warnings'] ), 'Zeile 2' ), 'Importhinweise enthalten keine Zeilennummer.' );

$broken = $rows[0];
$broken[1] = '';
$broken[9] = '';
$broken[12] = '99:00';
$skipped = FLZ_AGS_Registration_CSV::parse_tolerant( array( FLZ_AGS_Registration_CSV::header(), $broken ) );
flz_ags_registration_csv_assert( count( $skipped['registrations'] ) === 0, 'Ein Datensatz ohne Schuljahr/AG/Slot wird importiert.' );
flz_ags_registration_csv_assert( str_contains( implode( "\n", $skipped['warnings'] ), 'übersprungen' ), 'Ein ausgelassener Datensatz wird nicht verständlich gemeldet.' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-registrations.php' );
if ( false === $plugin_source || false === $template_source ) {
	throw new RuntimeException( 'Controller oder Anmeldungs-Template konnte nicht gelesen werden.' );
}
flz_ags_registration_csv_assert( str_contains( $plugin_source, "add_action('admin_post_flz_ags_import_registrations_csv'" ), 'Der geschützte Upload-Handler ist nicht registriert.' );
flz_ags_registration_csv_assert( str_contains( $plugin_source, "FLZ_AGS_Registration::find_for_admin(\$school_year, 'all', true)" ), 'Das Backup enthält nicht alle Status des gewählten Schuljahrs.' );
$handler_start = strpos( $plugin_source, 'public function handle_import_registrations_csv(): void' );
$handler_end = strpos( $plugin_source, 'public function handle_export_csv(): void', $handler_start ?: 0 );
flz_ags_registration_csv_assert( false !== $handler_start && false !== $handler_end, 'Der Anmeldungs-Importhandler konnte nicht isoliert werden.' );
$handler = substr( $plugin_source, $handler_start, $handler_end - $handler_start );
flz_ags_registration_csv_assert( str_contains( $handler, '$this->assert_admin_permission();' ), 'Der Anmeldungs-Import prüft keine Berechtigung.' );
flz_ags_registration_csv_assert( str_contains( $handler, "check_admin_referer('flz_ags_import_registrations_csv')" ), 'Der Anmeldungs-Import prüft keinen Nonce.' );
flz_ags_registration_csv_assert( str_contains( $handler, "isset(\$_POST['confirm_registration_import'])" ), 'Der direkte Import verlangt keine ausdrückliche Bestätigung.' );
flz_ags_registration_csv_assert( ! str_contains( $handler, 'wp_mail(' ), 'Ein Backup-Import versendet unerwartet Bestätigungsmails.' );
flz_ags_registration_csv_assert( str_contains( $handler, 'FLZ_AGS_Model::transaction(' ), 'Der Anmeldungs-Import ist nicht atomar abgesichert.' );
flz_ags_registration_csv_assert( str_contains( $template_source, 'Anmeldungs-CSV hochladen' ), 'Die Anmeldungsseite bietet keinen Upload.' );
flz_ags_registration_csv_assert( ! str_contains( $template_source, 'Dry-Run' ), 'Der Upload irritiert weiterhin mit einem Dry-Run.' );
flz_ags_registration_csv_assert( str_contains( $template_source, 'Hinweise zum letzten Anmeldungs-Import' ), 'Der Importbericht besitzt keine klare Überschrift.' );

echo "OK: flz_ags registration CSV roundtrip smoke test\n";
