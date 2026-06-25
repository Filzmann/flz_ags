<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObjectsException;

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
    public ?string $guardian_email;
    public ?string $status;
    public ?string $withdrawn_at;
    public ?string $withdrawn_reason;
    public ?int $consent_privacy;
    public ?string $created_at;
    public ?string $updated_at;

    // Optionale Anzeige- und Exportfelder aus den JOIN-Abfragen.
    public ?string $title;
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
        $this->guardian_email = $data['guardian_email'] ?? null;
        $this->status = $data['status'] ?? 'active';
        $this->withdrawn_at = $data['withdrawn_at'] ?? null;
        $this->withdrawn_reason = $data['withdrawn_reason'] ?? null;
        $this->consent_privacy = isset($data['consent_privacy']) ? (int) $data['consent_privacy'] : 0;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        $this->title = $data['title'] ?? null;
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
        $sql = 'SELECT r.*, c.title, s.weekday, s.start_time, s.end_time, s.room '
            . 'FROM ' . static::table_name() . ' r '
            . 'INNER JOIN ' . FLZ_AGS_Course::table_name() . ' c ON c.id = r.course_id '
            . 'INNER JOIN ' . FLZ_AGS_Slot::table_name() . ' s ON s.id = r.slot_id '
            . 'WHERE r.school_year = %s AND r.status = %s ORDER BY ' . $order_sql;

        return static::query_models(
            $sql,
            array($school_year, $status),
            $export_order ? 'Laden der AG-Anmeldungen für den CSV-Export' : 'Laden der AG-Anmeldungen'
        );
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
            guardian_email varchar(190) NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            withdrawn_at datetime NULL,
            withdrawn_reason text NULL,
            consent_privacy tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY slot_id (slot_id),
            KEY course_id (course_id),
            KEY school_year (school_year),
            KEY class_name (class_name),
            KEY status (status)
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

        return array(
            'course_id' => $this->course_id,
            'slot_id' => $this->slot_id,
            'school_year' => $this->school_year,
            'class_name' => $this->class_name,
            'grade_key' => $this->grade_key,
            'student_first_name' => $this->student_first_name,
            'student_last_name' => $this->student_last_name,
            'guardian_email' => $this->guardian_email,
            'status' => $this->status,
            'withdrawn_at' => $this->withdrawn_at,
            'withdrawn_reason' => $this->withdrawn_reason,
            'consent_privacy' => $this->consent_privacy,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        );
    }
}
