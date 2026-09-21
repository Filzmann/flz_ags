<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Diagnosedaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Versionierter, portabler Backupvertrag für AG-Anmeldungen.
 */
final class FLZ_AGS_Registration_CSV
{
    public const VERSION = 'flz_ags_registrations_v1';

    /** @return array<int,string> */
    public static function header(): array
    {
        return array(
            'format_version',
            'school_year',
            'status',
            'class_name',
            'grade_key',
            'student_last_name',
            'student_first_name',
            'student_email',
            'consent_privacy',
            'course_slug',
            'course_title',
            'slot_weekday',
            'slot_start_time',
            'slot_end_time',
            'slot_room',
            'withdrawn_at',
            'withdrawn_reason',
            'created_at',
            'updated_at',
        );
    }

    /**
     * @param array<int,object> $registrations
     * @return array<int,array<int,string|int>>
     */
    public static function export_rows(array $registrations): array
    {
        $rows = array();
        foreach ($registrations as $registration) {
            if (!is_object($registration)) {
                throw new UnexpectedValueException('Ein Anmeldungsdatensatz besitzt kein exportierbares Format.');
            }
            $rows[] = array(
                self::VERSION,
                (string) ($registration->school_year ?? ''),
                (string) ($registration->status ?? ''),
                (string) ($registration->class_name ?? ''),
                (string) ($registration->grade_key ?? ''),
                (string) ($registration->student_last_name ?? ''),
                (string) ($registration->student_first_name ?? ''),
                (string) ($registration->student_email ?? ''),
                (int) ($registration->consent_privacy ?? 0),
                (string) ($registration->slug ?? ''),
                (string) ($registration->title ?? ''),
                (int) ($registration->weekday ?? 0),
                (string) ($registration->start_time ?? ''),
                (string) ($registration->end_time ?? ''),
                (string) ($registration->room ?? ''),
                (string) ($registration->withdrawn_at ?? ''),
                (string) ($registration->withdrawn_reason ?? ''),
                (string) ($registration->created_at ?? ''),
                (string) ($registration->updated_at ?? ''),
            );
        }
        return $rows;
    }

    /**
     * Liest brauchbare Datensätze einzeln. Fehlerhafte Pflichtbezüge werden
     * ausgelassen; ungefährliche Inhaltsfehler erhalten sichere Platzhalter.
     *
     * @param array<int,array<int,string|null>> $raw_rows Einschließlich Kopfzeile.
     * @return array{registrations:array<int,array<string,mixed>>,warnings:array<int,string>}
     */
    public static function parse_tolerant(array $raw_rows): array
    {
        if (empty($raw_rows)) {
            throw new UnexpectedValueException('Die Anmeldungs-CSV ist leer.');
        }
        $header = array_map(array(self::class, 'decode_cell'), array_shift($raw_rows));
        if ($header !== self::header()) {
            throw new UnexpectedValueException('Die Kopfzeile entspricht nicht dem erwarteten Anmeldungs-Backup. Bitte eine zuvor heruntergeladene Anmeldungs-CSV verwenden.');
        }

        $registrations = array();
        $warnings = array();
        $column_count = count(self::header());
        foreach ($raw_rows as $index => $raw_row) {
            $line = $index + 2;
            if (!is_array($raw_row)) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: kein lesbarer CSV-Datensatz.';
                continue;
            }
            $row = array_map(array(self::class, 'decode_cell'), $raw_row);
            if (count(array_filter($row, static fn(string $value): bool => '' !== $value)) === 0) {
                continue;
            }
            if (count($row) > $column_count) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: zu viele Spalten.';
                continue;
            }
            if (count($row) < $column_count) {
                $row = array_pad($row, $column_count, '');
                $warnings[] = 'Zeile ' . $line . ' hatte fehlende Spalten; leere Standardwerte wurden ergänzt.';
            }
            if (self::VERSION !== $row[0]) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: unbekannte Format-Version „' . $row[0] . '“.';
                continue;
            }
            if (!self::valid_school_year($row[1])) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: Das Schuljahr fehlt oder ist ungültig.';
                continue;
            }
            if ('' === $row[9]) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: Der portable AG-Slug fehlt.';
                continue;
            }
            $weekday = (int) $row[11];
            $start_time = self::time($row[12]);
            $end_time = self::time($row[13]);
            if ($weekday < 1 || $weekday > 7 || '' === $start_time || '' === $end_time) {
                $warnings[] = 'Zeile ' . $line . ' wurde übersprungen: Der zugehörige Termin ist nicht eindeutig angegeben.';
                continue;
            }

            $statuses = array('active', 'withdrawn', 'cancelled', 'waitlist');
            $status = in_array($row[2], $statuses, true) ? $row[2] : 'cancelled';
            if ($status !== $row[2]) {
                $warnings[] = 'Zeile ' . $line . ': Unbekannter Status wurde sicherheitshalber durch „cancelled“ ersetzt.';
            }
            $class_name = '' !== $row[3] ? $row[3] : 'Unbekannt';
            if ($class_name !== $row[3]) {
                $warnings[] = 'Zeile ' . $line . ': Fehlende Klasse wurde durch „Unbekannt“ ersetzt.';
            }
            $last_name = '' !== $row[5] ? $row[5] : 'Ohne Nachname';
            $first_name = '' !== $row[6] ? $row[6] : 'Ohne Vorname';
            if ($last_name !== $row[5]) {
                $warnings[] = 'Zeile ' . $line . ': Fehlender Nachname erhielt einen Platzhalter.';
            }
            if ($first_name !== $row[6]) {
                $warnings[] = 'Zeile ' . $line . ': Fehlender Vorname erhielt einen Platzhalter.';
            }
            $email = filter_var($row[7], FILTER_VALIDATE_EMAIL) ? $row[7] : '';
            if ('' !== $row[7] && '' === $email) {
                $warnings[] = 'Zeile ' . $line . ': Ungültige optionale E-Mail-Adresse wurde geleert.';
            }
            $consent = in_array(strtolower($row[8]), array('1', 'true', 'ja', 'yes'), true) ? 1 : 0;
            if (!in_array(strtolower($row[8]), array('', '0', '1', 'false', 'true', 'nein', 'ja', 'no', 'yes'), true)) {
                $warnings[] = 'Zeile ' . $line . ': Unklare Datenschutz-Einwilligung wurde als nicht erteilt übernommen.';
            }
            $grade_key = preg_match('/^(7|8|9|10|11|12)$/', $row[4]) === 1 ? $row[4] : '';
            if ('' === $grade_key && preg_match('/^(7|8|9|10|11|12)(?:\D|$)/', $class_name, $grade_match) === 1) {
                $grade_key = $grade_match[1];
            }
            $withdrawn_at = self::datetime($row[15]);
            $created_at = self::datetime($row[17]);
            $updated_at = self::datetime($row[18]);
            if ('' !== $row[15] && '' === $withdrawn_at) {
                $warnings[] = 'Zeile ' . $line . ': Ungültiges Widerrufsdatum wurde geleert.';
            }
            if ('' === $created_at) {
                $warnings[] = 'Zeile ' . $line . ': Fehlendes oder ungültiges Anmeldedatum wird beim Import durch den aktuellen Zeitpunkt ersetzt.';
            }
            if ('' !== $row[18] && '' === $updated_at) {
                $warnings[] = 'Zeile ' . $line . ': Ungültiges Änderungsdatum wird beim Import durch den aktuellen Zeitpunkt ersetzt.';
            }

            $registrations[] = array(
                'source_line' => $line,
                'school_year' => $row[1],
                'status' => $status,
                'class_name' => $class_name,
                'grade_key' => $grade_key,
                'student_last_name' => $last_name,
                'student_first_name' => $first_name,
                'student_email' => $email,
                'consent_privacy' => $consent,
                'course_slug' => $row[9],
                'course_title' => $row[10],
                'slot_weekday' => $weekday,
                'slot_start_time' => $start_time,
                'slot_end_time' => $end_time,
                'slot_room' => $row[14],
                'withdrawn_at' => $withdrawn_at,
                'withdrawn_reason' => $row[16],
                'created_at' => $created_at,
                'updated_at' => $updated_at,
            );
        }

        return array('registrations' => $registrations, 'warnings' => $warnings);
    }

    private static function decode_cell($value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }

    private static function valid_school_year(string $value): bool
    {
        return preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches) === 1
            && (int) $matches[2] === (int) $matches[1] + 1;
    }

    private static function time(string $value): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) {
            return '';
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private static function datetime(string $value): string
    {
        if ('' === $value) {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === $value ? $value : '';
    }
}
