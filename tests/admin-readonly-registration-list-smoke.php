<?php

if ( PHP_SAPI !== 'cli' ) {

	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$templates = array(
	'admin-all-registrations-readonly.php',
	'admin-leader-registrations.php',
);
$script_source = file_get_contents( dirname( __DIR__ ) . '/assets/js/flz-ags-admin.js' );

if ( false === $script_source ) {
	throw new RuntimeException( 'Das Admin-Script konnte nicht gelesen werden.' );
}

$shared_template = file_get_contents( dirname( __DIR__ ) . '/templates/admin-readonly-registration-table.php' );
if ( false === $shared_template ) {
	throw new RuntimeException( 'Die gemeinsame schreibgeschützte Anmeldungstabelle konnte nicht gelesen werden.' );
}

foreach ( $templates as $template ) {
	$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/' . $template );
	if ( false === $template_source || ! str_contains( $template_source, "admin-readonly-registration-table.php" ) ) {
		throw new RuntimeException( 'Die schreibgeschützte Anmeldungstabelle verwendet nicht den gemeinsamen Tabellenvertrag: ' . $template );
	}
}

$filter_columns = array( 'student', 'class', 'course', 'slot', 'email', 'status', 'date' );
foreach ( $filter_columns as $column ) {
	if (
		! str_contains( $shared_template, "'$column' => array" )
		|| ! str_contains( $shared_template, 'data-flz-ags-registration-filter' )
		|| ! str_contains( $shared_template, 'data-filter-<?php echo esc_attr( $key ); ?>' )
		|| ! str_contains( $shared_template, 'data-flz-ags-registration-sort' )
		|| ! str_contains( $shared_template, 'data-sort-' . $column )
	) {
		throw new RuntimeException( 'Filter- und Sortiervertrag fehlt in der gemeinsamen Tabelle: ' . $column );
	}
}

if (
	! str_contains( $shared_template, 'data-flz-ags-registration-list' )
	|| ! str_contains( $shared_template, 'data-flz-ags-registration-items' )
	|| ! str_contains( $shared_template, 'data-flz-ags-registration-item' )
	|| ! str_contains( $shared_template, 'data-flz-ags-registration-results-status' )
	|| ! str_contains( $shared_template, 'data-flz-ags-registration-no-results' )
) {
	throw new RuntimeException( 'Der vollständige interaktive Listenvertrag fehlt in der gemeinsamen Tabelle.' );
}

if (
	! str_contains( $script_source, '[data-flz-ags-registration-list]' )
	|| ! str_contains( $script_source, '[data-flz-ags-registration-sort]' )
	|| ! str_contains( $script_source, '[data-flz-ags-registration-filter]' )
) {
	throw new RuntimeException( 'Das Admin-Script unterstützt die vereinheitlichten schreibgeschützten Tabellen nicht.' );
}

echo "OK: flz_ags readonly registration list smoke test\n";
