<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/includes/class-flz-ags.php');
$upgrade = file_get_contents($root . '/activate-deactivate.php');

if (str_contains($plugin, "update_option('flz_ags_mock_confirmation_mails'")) {
	throw new RuntimeException('Lokale Mock-Mails werden weiterhin dauerhaft mit personenbezogenem Inhalt gespeichert.');
}
foreach (
	array(
		"set_transient('flz_ags_mock_confirmation_mail_last'" => $plugin,
		'HOUR_IN_SECONDS' => $plugin,
		"delete_transient('flz_ags_mock_confirmation_mail_last')" => $plugin,
		"delete_option('flz_ags_mock_confirmation_mails')" => $upgrade,
	) as $needle => $source
) {
	if (!str_contains($source, $needle)) {
		throw new RuntimeException('Der kurzlebige datensparsame Mail-Capture-Vertrag fehlt: ' . $needle);
	}
}

echo "OK: flz_ags mail capture privacy smoke test\n";
