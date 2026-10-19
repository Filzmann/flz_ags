<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/includes/class-flz-ags.php');
$course = file_get_contents($root . '/includes/models/class-flz-ags-course.php');
$registration = file_get_contents($root . '/includes/models/class-flz-ags-registration.php');
$settings = file_get_contents($root . '/templates/admin-settings.php');
$form = file_get_contents($root . '/templates/frontend-registration-form.php');
$helpers = file_get_contents($root . '/includes/helpers.php');
$css = file_get_contents($root . '/assets/css/flz-ags.css');
$activation = file_get_contents($root . '/activate-deactivate.php');
$leader_template = file_get_contents($root . '/templates/admin-leader-registrations.php');

foreach (array($plugin, $course, $registration, $settings, $form, $helpers, $css, $activation, $leader_template) as $source) {
    if (!is_string($source)) {
        throw new RuntimeException('Eine für den AG-Leitungs-Vertrag benötigte Quelldatei konnte nicht gelesen werden.');
    }
}

foreach (array(
    'flz_ags_view_assigned_courses_capability' => $plugin,
    "c.leader_user_id = %d" => $registration,
    'find_for_leader' => $course,
    'find_for_leader' => $registration,
    'leader_user_id bigint(20) unsigned NULL' => $course,
    'registration_form_notice' => $settings,
    'multiple_registrations_enabled' => $settings,
    'registration_form_notice' => $form,
    'wp_kses_post( wpautop( $registration_form_notice ) )' => $form,
    'z-index: 2001' => $css,
    '.flz-ags-registration-panel.is-open,' => $css,
    '.flz-ags-registration-panel:target {' => $css,
    'z-index: 2003 !important' => $css,
    '.flz-ags-registration-panel .flz-ags-registration > h2' => $css,
    'display: none' => $css,
    'flz_ags_create_leader_role' => $activation,
) as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('Der gewünschte AG-Leitungs- oder Anmeldevertrag fehlt: ' . $needle);
    }
}

if ( ! str_contains( $helpers, 'function flz_ags_default_registration_form_notice()' ) ) {
    throw new RuntimeException( 'Der editierbare Standardhinweis für die AG-Anmeldung fehlt.' );
}
if ( str_contains( $form, 'flz_ags_required_registration_notice' ) ) {
    throw new RuntimeException( 'Das öffentliche AG-Formular verwendet weiterhin einen nicht editierbaren Hinweis.' );
}
if ( ! str_contains( $settings, 'Hinweis im Anmeldeformular' ) ) {
    throw new RuntimeException( 'Die Einstellung für den sichtbaren AG-Anmeldehinweis ist nicht verständlich beschriftet.' );
}

if (str_contains($plugin, "'Raum'   => \$slot->room")) {
    throw new RuntimeException('Der Raum wird weiterhin im öffentlichen Anmeldeformular angezeigt.');
}

if (!str_contains($registration, 'UNIQUE KEY active_student_course_key (active_student_key, course_id)')) {
    throw new RuntimeException('Mehrfachanmeldungen sind nicht pro AG technisch abgesichert.');
}

if (str_contains($leader_template, 'admin-post.php')) {
    throw new RuntimeException('Die Ansicht für AG-Leitungen enthält einen Schreibpfad.');
}

echo "OK: flz_ags leader readonly and registration settings smoke test\n";
