<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Liefert ausschließlich die manuell benannten Tabellen der Version 0.2.x.
 * Neue Abfragen verwenden immer die vom Modell abgeleiteten Tabellennamen.
 */
function flz_ags_legacy_table(string $name): string
{
    global $wpdb;

    if (!in_array($name, array('courses', 'slots', 'registrations'), true)) {
        throw new InvalidArgumentException('Unbekannte alte AG-Tabelle: ' . $name);
    }

    return $wpdb->prefix . 'flz_ag_' . $name;
}

/**
 * Protokolliert technische Ursachen ohne sie an Besucher auszugeben.
 */
function flz_ags_log_error(Throwable $error, string $context): void
{
    $messages = array();
    $current = $error;
    do {
        $messages[] = get_class($current) . ': ' . $current->getMessage();
        $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    error_log('[flz_ags] ' . $context . ' | ' . implode(' <- ', $messages));
}

/**
 * Liefert sichere Meldungen für bekannte Fehlercodes aus Weiterleitungen.
 */
function flz_ags_error_message(string $code): string
{
    $messages = array(
        'save-course' => 'Die AG konnte nicht vollständig gespeichert werden. Es wurden keine Teiländerungen übernommen.',
        'save-course-detail-page' => 'Die AG konnte nicht gespeichert werden: Für eine geöffnete Anmeldung muss eine gültige AG-Detailseite ausgewählt oder angelegt werden.',
        'install-demo' => 'Die Demo-AGs konnten nicht vollständig angelegt werden. Es wurden keine Teiländerungen übernommen.',
        'update-registration' => 'Die Anmeldung konnte nicht aktualisiert werden.',
        'export' => 'Der CSV-Export konnte nicht erstellt werden.',
    );

    return $messages[$code] ?? 'Die angeforderten AG-Daten konnten nicht verarbeitet werden.';
}

/**
 * Führt eine interne Weiterleitung aus und behandelt auch deren Fehlschlag.
 */
function flz_ags_safe_redirect(string $url): void
{
    if (!wp_safe_redirect($url)) {
        wp_die(esc_html__('Die interne Weiterleitung ist fehlgeschlagen.', 'flz-ags'));
    }
    exit;
}

function flz_ags_manage_capability(): string
{
    return (string) apply_filters('flz_ags_manage_capability', 'manage_options');
}

function flz_ags_default_school_year(): string
{
    $year = (int) current_time('Y');
    $month = (int) current_time('n');

    if ($month >= 8) {
        return $year . '/' . ($year + 1);
    }

    return ($year - 1) . '/' . $year;
}

function flz_ags_current_school_year(): string
{
    $value = (string) get_option('flz_ags_current_school_year', '');
    return $value !== '' ? $value : flz_ags_default_school_year();
}

function flz_ags_sanitize_school_year($value): string
{
    $value = sanitize_text_field((string) $value);
    if (preg_match('/^\d{4}\s*\/\s*\d{4}$/', $value)) {
        return preg_replace('/\s+/', '', $value);
    }

    return flz_ags_default_school_year();
}

function flz_ags_default_classes(): array
{
    return array(
        '7.1', '7.2', '7.3', '7.4', '7.5',
        '8.1', '8.2', '8.3', '8.4', '8.5',
        '9.1', '9.2', '9.3', '9.4', '9.5', '9.6',
        '10.1', '10.2', '10.3', '10.4', '10.5',
        'WKK1', 'WKK2',
        '11_BENK', '11_BLUM', '11_EDEL', '11_BEYE', '11_JOER', '11_KEYS', '11_KRUE', '11_MOES', '11_REIM', '11_WALT',
        '12_BERT', '12_BEST', '12_BEYE', '12_DITT', '12_DOLE', '12_GROS', '12_GUEN', '12_KELL', '12_BIRK', '12_MOHK', '12_TSCH',
    );
}

function flz_ags_get_classes(): array
{
    $classes = get_option('flz_ags_classes', array());
    if (!is_array($classes) || empty($classes)) {
        return flz_ags_default_classes();
    }

    return array_values(array_filter(array_map('sanitize_text_field', $classes)));
}

function flz_ags_sanitize_classes_from_text(string $text): array
{
    $lines = preg_split('/[\r\n,;]+/', $text);
    $classes = array();

    foreach ((array) $lines as $line) {
        $line = trim(sanitize_text_field($line));
        if ($line !== '') {
            $classes[] = $line;
        }
    }

    return array_values(array_unique($classes));
}

function flz_ags_extract_grade_key(string $class_name): string
{
    $class_name = trim($class_name);

    if (preg_match('/^WKK/i', $class_name)) {
        return 'WKK';
    }

    if (preg_match('/^(\d{1,2})(?:[._]|$)/', $class_name, $matches)) {
        return (string) (int) $matches[1];
    }

    return '';
}

function flz_ags_is_valid_class(string $class_name): bool
{
    return in_array($class_name, flz_ags_get_classes(), true);
}

function flz_ags_sanitize_allowed_grades($value): string
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,;]+/', (string) $value);
    }

    $allowed = array();
    foreach ((array) $items as $item) {
        $item = strtoupper(trim(sanitize_text_field((string) $item)));
        if ($item === '') {
            continue;
        }
        if (in_array($item, array('7', '8', '9', '10', '11', '12', 'WKK'), true)) {
            $allowed[] = $item;
        }
    }

    return implode(',', array_values(array_unique($allowed)));
}

function flz_ags_grade_is_allowed(string $class_name, string $allowed_grades, bool $only_grade_7): bool
{
    $grade_key = flz_ags_extract_grade_key($class_name);

    if ($only_grade_7 && $grade_key !== '7') {
        return false;
    }

    $allowed_grades = trim($allowed_grades);
    if ($allowed_grades === '') {
        return true;
    }

    $allowed = array_map('trim', explode(',', strtoupper($allowed_grades)));
    return in_array(strtoupper($grade_key), $allowed, true);
}

function flz_ags_weekdays(): array
{
    return array(
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag',
    );
}

function flz_ags_weekday_label($weekday): string
{
    $weekdays = flz_ags_weekdays();
    $weekday = (int) $weekday;
    return $weekdays[$weekday] ?? '';
}

function flz_ags_status_labels(): array
{
    return array(
        'active' => 'aktiv',
        'withdrawn' => 'widerrufen',
        'cancelled' => 'abgesagt',
        'waitlist' => 'Warteliste',
    );
}

function flz_ags_status_label(string $status): string
{
    $labels = flz_ags_status_labels();
    return $labels[$status] ?? $status;
}

function flz_ags_format_time(?string $time): string
{
    if (function_exists('flz_ui_format_time')) {
        return flz_ui_format_time($time);
    }

    $time = (string) $time;
    if ($time === '') {
        return '';
    }

    return substr($time, 0, 5);
}

function flz_ags_admin_url(array $args = array()): string
{
    return add_query_arg($args, admin_url('admin.php'));
}

function flz_ags_notice(string $message, string $type = 'success'): string
{
    if (function_exists('flz_ui')) {
        return flz_ui()->notice($message, $type);
    }

    $class = $type === 'error' ? 'notice notice-error' : 'notice notice-success';
    return '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
}

function flz_ags_course_image_url($image_url): string
{
    $image_url = trim((string) $image_url);
    if ($image_url !== '') {
        return esc_url($image_url);
    }

    return esc_url(FLZ_AGS_URL . 'assets/img/default-course.svg');
}

/**
 * Ermittelt die WordPress-Seite, die als öffentliche AG-Detailseite dient.
 */
function flz_ags_course_detail_page_id(object $course): int
{
    $detail_page_id = isset($course->detail_page_id) ? absint($course->detail_page_id) : 0;
    if ($detail_page_id > 0 && get_post_type($detail_page_id) === 'page') {
        return $detail_page_id;
    }

    return 0;
}

/**
 * Liefert die öffentliche URL zur Detailseite einer AG.
 */
function flz_ags_course_detail_url(object $course): string
{
    $detail_page_id = flz_ags_course_detail_page_id($course);
    if ($detail_page_id <= 0) {
        return '';
    }

    $permalink = get_permalink($detail_page_id);
    return is_string($permalink) ? $permalink : '';
}

/**
 * Sucht die Sammelseite „AGs“, unter der neue Detailseiten angelegt werden.
 */
function flz_ags_detail_parent_page_id(): int
{
    foreach (array('unser-angebot/ags', 'ags') as $path) {
        $page = get_page_by_path($path, OBJECT, 'page');
        if ($page instanceof WP_Post) {
            return (int) $page->ID;
        }
    }

    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => 20,
        's'              => 'AGs',
    ));
    foreach ($pages as $page) {
        if (!$page instanceof WP_Post) {
            continue;
        }
        if (in_array($page->post_title, array('AGs', 'Arbeitsgemeinschaften'), true)) {
            return (int) $page->ID;
        }
    }

    return 0;
}

function flz_ags_page_status_label(string $status): string
{
    $labels = array(
        'publish' => 'veröffentlicht',
        'draft' => 'Entwurf',
        'pending' => 'ausstehend',
        'private' => 'privat',
        'future' => 'geplant',
    );

    return $labels[$status] ?? $status;
}

/**
 * Kompakte, im Backend gut lesbare Beschriftung für eine WordPress-Seite.
 */
function flz_ags_page_label(int $page_id): string
{
    $page = get_post($page_id);
    if (!$page instanceof WP_Post || $page->post_type !== 'page') {
        return 'Seite #' . $page_id;
    }

    $parts = array();
    $ancestors = array_reverse(get_post_ancestors($page_id));
    foreach ($ancestors as $ancestor_id) {
        $parts[] = get_the_title((int) $ancestor_id);
    }
    $parts[] = get_the_title($page_id);

    $path = implode(' › ', array_filter($parts));
    return $path . ' (' . flz_ags_page_status_label($page->post_status) . ')';
}

function flz_ags_demo_courses(): array
{
    $courses = array();
    foreach (flz_ags_demo_source_pages() as $index => $page) {
        $course = flz_ags_demo_course_from_page($page, $index);
        if (!empty($course)) {
            $courses[] = $course;
        }
    }

    return $courses;
}

/**
 * Liefert die vorhandenen AG-Seiten aus dem WordPress-Seitenbaum.
 *
 * Demo-Daten werden bewusst nicht mehr als harte Liste gepflegt. Die AG-Seiten
 * sind die fachliche Quelle; fehlen dort Zeiten, werden keine künstlichen
 * Demo-Slots erfunden.
 */
function flz_ags_demo_source_pages(): array
{
    $parent_id = flz_ags_detail_parent_page_id();
    if ($parent_id <= 0) {
        return array();
    }

    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'post_parent'    => $parent_id,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ));

    return array_values(array_filter($pages, static function ($page): bool {
        return $page instanceof WP_Post;
    }));
}

function flz_ags_demo_course_from_page(WP_Post $page, int $index): array
{
    $plain = flz_ags_page_plain_text((string) $page->post_content);
    $description = flz_ags_extract_labeled_value($plain, 'Beschreibung');
    if ($description === '') {
        $description = wp_trim_words($plain, 55, ' …');
    }

    $focus = flz_ags_extract_labeled_value($plain, 'Das soll dabei im Fokus stehen');
    $excerpt = trim(wp_strip_all_tags((string) $page->post_excerpt));
    $short_description = $excerpt !== '' ? $excerpt : $focus;
    if ($short_description === '') {
        $short_description = wp_trim_words($description, 24, ' …');
    }

    $allowed_grades = flz_ags_allowed_grades_from_demo_text(
        flz_ags_extract_labeled_value($plain, 'Jahrgang')
    );

    return array(
        'title' => sanitize_text_field(get_the_title($page)),
        'short_description' => sanitize_textarea_field($short_description),
        'description' => sanitize_textarea_field($description),
        'category' => '',
        'leader_name' => sanitize_text_field(flz_ags_demo_leader_from_page($page)),
        'allowed_grades' => $allowed_grades,
        'only_grade_7' => $allowed_grades === '7' ? 1 : 0,
        'image_url' => esc_url_raw(flz_ags_page_image_url((int) $page->ID, (string) $page->post_content)),
        'detail_page_id' => (int) $page->ID,
        'sort_order' => ($index + 1) * 10,
        'slots' => flz_ags_demo_slots_from_page_text($plain),
    );
}

function flz_ags_page_plain_text(string $content): string
{
    $content = str_replace(array('</td>', '</tr>', '</p>', '<br>', '<br/>', '<br />'), ' ', $content);
    $plain = wp_strip_all_tags($content);
    $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $plain = str_replace("\xc2\xa0", ' ', $plain);

    return trim((string) preg_replace('/\s+/u', ' ', $plain));
}

function flz_ags_extract_labeled_value(string $plain, string $label): string
{
    $labels = array(
        'Das soll dabei im Fokus stehen',
        'Ziel',
        'Beschreibung',
        'Ort',
        'Raum',
        'Anforderungen / Niveau',
        'Rhythmus',
        'Zeit',
        'Dauer',
        'Jahrgang',
        'Anzahl Teilnehmende',
        'Teilnahme',
    );
    $quoted = array_map(static function (string $item): string {
        return preg_quote($item, '/');
    }, $labels);

    $pattern = '/(?:^|\s)' . preg_quote($label, '/') . '\s*:\s*(.*?)(?=\s+(?:' . implode('|', $quoted) . ')\s*:|$)/ui';
    if (!preg_match($pattern, $plain, $matches)) {
        return '';
    }

    return trim((string) $matches[1]);
}

function flz_ags_demo_leader_from_page(WP_Post $page): string
{
    $leader = flz_ags_extract_labeled_value(flz_ags_page_plain_text((string) $page->post_content), 'Leitung');
    if ($leader !== '') {
        return $leader;
    }

    if (preg_match('/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>/is', (string) $page->post_content, $matches)) {
        $leader = trim(wp_strip_all_tags((string) $matches[1]));
        $leader = (string) preg_replace('/\s+/u', ' ', html_entity_decode($leader, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'));
        if ($leader !== '' && stripos($leader, get_the_title($page)) === false) {
            return $leader;
        }
    }

    return '';
}

function flz_ags_page_image_url(int $page_id, string $content): string
{
    $thumbnail = get_the_post_thumbnail_url($page_id, 'medium_large');
    if (is_string($thumbnail) && $thumbnail !== '') {
        return $thumbnail;
    }

    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        return (string) $matches[1];
    }

    return '';
}

function flz_ags_allowed_grades_from_demo_text(string $value): string
{
    $value = strtoupper(str_replace(array('–', '—'), '-', $value));
    $grades = array();

    if (preg_match('/\b(7|8|9|10|11|12)\s*-\s*(7|8|9|10|11|12)\b/', $value, $range)) {
        $start = (int) $range[1];
        $end = (int) $range[2];
        if ($start <= $end) {
            for ($grade = $start; $grade <= $end; $grade++) {
                $grades[] = (string) $grade;
            }
        }
    }

    if (preg_match_all('/\b(?:7|8|9|10|11|12|WKK)\b/', $value, $matches)) {
        $grades = array_merge($grades, $matches[0]);
    }

    return flz_ags_sanitize_allowed_grades($grades);
}

function flz_ags_demo_slots_from_page_text(string $plain): array
{
    $time_text = flz_ags_extract_labeled_value($plain, 'Zeit');
    if ($time_text === '') {
        $time_text = $plain;
    }

    $pattern = '/(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag)\s+(\d{1,2})[:.](\d{2})\s*(?:–|—|-|bis)\s*(\d{1,2})[:.](\d{2})/ui';
    if (!preg_match_all($pattern, $time_text, $matches, PREG_SET_ORDER)) {
        return array();
    }

    $slots = array();
    $room = sanitize_text_field(flz_ags_extract_labeled_value($plain, 'Raum'));
    $max_participants = flz_ags_demo_max_participants($plain);
    foreach ($matches as $match) {
        $weekday = flz_ags_weekday_from_label((string) $match[1]);
        if ($weekday <= 0) {
            continue;
        }

        $slots[] = array(
            'weekday' => $weekday,
            'start_time' => sprintf('%02d:%02d', (int) $match[2], (int) $match[3]),
            'end_time' => sprintf('%02d:%02d', (int) $match[4], (int) $match[5]),
            'room' => $room,
            'max_participants' => $max_participants,
        );
    }

    return $slots;
}

function flz_ags_weekday_from_label(string $label): int
{
    $label = strtolower($label);
    foreach (flz_ags_weekdays() as $weekday => $weekday_label) {
        if ($label === strtolower($weekday_label)) {
            return (int) $weekday;
        }
    }

    return 0;
}

function flz_ags_demo_max_participants(string $plain): int
{
    $value = flz_ags_extract_labeled_value($plain, 'Anzahl Teilnehmende');
    if ($value === '' || !preg_match_all('/\d+/', $value, $matches)) {
        return 0;
    }

    return max(array_map('intval', $matches[0]));
}
