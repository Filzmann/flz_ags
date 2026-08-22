<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObjectsException;

/**
 * Stabiler, nicht rückrechenbarer Schlüssel für eine Schüler*in pro Schuljahr.
 */
function flz_ags_registration_student_key(
    string $school_year,
    string $class_name,
    string $first_name,
    string $last_name
): string {
    $values = array($school_year, $class_name, $first_name, $last_name);
    $values = array_map(
        static function (string $value): string {
            $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        },
        $values
    );

    return hash('sha256', implode('|', $values));
}

/**
 * Persistentes Modell einer AG-Anmeldung.
 */
class FLZ_AGS_Registration extends FLZ_AGS_Model
{
    public ?int $course_id;
    public ?int $slot_id;
    public ?string $school_year;
    public ?string $class_name;
    public ?string $grade_key;
    public ?string $student_first_name;
    public ?string $student_last_name;
    public ?string $student_email;
    public ?string $status;
    public ?string $withdrawn_at;
    public ?string $withdrawn_reason;
    public ?int $consent_privacy;
    public ?string $created_at;
    public ?string $updated_at;
    public ?string $student_key;
    public ?string $active_student_key;

    // Optionale Anzeige- und Exportfelder aus den JOIN-Abfragen.
    public ?string $title;
    public ?string $slug;
    public ?int $weekday;
    public ?string $start_time;
    public ?string $end_time;
    public ?string $room;

    public function __construct(array $data = array())
    {
        parent::__construct($data['id'] ?? null);
        $this->course_id = isset($data['course_id']) ? (int) $data['course_id'] : null;
        $this->slot_id = isset($data['slot_id']) ? (int) $data['slot_id'] : null;
        $this->school_year = $data['school_year'] ?? null;
        $this->class_name = $data['class_name'] ?? null;
        $this->grade_key = $data['grade_key'] ?? null;
        $this->student_first_name = $data['student_first_name'] ?? null;
        $this->student_last_name = $data['student_last_name'] ?? null;
        $this->student_email = $data['student_email'] ?? null;
        $this->status = $data['status'] ?? 'active';
        $this->withdrawn_at = $data['withdrawn_at'] ?? null;
        $this->withdrawn_reason = $data['withdrawn_reason'] ?? null;
        $this->consent_privacy = isset($data['consent_privacy']) ? (int) $data['consent_privacy'] : 0;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        $this->student_key = $data['student_key'] ?? null;
        $this->active_student_key = $data['active_student_key'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->slug = $data['slug'] ?? null;
        $this->weekday = isset($data['weekday']) ? (int) $data['weekday'] : null;
        $this->start_time = $data['start_time'] ?? null;
        $this->end_time = $data['end_time'] ?? null;
        $this->room = $data['room'] ?? null;
    }

    public static function find_for_admin(string $school_year, string $status, bool $export_order = false): array
    {
        $order_sql = $export_order
            ? 'c.title ASC, s.weekday ASC, r.class_name ASC, r.student_last_name ASC'
            : 'r.class_name ASC, r.student_last_name ASC, r.student_first_name ASC';
        $status_sql = 'all' === $status ? '' : ' AND r.status = %s';
        $values = 'all' === $status ? array($school_year) : array($school_year, $status);
        $sql = 'SELECT r.*, c.title, c.slug, s.weekday, s.start_time, s.end_time, s.room '
            . 'FROM ' . static::table_name() . ' r '
            . 'INNER JOIN ' . FLZ_AGS_Course::table_name() . ' c ON c.id = r.course_id '
            . 'INNER JOIN ' . FLZ_AGS_Slot::table_name() . ' s ON s.id = r.slot_id '
            . 'WHERE r.school_year = %s' . $status_sql . ' ORDER BY ' . $order_sql;

        return static::query_models(
            $sql,
            $values,
            $export_order ? 'Laden der AG-Anmeldungen für den CSV-Export' : 'Laden der AG-Anmeldungen'
        );
    }

    public static function find_backup_match(array $data): ?self
    {
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE school_year = %s AND course_id = %d AND slot_id = %d AND class_name = %s'
            . ' AND student_first_name = %s AND student_last_name = %s AND created_at = %s'
            . ' ORDER BY id ASC LIMIT 2';
        $models = static::query_models($sql, array(
            $data['school_year'],
            $data['course_id'],
            $data['slot_id'],
            $data['class_name'],
            $data['student_first_name'],
            $data['student_last_name'],
            $data['created_at'],
        ), 'Suchen einer bereits importierten AG-Anmeldung');
        if (count($models) > 1) {
            throw new UnexpectedValueException('Die Anmeldung ist anhand ihrer Backupdaten nicht eindeutig.');
        }
        return $models[0] ?? null;
    }

    public static function find_active_for_student(string $school_year, string $class_name, string $first_name, string $last_name): ?self
    {
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE school_year = %s AND class_name = %s AND student_first_name = %s'
            . ' AND student_last_name = %s AND status = \'active\' ORDER BY id ASC LIMIT 1';
        $models = static::query_models(
            $sql,
            array($school_year, $class_name, $first_name, $last_name),
            'Prüfen aktiver Anmeldungen während des Backup-Imports'
        );
        return $models[0] ?? null;
    }

    /** @return array<int,self> */
    public static function find_created_before(string $cutoff): array
    {
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE created_at < %s ORDER BY created_at ASC, id ASC';
        return static::query_models($sql, array($cutoff), 'Laden abgelaufener AG-Anmeldungen');
    }

    protected static function get_table_schema(): string
    {
        return "(
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            slot_id bigint(20) unsigned NOT NULL,
            school_year varchar(20) NOT NULL,
            class_name varchar(50) NOT NULL,
            grade_key varchar(20) NOT NULL,
            student_first_name varchar(120) NOT NULL,
            student_last_name varchar(120) NOT NULL,
            student_email varchar(190) NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            withdrawn_at datetime NULL,
            withdrawn_reason text NULL,
            consent_privacy tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            student_key char(64) NOT NULL,
            active_student_key char(64) NULL,
            PRIMARY KEY  (id),
            KEY slot_id (slot_id),
            KEY course_id (course_id),
            KEY school_year (school_year),
            KEY class_name (class_name),
            KEY status (status),
            UNIQUE KEY active_student_key (active_student_key)
        )";
    }

    protected function prepareDataForSaving(): array
    {
        if (
            $this->course_id === null || $this->course_id <= 0
            || $this->slot_id === null || $this->slot_id <= 0
            || trim((string) $this->school_year) === ''
            || trim((string) $this->class_name) === ''
            || trim((string) $this->student_first_name) === ''
            || trim((string) $this->student_last_name) === ''
            || !array_key_exists((string) $this->status, flz_ags_status_labels())
        ) {
            throw FlzWpdbObjectsException::invalid_model_state(
                static::class,
                'AG, Termin, Schuljahr, Klasse, Name und Status müssen gültig gesetzt sein.'
            );
        }

        $this->student_key = flz_ags_registration_student_key(
            (string) $this->school_year,
            (string) $this->class_name,
            (string) $this->student_first_name,
            (string) $this->student_last_name
        );
        $this->active_student_key = 'active' === $this->status ? $this->student_key : null;

        return array(
            'course_id' => $this->course_id,
            'slot_id' => $this->slot_id,
            'school_year' => $this->school_year,
            'class_name' => $this->class_name,
            'grade_key' => $this->grade_key,
            'student_first_name' => $this->student_first_name,
            'student_last_name' => $this->student_last_name,
            'student_email' => $this->student_email,
            'status' => $this->status,
            'withdrawn_at' => $this->withdrawn_at,
            'withdrawn_reason' => $this->withdrawn_reason,
            'consent_privacy' => $this->consent_privacy,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'student_key' => $this->student_key,
            'active_student_key' => $this->active_student_key,
        );
    }

    /** @return array<int,self> */
    public static function find_by_email(string $email): array
    {
        return static::get_all_by(array('student_email' => sanitize_email($email)));
    }
}
