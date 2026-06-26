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

/**
 * Verhindert, dass Tabellenkalkulationen Nutzwerte als Formel ausführen.
 *
 * @deprecated Seit 0.3.2 zentral über flz_wpdb_objects_csv_safe_cell().
 */
function flz_ags_csv_cell($value): string
{
    if (function_exists('flz_wpdb_objects_csv_safe_cell')) {
        return flz_wpdb_objects_csv_safe_cell($value);
    }

    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
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

    return esc_url(FLZ_AGS_URL . 'assets/img/demo/default.svg');
}

function flz_ags_demo_courses(): array
{
    $base = FLZ_AGS_URL . 'assets/img/demo/';

    return array(
        array(
            'title' => 'Aquaristik AG',
            'short_description' => 'Pflege und Wartung der Aquarien und deren Bewohner.',
            'description' => 'Wöchentliche Pflege der Aquarien, Wasserwechsel, Pflanzenpflege und Wasseranalysen. Demo-Datensatz aus der bestehenden AG-Darstellung.',
            'category' => 'Naturwissenschaften',
            'leader_name' => 'Thomas Grabowski',
            'allowed_grades' => '7,8,9,10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'aquaristik.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/ag-indien-7/',
            'sort_order' => 10,
            'slots' => array(
                array('weekday' => 3, 'start_time' => '15:30', 'end_time' => '17:00', 'room' => 'Mensa', 'max_participants' => 9),
            ),
        ),
        array(
            'title' => 'Basketball AG',
            'short_description' => 'Spaß am Basketballspiel für Neulinge und Fortgeschrittene.',
            'description' => 'Basketball nach dem Unterricht, Technik, Spielpraxis und ggf. Aufbau eines Schulteams. Demo-Datensatz aus der bestehenden AG-Darstellung.',
            'category' => 'Sport',
            'leader_name' => 'Samer Korsus / Kevin Do',
            'allowed_grades' => '7,8,9,10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'basketball.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/basketball-ag/',
            'sort_order' => 20,
            'slots' => array(
                array('weekday' => 4, 'start_time' => '13:40', 'end_time' => '15:10', 'room' => 'TH-D', 'max_participants' => 20),
            ),
        ),
        array(
            'title' => 'Bollywood-AG',
            'short_description' => 'Indische Kultur kreativ erleben: Tanz, Theater, Kochen und Feste.',
            'description' => 'Die AG verbindet Tradition und Moderne und macht Indien über kreative Formate erlebbar. Demo-Datensatz aus der bestehenden AG-Darstellung.',
            'category' => 'Kultur',
            'leader_name' => 'Alisha Chauhan',
            'allowed_grades' => '10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'bollywood.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/bollywood/',
            'sort_order' => 30,
            'slots' => array(
                array('weekday' => 4, 'start_time' => '13:40', 'end_time' => '15:10', 'room' => '1405', 'max_participants' => 12),
            ),
        ),
        array(
            'title' => 'Instrumental AG',
            'short_description' => 'Instrumente spielen, singen und musikalische Fähigkeiten entwickeln.',
            'description' => 'Erarbeitung eines Repertoires und Verbesserung des Zusammenspiels. Demo-Datensatz aus der bestehenden AG-Darstellung.',
            'category' => 'Musik',
            'leader_name' => 'Frau Große / Frau Preidel',
            'allowed_grades' => '7',
            'only_grade_7' => 1,
            'image_url' => $base . 'instrumental.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/instrumental-ag/',
            'sort_order' => 40,
            'slots' => array(
                array('weekday' => 3, 'start_time' => '13:40', 'end_time' => '14:40', 'room' => '2006', 'max_participants' => 20),
            ),
        ),
        array(
            'title' => 'Hausaufgabenhilfe',
            'short_description' => 'Unterstützung beim Lernen, Üben und Organisieren von Aufgaben.',
            'description' => 'Demo-Datensatz nach vorhandener AG-Übersicht. Zeiten und Raum bitte vor Produktivbetrieb prüfen.',
            'category' => 'Lernförderung',
            'leader_name' => 'Tagore-Gymnasium',
            'allowed_grades' => '7,8,9,10',
            'only_grade_7' => 0,
            'image_url' => $base . 'hausaufgabenhilfe.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/hausaufgabenhilfe/',
            'sort_order' => 50,
            'slots' => array(
                array('weekday' => 2, 'start_time' => '14:30', 'end_time' => '15:30', 'room' => 'Lernraum', 'max_participants' => 16),
                array('weekday' => 4, 'start_time' => '14:30', 'end_time' => '15:30', 'room' => 'Lernraum', 'max_participants' => 16),
            ),
        ),
        array(
            'title' => 'Gesundes Kochen',
            'short_description' => 'Gemeinsam einfache, gesunde Gerichte planen und zubereiten.',
            'description' => 'Demo-Datensatz nach vorhandener AG-Übersicht. Zeiten und Raum bitte vor Produktivbetrieb prüfen.',
            'category' => 'Gesundheit / Kochen',
            'leader_name' => 'Tagore-Gymnasium',
            'allowed_grades' => '7,8,9,10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'kochen.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/gesundes-kochen/',
            'sort_order' => 60,
            'slots' => array(
                array('weekday' => 2, 'start_time' => '14:30', 'end_time' => '16:00', 'room' => 'Lehrküche', 'max_participants' => 12),
            ),
        ),
        array(
            'title' => 'Line Dance',
            'short_description' => 'Tanzen in der Gruppe mit festen Schrittfolgen und Musik.',
            'description' => 'Demo-Datensatz nach vorhandener AG-Übersicht. Zeiten und Raum bitte vor Produktivbetrieb prüfen.',
            'category' => 'Tanz / Sport',
            'leader_name' => 'Tagore-Gymnasium',
            'allowed_grades' => '7,8,9,10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'dance.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/line-dance/',
            'sort_order' => 70,
            'slots' => array(
                array('weekday' => 3, 'start_time' => '14:30', 'end_time' => '15:30', 'room' => 'Aula', 'max_participants' => 18),
            ),
        ),
        array(
            'title' => 'Robo Cup-AG',
            'short_description' => 'Robotik, Konstruktion und Programmierung im Team.',
            'description' => 'Demo-Datensatz nach vorhandener AG-Übersicht. Zeiten und Raum bitte vor Produktivbetrieb prüfen.',
            'category' => 'Informatik / Technik',
            'leader_name' => 'Tagore-Gymnasium',
            'allowed_grades' => '7,8,9,10,11,12',
            'only_grade_7' => 0,
            'image_url' => $base . 'robotik.svg',
            'info_url' => 'https://tagore-gymnasium.de/unser-angebot/ags/robo-cup-ag/',
            'sort_order' => 80,
            'slots' => array(
                array('weekday' => 1, 'start_time' => '14:30', 'end_time' => '16:00', 'room' => 'Computerraum', 'max_participants' => 12),
            ),
        ),
    );
}
