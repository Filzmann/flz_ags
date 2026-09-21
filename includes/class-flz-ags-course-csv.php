<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Diagnosedaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Versionierter, portabler CSV-Vertrag für AG-Stammdaten und ihre Termine.
 */
final class FLZ_AGS_Course_CSV
{
    public const VERSION = 'flz_ags_courses_v1';

    /**
     * @return array<int,string>
     */
    public static function header(): array
    {
        return array(
            'format_version',
            'record_type',
            'course_key',
            'school_year',
            'title',
            'slug',
            'short_description',
            'description',
            'image_url',
            'detail_page_path',
            'category',
            'leader_name',
            'allowed_grades',
            'only_grade_7',
            'is_active',
            'is_visible',
            'registration_open',
            'course_sort_order',
            'slot_weekday',
            'slot_start_time',
            'slot_end_time',
            'slot_room',
            'slot_max_participants',
            'slot_is_active',
            'slot_sort_order',
        );
    }

    public static function course_key(string $school_year, string $slug): string
    {
        return $school_year . '|' . $slug;
    }

    /**
     * @param array<int,object> $courses
     * @return array<int,array<int,string|int>>
     */
    public static function export_rows(array $courses, callable $slots_for_course, ?callable $detail_page_path = null): array
    {
        $rows = array();

        foreach ($courses as $course) {
            if (!is_object($course) || empty($course->id)) {
                throw new UnexpectedValueException('Ein AG-Exportdatensatz besitzt keine gültige ID.');
            }

            $school_year = (string) ($course->school_year ?? '');
            $slug = (string) ($course->slug ?? '');
            $key = self::course_key($school_year, $slug);
            $rows[] = array(
                self::VERSION,
                'course',
                $key,
                $school_year,
                (string) ($course->title ?? ''),
                $slug,
                (string) ($course->short_description ?? ''),
                (string) ($course->description ?? ''),
                (string) ($course->image_url ?? ''),
                !empty($course->detail_page_id) && null !== $detail_page_path
                    ? (string) $detail_page_path((int) $course->detail_page_id)
                    : '',
                (string) ($course->category ?? ''),
                (string) ($course->leader_name ?? ''),
                (string) ($course->allowed_grades ?? ''),
                (int) ($course->only_grade_7 ?? 0),
                (int) ($course->is_active ?? 0),
                (int) ($course->is_visible ?? 0),
                (int) ($course->registration_open ?? 0),
                (int) ($course->sort_order ?? 0),
                '', '', '', '', '', '', '',
            );

            $slots = $slots_for_course((int) $course->id);
            if (!is_array($slots)) {
                throw new UnexpectedValueException('Die Termine einer AG konnten nicht als Liste exportiert werden.');
            }

            foreach ($slots as $slot) {
                if (!is_object($slot)) {
                    throw new UnexpectedValueException('Ein AG-Termin besitzt kein gültiges Datenformat.');
                }
                $rows[] = array(
                    self::VERSION,
                    'slot',
                    $key,
                    $school_year,
                    '',
                    $slug,
                    '', '', '', '', '', '', '', '', '', '', '', '',
                    (int) ($slot->weekday ?? 0),
                    (string) ($slot->start_time ?? ''),
                    (string) ($slot->end_time ?? ''),
                    (string) ($slot->room ?? ''),
                    (int) ($slot->max_participants ?? 0),
                    (int) ($slot->is_active ?? 0),
                    (int) ($slot->sort_order ?? 0),
                );
            }
        }

        return $rows;
    }

    /**
     * Liest möglichst viele gültige AG- und Slot-Datensätze und protokolliert
     * jede automatische Korrektur oder ausgelassene Zeile verständlich.
     * Nur eine unbrauchbare Kopfzeile verhindert die gesamte Verarbeitung.
     *
     * @param array<int,array<int,string|null>> $raw_rows Einschließlich Kopfzeile.
     * @return array{courses:array<string,array{course:array<string,mixed>,slots:array<int,array<string,mixed>>}>,warnings:array<int,string>}
     */
    public static function parse_tolerant(array $raw_rows): array
    {
        if (empty($raw_rows)) {
            throw new UnexpectedValueException('Die AG-CSV ist leer.');
        }

        $header = array_map(array(self::class, 'decode_cell'), array_shift($raw_rows));
        if ($header !== self::header()) {
            throw new UnexpectedValueException('Die Kopfzeile entspricht nicht dem erwarteten AG-CSV-Format. Bitte eine zuvor heruntergeladene AG-CSV als Vorlage verwenden.');
        }

        $warnings = array();
        $course_rows = array();
        $courses = array();
        $pending_slots = array();
        $column_count = count(self::header());

        foreach ($raw_rows as $index => $raw_row) {
            $line_number = $index + 2;
            if (!is_array($raw_row)) {
                $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: kein lesbarer CSV-Datensatz.';
                continue;
            }
            $row = array_map(array(self::class, 'decode_cell'), $raw_row);
            if (count(array_filter($row, static fn(string $value): bool => '' !== $value)) === 0) {
                continue;
            }
            if (count($row) > $column_count) {
                $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: zu viele Spalten.';
                continue;
            }
            if (count($row) < $column_count) {
                $row = array_pad($row, $column_count, '');
                $warnings[] = 'Zeile ' . $line_number . ' hatte fehlende Spalten; leere Standardwerte wurden ergänzt.';
            }
            if (self::VERSION !== $row[0]) {
                $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: unbekannte Format-Version „' . $row[0] . '“.';
                continue;
            }

            if ('course' === $row[1]) {
                $row = self::repair_course_row($row, $line_number, $warnings);
                try {
                    $parsed = self::parse(array(self::header(), $row));
                    $key = (string) array_key_first($parsed);
                    if (isset($courses[$key])) {
                        $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: Die AG „' . $key . '“ kam bereits vorher in der Datei vor.';
                        continue;
                    }
                    $courses[$key] = $parsed[$key];
                    $course_rows[$key] = $row;
                } catch (UnexpectedValueException $error) {
                    $warnings[] = self::skipped_warning($line_number, $error);
                }
                continue;
            }

            if ('slot' === $row[1]) {
                $pending_slots[] = array('line' => $line_number, 'row' => $row);
                continue;
            }

            $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: unbekannter Datensatztyp „' . $row[1] . '“.';
        }

        $slot_keys = array();
        foreach ($pending_slots as $pending_slot) {
            $line_number = (int) $pending_slot['line'];
            $row = self::repair_slot_row($pending_slot['row'], $line_number, $warnings);
            $key = (string) $row[2];
            if (!isset($course_rows[$key])) {
                $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: Für den Termin wurde keine importierbare AG „' . $key . '“ gefunden.';
                continue;
            }

            try {
                $parsed = self::parse(array(self::header(), $course_rows[$key], $row));
                $slot = $parsed[$key]['slots'][0];
                $slot_key = implode('|', array($slot['weekday'], $slot['start_time'], $slot['end_time'], strtolower((string) $slot['room'])));
                if (isset($slot_keys[$key][$slot_key])) {
                    $warnings[] = 'Zeile ' . $line_number . ' wurde übersprungen: Dieser Termin kam bereits vorher in der Datei vor.';
                    continue;
                }
                $slot_keys[$key][$slot_key] = true;
                $courses[$key]['slots'][] = $slot;
            } catch (UnexpectedValueException $error) {
                $warnings[] = self::skipped_warning($line_number, $error);
            }
        }

        ksort($courses, SORT_STRING);
        return array('courses' => $courses, 'warnings' => $warnings);
    }

    /**
     * Überträgt alle importierbaren AGs und ihre Slots in ein Zielschuljahr.
     * Kollidieren mehrere Quell-AGs nach der Umstellung, bleibt die aus dem
     * neuesten Quellschuljahr erhalten; ältere werden nachvollziehbar ausgelassen.
     *
     * @param array{courses:array<string,array{course:array<string,mixed>,slots:array<int,array<string,mixed>>}>,warnings:array<int,string>} $parsed
     * @return array{courses:array<string,array{course:array<string,mixed>,slots:array<int,array<string,mixed>>}>,warnings:array<int,string>}
     */
    public static function remap_school_year(array $parsed, string $target_school_year): array
    {
        if (
            preg_match('/^(\d{4})\/(\d{4})$/', $target_school_year, $matches) !== 1
            || (int) $matches[2] !== (int) $matches[1] + 1
        ) {
            throw new UnexpectedValueException('Das ausgewählte Zielschuljahr ist ungültig. Erwartet wird beispielsweise 2027/2028.');
        }

        $remapped = array();
        $warnings = array_values(array_map('strval', (array) ($parsed['warnings'] ?? array())));
        $source_courses = (array) ($parsed['courses'] ?? array());
        krsort($source_courses, SORT_STRING);
        foreach ($source_courses as $source_key => $item) {
            $slug = (string) ($item['course']['slug'] ?? '');
            $target_key = self::course_key($target_school_year, $slug);
            if (isset($remapped[$target_key])) {
                $warnings[] = 'AG „' . $source_key . '“ wurde übersprungen: Nach der Übertragung in ' . $target_school_year . ' wäre der Slug „' . $slug . '“ doppelt.';
                continue;
            }

            $item['course']['school_year'] = $target_school_year;
            foreach ($item['slots'] as &$slot) {
                $slot['school_year'] = $target_school_year;
            }
            unset($slot);
            $remapped[$target_key] = $item;
        }

        ksort($remapped, SORT_STRING);
        return array('courses' => $remapped, 'warnings' => $warnings);
    }

    /**
     * @param array<int,array<int,string|null>> $raw_rows Einschließlich Kopfzeile.
     * @return array<string,array{course:array<string,mixed>,slots:array<int,array<string,mixed>>}>
     */
    public static function parse(array $raw_rows): array
    {
        if (empty($raw_rows)) {
            throw new UnexpectedValueException('Die AG-CSV ist leer.');
        }

        $header = array_map(array(self::class, 'decode_cell'), array_shift($raw_rows));
        if ($header !== self::header()) {
            throw new UnexpectedValueException('Die Kopfzeile entspricht nicht dem erwarteten AG-CSV-Format.');
        }

        $courses = array();
        $pending_slots = array();
        foreach ($raw_rows as $index => $raw_row) {
            $line_number = $index + 2;
            if (!is_array($raw_row)) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' besitzt kein gültiges Datenformat.');
            }
            $row = array_map(array(self::class, 'decode_cell'), $raw_row);
            if (count(array_filter($row, static fn(string $value): bool => '' !== $value)) === 0) {
                continue;
            }
            if (count($row) !== count(self::header())) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' besitzt nicht die erwartete Spaltenzahl.');
            }
            if ($row[0] !== self::VERSION) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' verwendet eine unbekannte Format-Version.');
            }

            $school_year = self::school_year($row[3], $line_number);
            $slug = sanitize_title($row[5]);
            if ('' === $slug || $slug !== $row[5]) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen gültigen, normalisierten AG-Slug.');
            }
            $key = self::course_key($school_year, $slug);
            if ($row[2] !== $key) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält einen manipulierten AG-Schlüssel.');
            }

            if ('course' === $row[1]) {
                if (isset($courses[$key])) {
                    throw new UnexpectedValueException('Die CSV enthält den AG-Datensatz ' . $key . ' mehrfach.');
                }
                $title = sanitize_text_field($row[4]);
                if ('' === $title) {
                    throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen AG-Titel.');
                }
                $image_url = esc_url_raw($row[8]);
                if ('' !== $row[8] && '' === $image_url) {
                    throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keine gültige Bild-URL.');
                }

                $courses[$key] = array(
                    'course' => array(
                        'school_year'       => $school_year,
                        'title'             => $title,
                        'slug'              => $slug,
                        'short_description' => sanitize_textarea_field($row[6]),
                        'description'       => wp_kses_post($row[7]),
                        'image_url'         => $image_url,
                        'detail_page_path'  => self::page_path($row[9], $line_number),
                        'category'          => sanitize_text_field($row[10]),
                        'leader_name'       => sanitize_text_field($row[11]),
                        'allowed_grades'    => flz_ags_sanitize_allowed_grades(sanitize_text_field($row[12])),
                        'only_grade_7'      => self::boolean($row[13], $line_number, 'only_grade_7'),
                        'is_active'         => self::boolean($row[14], $line_number, 'is_active'),
                        'is_visible'        => self::boolean($row[15], $line_number, 'is_visible'),
                        'registration_open' => self::boolean($row[16], $line_number, 'registration_open'),
                        'sort_order'        => self::integer($row[17], $line_number, 'course_sort_order'),
                    ),
                    'slots' => array(),
                );
                continue;
            }

            if ('slot' !== $row[1]) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält einen unbekannten Datensatztyp.');
            }

            $weekday = self::nonnegative_integer($row[18], $line_number, 'slot_weekday');
            if ($weekday < 1 || $weekday > 7) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen gültigen Wochentag.');
            }
            $start_time = self::time($row[19], $line_number, 'slot_start_time');
            $end_time = self::time($row[20], $line_number, 'slot_end_time');
            if ($end_time <= $start_time) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keine Endzeit nach der Beginnzeit.');
            }
            $room = sanitize_text_field($row[21]);
            $slot_key = implode('|', array($weekday, $start_time, $end_time, strtolower($room)));
            if (isset($pending_slots[$key][$slot_key])) {
                throw new UnexpectedValueException('Die CSV enthält einen AG-Termin mehrfach.');
            }
            $pending_slots[$key][$slot_key] = array(
                'weekday'         => $weekday,
                'start_time'      => $start_time,
                'end_time'        => $end_time,
                'room'            => $room,
                'max_participants'=> self::nonnegative_integer($row[22], $line_number, 'slot_max_participants'),
                'is_active'       => self::boolean($row[23], $line_number, 'slot_is_active'),
                'sort_order'      => self::integer($row[24], $line_number, 'slot_sort_order'),
            );
        }

        if (empty($courses)) {
            throw new UnexpectedValueException('Die CSV enthält keinen AG-Datensatz.');
        }
        foreach ($pending_slots as $key => $slots) {
            if (!isset($courses[$key])) {
                throw new UnexpectedValueException('Ein Slot referenziert keinen zugehörigen AG-Datensatz.');
            }
            $courses[$key]['slots'] = array_values($slots);
        }

        ksort($courses, SORT_STRING);
        return $courses;
    }

    private static function decode_cell($value): string
    {
        $value = (string) ($value ?? '');
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }
        if (preg_match('/^\'[=+\-@]/', $value)) {
            $value = substr($value, 1);
        }
        return trim($value);
    }

    /**
     * @param array<int,string> $row
     * @param array<int,string> $warnings
     * @return array<int,string>
     */
    private static function repair_course_row(array $row, int $line_number, array &$warnings): array
    {
        if ('' === sanitize_text_field($row[4])) {
            $placeholder = '' !== sanitize_title($row[5]) ? sanitize_title($row[5]) : 'Zeile ' . $line_number;
            $row[4] = 'Ohne Titel (' . $placeholder . ')';
            $warnings[] = 'Zeile ' . $line_number . ': Fehlender AG-Titel wurde durch „' . $row[4] . '“ ersetzt.';
        }

        $normalized_slug = sanitize_title($row[5]);
        if ('' === $normalized_slug) {
            $normalized_slug = sanitize_title($row[4]);
        }
        if ('' === $normalized_slug) {
            $normalized_slug = 'ag-zeile-' . $line_number;
        }
        if ($row[5] !== $normalized_slug) {
            $row[5] = $normalized_slug;
            $warnings[] = 'Zeile ' . $line_number . ': Fehlender oder ungültiger AG-Slug wurde durch „' . $normalized_slug . '“ ersetzt.';
        }
        if (preg_match('/^\d{4}\/\d{4}$/', $row[3])) {
            $expected_key = self::course_key($row[3], $normalized_slug);
            if ($row[2] !== $expected_key) {
                $row[2] = $expected_key;
                $warnings[] = 'Zeile ' . $line_number . ': Der technische AG-Schlüssel wurde als „' . $expected_key . '“ rekonstruiert.';
            }
        }

        if ('' !== $row[8] && '' === esc_url_raw($row[8])) {
            $row[8] = '';
            $warnings[] = 'Zeile ' . $line_number . ': Ungültige Bild-URL wurde entfernt.';
        }

        if ('' !== $row[9]) {
            try {
                self::page_path($row[9], $line_number);
            } catch (UnexpectedValueException $error) {
                $row[9] = '';
                $warnings[] = 'Zeile ' . $line_number . ': Ungültiger Detailseitenpfad wurde entfernt.';
            }
        }

        $normalized_grades = flz_ags_sanitize_allowed_grades(sanitize_text_field($row[12]));
        if ($row[12] !== $normalized_grades) {
            $row[12] = $normalized_grades;
            $warnings[] = 'Zeile ' . $line_number . ': Ungültige Zielgruppenanteile wurden entfernt.';
        }

        foreach (array(13 => '0', 14 => '1', 15 => '1', 16 => '0') as $column => $default) {
            if ('0' !== $row[$column] && '1' !== $row[$column]) {
                $row[$column] = $default;
                $warnings[] = 'Zeile ' . $line_number . ': Ungültiger Ja/Nein-Wert in Spalte „' . self::header()[$column] . '“ wurde durch ' . $default . ' ersetzt.';
            }
        }
        if (!preg_match('/^-?\d+$/', $row[17])) {
            $row[17] = '0';
            $warnings[] = 'Zeile ' . $line_number . ': Ungültige AG-Sortierung wurde durch 0 ersetzt.';
        }

        return $row;
    }

    /**
     * @param array<int,string> $row
     * @param array<int,string> $warnings
     * @return array<int,string>
     */
    private static function repair_slot_row(array $row, int $line_number, array &$warnings): array
    {
        $normalized_slug = sanitize_title($row[5]);
        if ('' === $normalized_slug && str_contains($row[2], '|')) {
            $normalized_slug = sanitize_title((string) substr($row[2], strpos($row[2], '|') + 1));
        }
        if ('' !== $normalized_slug && $row[5] !== $normalized_slug) {
            $row[5] = $normalized_slug;
            $warnings[] = 'Zeile ' . $line_number . ': Der AG-Slug des Termins wurde als „' . $normalized_slug . '“ rekonstruiert.';
        }
        if ('' !== $normalized_slug && preg_match('/^\d{4}\/\d{4}$/', $row[3])) {
            $expected_key = self::course_key($row[3], $normalized_slug);
            if ($row[2] !== $expected_key) {
                $row[2] = $expected_key;
                $warnings[] = 'Zeile ' . $line_number . ': Der AG-Schlüssel des Termins wurde als „' . $expected_key . '“ rekonstruiert.';
            }
        }

        if (!preg_match('/^\d+$/', $row[22])) {
            $row[22] = '0';
            $warnings[] = 'Zeile ' . $line_number . ': Ungültige maximale Teilnehmerzahl wurde durch 0 ersetzt.';
        }
        if ('0' !== $row[23] && '1' !== $row[23]) {
            $row[23] = '1';
            $warnings[] = 'Zeile ' . $line_number . ': Ungültiger Terminstatus wurde auf aktiv gesetzt.';
        }
        if (!preg_match('/^-?\d+$/', $row[24])) {
            $row[24] = '0';
            $warnings[] = 'Zeile ' . $line_number . ': Ungültige Termin-Sortierung wurde durch 0 ersetzt.';
        }

        return $row;
    }

    private static function skipped_warning(int $line_number, UnexpectedValueException $error): string
    {
        $reason = preg_replace('/^CSV-Zeile \d+ /', '', $error->getMessage());
        return 'Zeile ' . $line_number . ' wurde übersprungen: ' . lcfirst((string) $reason);
    }

    private static function school_year(string $value, int $line_number): string
    {
        if (!preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches) || (int) $matches[2] !== (int) $matches[1] + 1) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält kein gültiges Schuljahr.');
        }
        return $value;
    }

    private static function page_path(string $value, int $line_number): string
    {
        if ('' === $value) {
            return '';
        }
        foreach (explode('/', $value) as $segment) {
            if ('' === $segment || sanitize_title($segment) !== $segment) {
                throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen gültigen Detailseitenpfad.');
            }
        }
        return $value;
    }

    private static function integer(string $value, int $line_number, string $field): int
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen gültigen Wert für ' . $field . '.');
        }
        return (int) $value;
    }

    private static function nonnegative_integer(string $value, int $line_number, string $field): int
    {
        $integer = self::integer($value, $line_number, $field);
        if ($integer < 0) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält einen negativen Wert für ' . $field . '.');
        }
        return $integer;
    }

    private static function boolean(string $value, int $line_number, string $field): int
    {
        if ('0' !== $value && '1' !== $value) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keinen booleschen Wert für ' . $field . '.');
        }
        return (int) $value;
    }

    private static function time(string $value, int $line_number, string $field): string
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) !== 1) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keine gültige Zeit für ' . $field . '.');
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) ? (int) $matches[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new UnexpectedValueException('CSV-Zeile ' . $line_number . ' enthält keine gültige Zeit für ' . $field . '.');
        }
        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }
}
