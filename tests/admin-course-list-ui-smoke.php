<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-list.php' );
$plugin_source   = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$style_source    = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );

if ( false === $template_source || false === $plugin_source || false === $style_source ) {
	throw new RuntimeException( 'AG-Liste, Plugin-Controller oder Stylesheet konnte nicht gelesen werden.' );
}

$checks = array(
	array(
		str_contains( $template_source, "'type' => 'select'" )
		&& str_contains( $template_source, "'name' => 'school_year'" )
		&& str_contains( $template_source, "'options' => \$school_year_options" ),
		'Der Schuljahrfilter ist kein Dropdown mit den vorhandenen Schuljahren.',
	),
	array(
		str_contains( $template_source, '<details class="flz-ags-csv-details"' )
		&& str_contains( $template_source, '<summary' )
		&& str_contains( $template_source, 'AGs und Termine als CSV' ),
		'Der CSV-Bereich ist keine native, einklappbare Leiste.',
	),
	array(
		str_contains( $template_source, 'flz-ags-admin-toolbar' )
		&& str_contains( $style_source, '.flz-ags-admin-toolbar' ),
		'Die Listenaktionen und Filter bilden keinen kompakten Admin-Kopf.',
	),
	array(
		str_contains( $template_source, '$ui->editable_row(' )
		&& str_contains( $template_source, "'flz_ags_quick_edit_course'" )
		&& str_contains( $template_source, "'nonce'  => 'flz_ags_quick_edit_course_'" ),
		'Die Tabellenzeilen verwenden keinen geschützten Quick-Edit-Vertrag.',
	),
	array(
		str_contains( $plugin_source, "admin_post_flz_ags_quick_edit_course" )
		&& str_contains( $plugin_source, 'public function handle_quick_edit_course(): void' ),
		'Der Quick-Edit-Endpunkt ist nicht registriert.',
	),
	array(
		str_contains( $style_source, '.flz-ags-course-entry' )
		&& str_contains( $style_source, 'flex-direction: column;' )
		&& str_contains( $style_source, 'height: 100%;' )
		&& str_contains( $style_source, '.flz-ags-course-entry > .flz-ags-card' )
		&& str_contains( $style_source, 'flex: 1 1 auto;' ),
		'Die AG-Einträge strecken ihre Karten nicht unabhängig von der Kurztextlänge auf die Zeilenhöhe.',
	),
);

foreach ( $checks as list( $passed, $message ) ) {
	if ( ! $passed ) {
		throw new RuntimeException( $message );
	}
}

echo "OK: flz_ags admin course list UI smoke test\n";
