<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$entrypoint = file_get_contents($root . '/flz_ags.php');
$upgrade = file_get_contents($root . '/activate-deactivate.php');

foreach (
	array(
		"define('FLZ_AGS_VERSION', '0.6.0')" => $entrypoint,
		"define('FLZ_AGS_DB_VERSION', '2.0.0')" => $entrypoint,
		"get_option('flz_ags_db_version'" => $upgrade,
		"update_option('flz_ags_db_version', FLZ_AGS_DB_VERSION" => $upgrade,
		'flz_ags_upgrade_registration_identity_v2' => $upgrade,
	) as $needle => $source
) {
	if (!is_string($source) || !str_contains($source, $needle)) {
		throw new RuntimeException('Der getrennte AG-Schemaversionsvertrag fehlt: ' . $needle);
	}
}

echo "OK: flz_ags schema version smoke test\n";

