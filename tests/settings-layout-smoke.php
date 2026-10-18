<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

$root     = dirname( __DIR__ );
$settings = file_get_contents( $root . '/templates/admin-settings.php' );
$css      = file_get_contents( $root . '/assets/css/flz-ags.css' );

if ( ! is_string( $settings ) || ! is_string( $css ) ) {
	throw new RuntimeException( 'Die Quellen für die Einstellungen konnten nicht gelesen werden.' );
}

foreach ( array(
	'flz-ags-settings-card--organisation',
	'flz-ags-settings-card--registration',
	'flz-ags-settings-card--classes',
	'flz-ags-settings-card--privacy',
	'current_school_year',
	'period_mode',
	'parent_page_id',
	'multiple_registrations_enabled',
	'registration_grade_7_exclusive_start',
	'registration_form_notice',
	'classes_text',
	'registration_retention_enabled',
) as $needle ) {
	if ( ! str_contains( $settings, $needle ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-Testfehler wird nicht gerendert.
		throw new RuntimeException( 'Die zugeordnete Einstellung fehlt in der kompakten Struktur: ' . $needle );
	}
}

if ( ! str_contains( $css, '@media (min-width: 960px)' ) || ! str_contains( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) ) {
	throw new RuntimeException( 'Die Einstellungsbereiche nutzen auf breiten Bildschirmen keine zwei Spalten.' );
}
if ( ! str_contains( $css, 'grid-template-columns: minmax(0, 1fr)' ) ) {
	throw new RuntimeException( 'Die mobile einspaltige Darstellung der Einstellungen ist nicht abgesichert.' );
}

$privacy_start = strpos( $settings, 'flz-ags-settings-card--privacy' );
$privacy_end   = false === $privacy_start ? false : strpos( $settings, '</section>', $privacy_start );
$delete_action = strpos( $settings, 'Alte Anmeldungen jetzt löschen' );
if ( false === $privacy_start || false === $privacy_end || false === $delete_action || $delete_action < $privacy_start || $delete_action > $privacy_end ) {
	throw new RuntimeException( 'Die manuelle Löschung alter Anmeldungen gehört in den Datenschutzbereich.' );
}
if ( ! str_contains( $settings, "'form' => 'flz-ags-delete-old-registrations'" ) || ! str_contains( $settings, "'id' => 'flz-ags-delete-old-registrations'" ) ) {
	throw new RuntimeException( 'Die Datenschutz-Löschung ist nicht ohne verschachtelte Formulare dem geschützten Aktionsformular zugeordnet.' );
}

echo "OK: flz_ags compact settings layout smoke test\n";
