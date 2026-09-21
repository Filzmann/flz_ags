<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-registrations.php' );
$plugin_source   = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$script_source   = file_get_contents( dirname( __DIR__ ) . '/assets/js/flz-ags-admin.js' );
$style_source    = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );

if ( false === $template_source || false === $plugin_source || false === $script_source || false === $style_source ) {
	throw new RuntimeException( 'Anmeldungstabelle, Controller oder Admin-Script konnte nicht gelesen werden.' );
}

$filter_columns = array( 'student', 'class', 'course', 'slot', 'email', 'status', 'date', 'action' );
foreach ( $filter_columns as $column ) {
	if (
		! str_contains( $template_source, "'data-flz-ags-registration-filter' => '$column'" )
		|| ! str_contains( $template_source, 'data-filter-' . $column )
	) {
		throw new RuntimeException( 'Filtervertrag fehlt im Anmeldungstabellenkopf: ' . $column );
	}
}

$sortable_columns = array( 'student', 'class', 'course', 'slot', 'email', 'status', 'date' );
foreach ( $sortable_columns as $column ) {
	if (
		! str_contains( $template_source, 'data-flz-ags-registration-sort="' . $column . '"' )
		|| ! str_contains( $template_source, 'data-sort-' . $column )
	) {
		throw new RuntimeException( 'Sortiervertrag fehlt im Anmeldungstabellenkopf: ' . $column );
	}
}

if (
	str_contains( $template_source, 'data-flz-ags-registration-sort="action"' )
	|| str_contains( $template_source, 'data-sort-action' )
) {
	throw new RuntimeException( 'Die reine Aktionsspalte bietet weiterhin eine fachlich nicht sinnvolle Sortierung an.' );
}

if (
	! str_contains( $template_source, 'data-flz-ags-registration-list' )
	|| ! str_contains( $template_source, 'data-flz-ags-registration-items' )
	|| ! str_contains( $template_source, 'data-flz-ags-registration-item' )
	|| ! str_contains( $template_source, 'data-flz-ags-registration-results-status' )
	|| ! str_contains( $template_source, 'data-flz-ags-registration-no-results' )
) {
	throw new RuntimeException( 'Die Anmeldungstabelle besitzt keinen vollständigen interaktiven Listenvertrag.' );
}

if (
	! str_contains( $plugin_source, "FLZ_AGS_Registration::find_for_admin(\$school_year, 'all')" )
	|| str_contains( $template_source, "'name' => 'status'" )
) {
	throw new RuntimeException( 'Die Tabelle lädt nicht alle Status für den spaltenweisen Clientfilter oder bietet den alten Doppelfilter weiter an.' );
}

if (
	! str_contains( $script_source, '[data-flz-ags-registration-list]' )
	|| ! str_contains( $script_source, '[data-flz-ags-registration-sort]' )
	|| ! str_contains( $script_source, '[data-flz-ags-registration-filter]' )
) {
	throw new RuntimeException( 'Das Admin-Script initialisiert Filter und Sortierung der Anmeldungstabelle nicht.' );
}

if (
	! str_contains( $style_source, '[data-flz-ags-registration-item][hidden]' )
	|| ! str_contains( $style_source, 'display: none !important;' )
) {
	throw new RuntimeException( 'Gefilterte Anmeldungszeilen werden nicht unabhängig vom Tabellenlayout ausgeblendet.' );
}

echo "OK: flz_ags admin registration list smoke test\n";
