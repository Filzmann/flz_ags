<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObjectsException;

/**
 * Persistentes Modell eines wöchentlichen AG-Termins.
 */
class FLZ_AGS_Slot extends FLZ_AGS_Model
{
    public ?int $course_id;
    public ?string $school_year;
    public ?int $weekday;
    public ?string $start_time;
    public ?string $end_time;
    public ?string $room;
    public ?int $max_participants;
    public ?int $is_active;
    public ?int $sort_order;
    public ?string $created_at;
    public ?string $updated_at;

    // Optionale AG-Felder aus den öffentlichen JOIN-Abfragen.
    public ?string $title;
    public ?string $short_description;
    public ?string $description;
    public ?string $image_url;
    public ?string $info_url;
    public ?string $category;
    public ?string $leader_name;
    public ?string $allowed_grades;
    public ?int $only_grade_7;
    public ?int $registration_open;
    public ?int $course_active;
    public ?int $course_visible;

    public function __construct(array $data = array())
    {
        parent::__construct($data['id'] ?? null);
        $this->course_id = isset($data['course_id']) ? (int) $data['course_id'] : null;
        $this->school_year = $data['school_year'] ?? null;
        $this->weekday = isset($data['weekday']) ? (int) $data['weekday'] : null;
        $this->start_time = $data['start_time'] ?? null;
        $this->end_time = $data['end_time'] ?? null;
        $this->room = $data['room'] ?? null;
        $this->max_participants = isset($data['max_participants']) ? (int) $data['max_participants'] : 0;
        $this->is_active = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $this->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->short_description = $data['short_description'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->image_url = $data['image_url'] ?? null;
        $this->info_url = $data['info_url'] ?? null;
        $this->category = $data['category'] ?? null;
        $this->leader_name = $data['leader_name'] ?? null;
        $this->allowed_grades = $data['allowed_grades'] ?? null;
        $this->only_grade_7 = isset($data['only_grade_7']) ? (int) $data['only_grade_7'] : null;
        $this->registration_open = isset($data['registration_open']) ? (int) $data['registration_open'] : null;
        $this->course_active = isset($data['course_active']) ? (int) $data['course_active'] : null;
        $this->course_visible = isset($data['course_visible']) ? (int) $data['course_visible'] : null;
    }

    public static function find_for_course(int $course_id, bool $include_inactive = true): array
    {
        $active_sql = $include_inactive ? '' : ' AND is_active = 1';
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE course_id = %d' . $active_sql
            . ' ORDER BY sort_order ASC, weekday ASC, start_time ASC';

        return static::query_models($sql, array($course_id), 'Laden der Termine einer AG');
    }

    public static function find_with_course(int $slot_id, bool $for_update = false): ?self
    {
        $sql = 'SELECT s.*, c.title, c.allowed_grades, c.only_grade_7, c.registration_open, '
            . 'c.is_active AS course_active, c.is_visible AS course_visible '
            . 'FROM ' . static::table_name() . ' s '
            . 'INNER JOIN ' . FLZ_AGS_Course::table_name() . ' c ON c.id = s.course_id '
            . 'WHERE s.id = %d'
            . ($for_update ? ' FOR UPDATE' : '');
        $models = static::query_models($sql, array($slot_id), 'Laden eines AG-Termins mit AG-Daten');

        return $models[0] ?? null;
    }

    public static function find_public_for_school_year(string $school_year): array
    {
        $sql = 'SELECT s.*, c.title, c.short_description, c.description, c.image_url, c.info_url, '
            . 'c.category, c.leader_name, c.allowed_grades, c.only_grade_7, c.registration_open '
            . 'FROM ' . static::table_name() . ' s '
            . 'INNER JOIN ' . FLZ_AGS_Course::table_name() . ' c ON c.id = s.course_id '
            . 'WHERE s.school_year = %s AND s.is_active = 1 AND c.is_active = 1 AND c.is_visible = 1 '
            . 'ORDER BY c.sort_order ASC, c.title ASC, s.weekday ASC, s.start_time ASC';

        return static::query_models($sql, array($school_year), 'Laden der veröffentlichten AG-Termine');
    }

    protected static function get_table_schema(): string
    {
        return "(
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            school_year varchar(20) NOT NULL,
            weekday tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            room varchar(190) NULL,
            max_participants int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY school_year (school_year),
            KEY weekday (weekday),
            KEY active (is_active)
        )";
    }

    protected function prepareDataForSaving(): array
    {
        if (
            $this->course_id === null || $this->course_id <= 0
            || trim((string) $this->school_year) === ''
            || $this->weekday === null || $this->weekday < 1 || $this->weekday > 7
            || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', (string) $this->start_time)
            || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', (string) $this->end_time)
        ) {
            throw FlzWpdbObjectsException::invalid_model_state(
                static::class,
                'AG, Schuljahr, Wochentag sowie Beginn und Ende müssen gültig gesetzt sein.'
            );
        }

        return array(
            'course_id' => $this->course_id,
            'school_year' => $this->school_year,
            'weekday' => $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'room' => $this->room,
            'max_participants' => $this->max_participants,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        );
    }
}
