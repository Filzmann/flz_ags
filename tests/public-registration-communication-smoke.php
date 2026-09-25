<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$frontend = file_get_contents($root . '/frontend/frontend.php');
$controller = file_get_contents($root . '/includes/class-flz-ags.php');
$helpers = file_get_contents($root . '/includes/helpers.php');
$form = file_get_contents($root . '/templates/frontend-registration-form.php');
$course_form = file_get_contents($root . '/templates/admin-course-form.php');

foreach (array($frontend, $controller, $helpers, $form, $course_form) as $source) {
    if (!is_string($source)) {
        throw new RuntimeException('Eine Quelle für den öffentlichen AG-Kommunikationsvertrag konnte nicht gelesen werden.');
    }
}

if (str_contains($frontend, "(!empty(\$slot->room) ? ' · ' . \$slot->room : '')")) {
    throw new RuntimeException('Die öffentliche AG-Übersicht zeigt weiterhin Raumangaben.');
}

foreach (array(
    'Ersttreffpunkt: Raum ' => $controller,
    'Weitere Raumänderungen organisieren die AG-Leitungen direkt mit den Schülerinnen und Schülern.' => $controller,
    'function flz_ags_required_registration_notice(): string' => $helpers,
    'Die Anmeldung für diese AG gilt nur für ein Schulhalbjahr.' => $helpers,
    'Die nächste Anmeldemöglichkeit ist ab dem 31.01.2027 möglich.' => $helpers,
    'flz_ags_required_registration_notice()' => $form,
    'Deaktivierte AGs werden weder öffentlich angezeigt noch können sie über die Homepage angemeldet werden.' => $course_form,
    'Aktive, aber nicht sichtbare AGs werden weder öffentlich angezeigt noch können sie über die Homepage angemeldet werden.' => $course_form,
    'Die AG bleibt sichtbar, aber die Online-Anmeldung ist geschlossen. Interessierte Schüler*innen können sich direkt an die AG-Leitung wenden.' => $course_form,
) as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('Der öffentliche AG-Kommunikationsvertrag fehlt: ' . $needle); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI reports a test failure only.
    }
}

echo "OK: flz_ags public registration communication smoke test\n";
