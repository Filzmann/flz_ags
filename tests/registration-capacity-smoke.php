<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

// Der Test sichert die drei Schreibwege ab, die aktive Anmeldungen erzeugen:
// öffentliche Anmeldung, Reaktivierung und CSV-Import.
$controller = file_get_contents(dirname(__DIR__) . '/includes/class-flz-ags.php');
if (!is_string($controller)) {
	throw new RuntimeException('Der AG-Controller konnte nicht gelesen werden.');
}

function flz_ags_capacity_test_section(string $source, string $start, string $end): string {
	$offset = strpos($source, $start);
	$limit = strpos($source, $end, $offset === false ? 0 : $offset);
	if ($offset === false || $limit === false) {
		throw new RuntimeException('Der erwartete Controller-Abschnitt fehlt: ' . $start);
	}
	return substr($source, $offset, $limit - $offset);
}

function flz_ags_capacity_test_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

flz_ags_capacity_test_assert(
	str_contains($controller, 'private function assert_slot_capacity_available(')
	&& str_contains($controller, '$taken >= $max_participants')
	&& str_contains($controller, "throw new UnexpectedValueException('Dieser AG-Slot ist inzwischen ausgebucht.')"),
	'Es fehlt eine zentrale Kapazitätsprüfung für aktive AG-Anmeldungen.'
);

$public = flz_ags_capacity_test_section(
	$controller,
	'private function handle_frontend_registration(',
	'public function render_admin_registrations_page()'
);
flz_ags_capacity_test_assert(
	str_contains($public, '$this->assert_slot_capacity_available($slot);'),
	'Die öffentliche Anmeldung verwendet keine zentrale Kapazitätsprüfung.'
);

$update = flz_ags_capacity_test_section(
	$controller,
	'public function handle_update_registration(): void',
	'public function handle_export_courses_csv(): void'
);
flz_ags_capacity_test_assert(
	str_contains($update, 'FLZ_AGS_Model::transaction(')
	&& str_contains($update, '$this->get_slot_with_course($target_slot_id, true)')
	&& str_contains($update, '$this->assert_slot_capacity_available($slot);'),
	'Das Reaktivieren einer Anmeldung ist nicht atomar gegen eine volle Slot-Kapazität abgesichert.'
);

$import = flz_ags_capacity_test_section(
	$controller,
	'private function apply_registration_csv_import(',
	'private function assert_registration_csv_upload()'
);
flz_ags_capacity_test_assert(
	str_contains($import, '$this->get_slot_with_course((int) $slot->id, true)')
	&& str_contains($import, '$this->assert_slot_capacity_available($slot);'),
	'Der CSV-Import kann aktive Anmeldungen ohne Kapazitätsprüfung speichern.'
);

echo "OK: flz_ags registration capacity smoke test\n";
