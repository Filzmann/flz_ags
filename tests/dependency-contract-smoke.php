<?php
$source = file_get_contents( dirname( __DIR__ ) . '/flz_ags.php' );

if ( false === $source ) {
	throw new RuntimeException( 'Plugin-Bootstrap konnte nicht gelesen werden.' );
}

$checks = array(
	array( ! str_contains( $source, 'WP_PLUGIN_DIR' ), 'Das Plugin lädt noch Dateien aus einem Nachbar-Plugin.' ),
	array( str_contains( $source, "add_action('plugins_loaded'" ), 'Der Bootstrap wartet nicht auf plugins_loaded.' ),
	array( str_contains( $source, 'FLZ_WPDB_OBJECTS_VERSION' ), 'Die WPDB-Mindestversion wird nicht geprüft.' ),
	array( str_contains( $source, 'FLZ_UI_COMPONENTS_VERSION' ), 'Die UI-Mindestversion wird nicht geprüft.' ),
);

foreach ( $checks as list( $passed, $message ) ) {
	if ( ! $passed ) {
		throw new RuntimeException( $message );
	}
}

echo "OK: flz_ags dependency contract smoke test\n";
