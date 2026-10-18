<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Flz_ags
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run tests/bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Lädt eine Testabhängigkeit über einen expliziten, von der Testumgebung
 * gesetzten Pfad. Produktcode bindet weiterhin keine Nachbar-Repositories ein.
 */
function flz_ags_test_dependency_path( string $environment_name ): string {
	$path = getenv( $environment_name );
	if ( ! is_string( $path ) || '' === $path || ! is_dir( $path ) ) {
		echo "Die Testabhängigkeit {$environment_name} fehlt oder ist kein Verzeichnis." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}

	return rtrim( $path, '/\\' );
}

/**
 * Manually load the plugin and its public dependency entry points.
 */
function _manually_load_plugin() {
	require_once flz_ags_test_dependency_path( 'FLZ_WPDB_OBJECTS_DIR' ) . '/flz_wpdb_objects.php';
	require_once flz_ags_test_dependency_path( 'FLZ_UI_COMPONENTS_DIR' ) . '/flz_ui_components.php';
	require dirname( dirname( __FILE__ ) ) . '/flz_ags.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
