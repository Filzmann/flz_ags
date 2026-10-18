<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

define('ABSPATH', __DIR__ . '/');
$flz_ags_test_options = array();
function get_option(string $name, $default = false) {
	global $flz_ags_test_options;
	return $flz_ags_test_options[$name] ?? $default;
}
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function current_datetime(): DateTimeImmutable { return new DateTimeImmutable('2026-08-20 12:00:00', new DateTimeZone('Europe/Berlin')); }

$root = dirname(__DIR__);
$helpers = file_get_contents($root . '/includes/helpers.php');
$plugin = file_get_contents($root . '/includes/class-flz-ags.php');
$settings = file_get_contents($root . '/templates/admin-settings.php');
$activation = file_get_contents($root . '/activate-deactivate.php');
$all_registrations = file_get_contents($root . '/templates/admin-all-registrations-readonly.php');

foreach (array($helpers, $plugin, $settings, $activation, $all_registrations) as $source) {
	if (!is_string($source)) {
		throw new RuntimeException('Eine Datei für den Halbjahres- und Rechtevertrag konnte nicht gelesen werden.');
	}
}

if (str_contains($all_registrations, 'admin-post.php') || !str_contains($all_registrations, 'Zeitraum')) {
	throw new RuntimeException('Die AG-Verwaltungsansicht ist nicht strikt lesend oder besitzt keinen Zeitraumfilter.');
}

require_once $root . '/includes/helpers.php';
global $flz_ags_test_options;
$flz_ags_test_options = array('flz_ags_period_mode' => 'semester');
if (flz_ags_default_period_for_date(new DateTimeImmutable('2026-09-01', new DateTimeZone('Europe/Berlin'))) !== '2026/2027-1') {
	throw new RuntimeException('Der Halbjahresmodus erzeugt keinen ersten Halbjahreswert.');
}
if (flz_ags_default_period_for_date(new DateTimeImmutable('2027-03-01', new DateTimeZone('Europe/Berlin'))) !== '2026/2027-2') {
	throw new RuntimeException('Der Halbjahresmodus erzeugt keinen zweiten Halbjahreswert.');
}
if (flz_ags_sanitize_period('2026/2027') !== '2026/2027' || flz_ags_sanitize_period('2026/2027-2') !== '2026/2027-2') {
	throw new RuntimeException('Historische Jahreswerte oder Halbjahreswerte werden nicht akzeptiert.');
}
$flz_ags_test_options = array('flz_ags_registration_grade_7_exclusive_start' => '2026-08-20', 'flz_ags_registration_grade_7_exclusive_days' => 7);
if (!flz_ags_grade_7_exclusive_window_active(new DateTimeImmutable('2026-08-26 23:59:59', new DateTimeZone('Europe/Berlin')))) {
	throw new RuntimeException('Die 7.-Klasse-Sperre endet zu früh.');
}
if (flz_ags_grade_7_exclusive_window_active(new DateTimeImmutable('2026-08-27 00:00:00', new DateTimeZone('Europe/Berlin')))) {
	throw new RuntimeException('Die 7.-Klasse-Sperre endet nicht nach der konfigurierten Dauer.');
}

foreach (array(
	'flz_ags_period_mode' => $helpers,
	'flz_ags_current_period' => $helpers,
	'flz_ags_sanitize_period' => $helpers,
	'flz_ags_view_all_registrations_capability' => $helpers,
	'flz_ags_administration_role_slug' => $helpers,
	'flz_ags_registration_grade_7_exclusive_start' => $helpers,
	'flz_ags_registration_grade_7_exclusive_days' => $helpers,
	'flz_ags_grade_7_exclusive_window_active' => $helpers,
	'flz_ags_view_all_registrations_capability' => $plugin,
	'flz_ags_grade_7_exclusive_window_active' => $plugin,
	'flz_ags_create_administration_role' => $activation,
	'Anmeldezeitraum für Klasse 7' => $settings,
	'Zeitraum der AGs und Anmeldungen' => $settings,
) as $needle => $source) {
	if (!str_contains($source, $needle)) {
		throw new RuntimeException('Der Halbjahres-, Rechte- oder Anmeldevertrag fehlt: ' . $needle);
	}
}

$registration_handler = strpos($plugin, 'private function handle_frontend_registration(');
$registration_handler_end = strpos($plugin, 'public function render_admin_registrations_page()', $registration_handler ?: 0);
if (false === $registration_handler || false === $registration_handler_end) {
	throw new RuntimeException('Der öffentliche Anmeldepfad konnte nicht eingegrenzt werden.');
}
$registration_handler_source = substr($plugin, $registration_handler, $registration_handler_end - $registration_handler);
if (!str_contains($registration_handler_source, 'flz_ags_grade_7_exclusive_window_active')) {
	throw new RuntimeException('Die 7.-Klassen-Sperre wird vor dem Speichern nicht geprüft.');
}
if (!str_contains($registration_handler_source, 'find_active_for_student(')) {
	throw new RuntimeException('Die 7.-Klassen-Sperre prüft keine bestehende aktive Anmeldung.');
}

echo "OK: flz_ags semester period and access smoke test\n";
