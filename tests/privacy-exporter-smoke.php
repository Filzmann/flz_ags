<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$privacy_file = $root . '/includes/privacy.php';
$entrypoint = file_get_contents($root . '/flz_ags.php');

if (!is_file($privacy_file)) {
	throw new RuntimeException('Der AG-Privacy-Vertrag fehlt.');
}
$privacy = file_get_contents($privacy_file);
foreach (
	array(
		'wp_privacy_personal_data_exporters' => $privacy,
		'wp_privacy_personal_data_erasers' => $privacy,
		'FLZ_AGS_Model::transaction' => $privacy,
		'find_by_email' => $privacy,
		"require_once FLZ_AGS_DIR . 'includes/privacy.php'" => $entrypoint,
	) as $needle => $source
) {
	if (!is_string($source) || !str_contains($source, $needle)) {
		throw new RuntimeException('Der AG-Privacy-Vertrag fehlt: ' . $needle);
	}
}

echo "OK: flz_ags privacy exporter/eraser smoke test\n";

