<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$model = file_get_contents($root . '/includes/models/class-flz-ags-registration.php');
$upgrade = file_get_contents($root . '/activate-deactivate.php');
$controller = file_get_contents($root . '/includes/class-flz-ags.php');

foreach (
	array(
		'student_key char(64) NOT NULL' => $model,
		'active_student_key char(64) NULL' => $model,
		'UNIQUE KEY active_student_key' => $model,
		'\'active\' === $this->status ? $this->student_key : null' => $model,
		'flz_ags_registration_student_key' => $model,
		'ADD UNIQUE KEY active_student_key' => $upgrade,
		'flz_ags_has_active_registration_conflict' => $controller,
	) as $needle => $source
) {
	if (!is_string($source) || !str_contains($source, $needle)) {
		throw new RuntimeException('Die globale AG-Anmeldungseindeutigkeit fehlt: ' . $needle);
	}
}

echo "OK: flz_ags registration uniqueness smoke test\n";
