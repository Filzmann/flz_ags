<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$template = file_get_contents($root . '/templates/admin-registrations.php');
$controller = file_get_contents($root . '/includes/class-flz-ags.php');
$script = file_get_contents($root . '/assets/js/flz-ags-admin.js');

function flz_ags_inline_edit_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

flz_ags_inline_edit_assert(
	is_string($template) && str_contains($template, '$ui->editable_row(array(')
	&& str_contains($template, "'name' => 'course_id'")
	&& str_contains($template, "'name' => 'slot_id'")
	&& str_contains($template, "'data-flz-ags-registration-slot-map'")
	&& str_contains($template, "'name' => 'new_status'")
	&& str_contains($template, "'name' => 'class_name'")
	&& str_contains($template, "'name' => 'student_first_name'")
	&& str_contains($template, "'name' => 'student_last_name'")
	&& str_contains($template, "'name' => 'student_email'"),
	'Die Anmeldungstabelle enthält keine vollständigen Inline-Bearbeitungsfelder.'
);
flz_ags_inline_edit_assert(
	is_string($template) && str_contains($template, '$taken >= $max_participants')
	&& str_contains($template, '$course_edit_options[$course_id]')
	&& str_contains($template, '$slot_edit_options_by_course[$course_id]'),
	'Die AG-Auswahl filtert AGs ohne freie Slot-Kapazität nicht aus.'
);
flz_ags_inline_edit_assert(
	is_string($template) && str_contains($template, "'nonce' => 'flz_ags_update_registration'")
	&& str_contains($template, "'action' => 'flz_ags_update_registration'")
	&& str_contains($template, "'data-flz-ags-registration-item' => true"),
	'Die Inline-Bearbeitung ist nicht durch den Anmeldungs-Nonce geschützt.'
);
flz_ags_inline_edit_assert(
	is_string($controller) && str_contains($controller, 'FLZ_AGS_Model::transaction(')
	&& str_contains($controller, '$target_course_id')
	&& str_contains($controller, '$target_slot_id')
	&& str_contains($controller, '$this->get_slot_with_course($target_slot_id, true)')
	&& str_contains($controller, '(int) $slot->course_id !== $target_course_id')
	&& str_contains($controller, 'flz_ags_grade_is_allowed(')
	&& str_contains($controller, 'find_active_for_student('),
	'Der Bearbeitungshandler prüft Zielslot, Jahrgang und aktive Doppelanmeldung nicht atomar.'
);
flz_ags_inline_edit_assert(
	is_string($controller) && str_contains($controller, '$this->assert_slot_capacity_available($slot);'),
	'Der Bearbeitungshandler schützt einen Slot-Wechsel nicht gegen Überbelegung.'
);
flz_ags_inline_edit_assert(
	is_string($script) && str_contains($script, 'function resetRegistrationSlotOptions(')
	&& str_contains($script, "slotSelect.value = '';")
	&& str_contains($script, "'data-flz-ags-registration-slot-map'"),
	'Ein AG-Wechsel leert die Slot-Auswahl nicht dynamisch.'
);

echo "OK: flz_ags admin registration inline edit smoke test\n";
