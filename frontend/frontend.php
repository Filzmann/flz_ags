<?php

defined('ABSPATH') || exit;

/**
 * Frontend-Renderer für öffentliche AG-Ausgaben.
 *
 * Frontend-Controller geben nur Daten weiter; das Markup liegt unter
 * `templates/frontend-*`.
 *
 * @param array<string,mixed> $vars Template-Variablen.
 */
function flz_ags_render_frontend_template(string $template, array $vars = array()): void
{
    flz_ags_render_template('frontend-' . $template, $vars);
}

/**
 * Rendert ein Frontend-Template als String.
 *
 * @param array<string,mixed> $vars Template-Variablen.
 */
function flz_ags_get_frontend_template(string $template, array $vars = array()): string
{
    return flz_ags_get_template('frontend-' . $template, $vars);
}

/**
 * Rendert die öffentlichen Filter für Listen oder Anmeldeformular.
 */
function flz_ags_render_frontend_filters(bool $include_classes): void
{
    flz_ags_render_frontend_template('filters', array(
        'include_classes' => $include_classes,
    ));
}

/**
 * Rendert eine öffentliche AG-Karte.
 */
function flz_ags_render_course_card(object $course): void
{
    flz_ags_render_frontend_template('course-card', array(
        'card_args' => flz_ags_course_card_args($course),
    ));
}

/**
 * Bereitet die zentrale Kartenkomponente für eine öffentliche AG auf.
 *
 * @return array<string,mixed>
 */
function flz_ags_course_card_args(object $course): array
{
    $weekdays = array();
    $slot_lines = array();
    $total_max = 0;
    $total_free = 0;
    $all_full = true;

    foreach ((array) ($course->slots ?? array()) as $slot) {
        $taken = FLZ_AGS_Registration::count_by(array('slot_id' => (int) $slot->id, 'status' => 'active'));
        $max = (int) $slot->max_participants;
        $free = $max > 0 ? max(0, $max - $taken) : null;
        $weekdays[] = (string) $slot->weekday;
        $slot_lines[] = flz_ags_weekday_label($slot->weekday)
            . ', '
            . flz_ags_format_time($slot->start_time)
            . '–'
            . flz_ags_format_time($slot->end_time)
            . (!empty($slot->room) ? ' · ' . $slot->room : '');

        if ($max > 0) {
            $total_max += $max;
            $total_free += (int) $free;
            if ($free > 0) {
                $all_full = false;
            }
        } else {
            $all_full = false;
        }
    }

    $target = $course->only_grade_7
        ? 'nur Klasse 7'
        : flz_ags_allowed_grades_label((string) $course->allowed_grades);
    $meta = array();
    if (!empty($course->category)) {
        $meta['Bereich'] = $course->category;
    }
    if (!empty($course->leader_name)) {
        $meta['Leitung'] = $course->leader_name;
    }
    $meta['Zielgruppe'] = $target;
    $meta['Termine'] = implode(' | ', $slot_lines);
    $meta['Plätze'] = $total_max > 0 ? $total_free . ' frei von ' . $total_max : 'keine Begrenzung hinterlegt';

    $actions = array();
    $detail_page_id = flz_ags_course_detail_page_id($course);
    $detail_url = flz_ags_course_detail_url($course);
    if ($detail_url !== '') {
        $has_detail_registration = $detail_page_id > 0 && !$all_full && !empty($course->registration_open);
        $label = $has_detail_registration ? 'Details und Anmeldung' : 'Details anzeigen';
        $actions[] = array(
            'href'     => $detail_url,
            'label'    => $label,
            'variant'  => $has_detail_registration ? 'primary' : 'secondary',
            'icon'     => 'view',
            'icon_alt' => $label,
        );
    }

    return array(
        'class'     => 'flz-ags-card flz-ags-course-card',
        'attrs'     => array(
            'data-flz-ags-filter-item' => true,
            'data-weekdays'            => implode(',', array_values(array_unique($weekdays))),
            'data-only-grade-7'        => (int) $course->only_grade_7,
            'data-allowed-grades'      => $course->allowed_grades,
            'data-full'                => $all_full ? '1' : '0',
        ),
        'image_url' => flz_ags_course_image_url($course->image_url ?? ''),
        'image_alt' => (string) $course->title,
        'title'     => (string) $course->title,
        'text'      => (string) $course->short_description,
        'meta'      => $meta,
        'badge'     => $all_full ? 'ausgebucht' : '',
        'actions'   => $actions,
    );
}
