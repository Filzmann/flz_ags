<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );
define( 'FLZ_AGS_VERSION', '0.6.0' );
define( 'FLZ_AGS_DIR', dirname( __DIR__ ) . '/' );

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

require_once dirname( __DIR__ ) . '/includes/helpers.php';

if ( ! function_exists( 'flz_ags_asset_version' ) ) {
	throw new RuntimeException( 'Eine inhaltsabhängige Asset-Version fehlt; Browser können veraltetes JavaScript weiterverwenden.' );
}

$admin_version    = flz_ags_asset_version( 'assets/js/flz-ags-admin.js' );
$frontend_version = flz_ags_asset_version( 'assets/js/flz-ags.js' );
$missing_version  = flz_ags_asset_version( 'assets/js/fehlt.js' );

if ( ! str_starts_with( $admin_version, FLZ_AGS_VERSION . '-' ) || $admin_version === $frontend_version ) {
	throw new RuntimeException( 'Asset-Versionen bilden den jeweiligen Dateiinhalt nicht deterministisch ab.' );
}

if ( FLZ_AGS_VERSION !== $missing_version ) {
	throw new RuntimeException( 'Bei einem fehlenden Asset greift die Versionierung nicht sicher auf die Plugin-Version zurück.' );
}

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
if (
	false === $plugin_source
	|| ! str_contains( $plugin_source, "flz_ags_asset_version('assets/js/flz-ags-admin.js')" )
	|| ! str_contains( $plugin_source, "flz_ags_asset_version('assets/css/flz-ags.css')" )
) {
	throw new RuntimeException( 'Admin-Script und Stylesheet verwenden die inhaltsabhängige Asset-Version nicht.' );
}

echo "OK: flz_ags asset version smoke test\n";
