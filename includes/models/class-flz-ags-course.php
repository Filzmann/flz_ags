<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObjectsException;

/**
 * Persistentes Modell einer Arbeitsgemeinschaft.
 */
class FLZ_AGS_Course extends FLZ_AGS_Model
{
    public ?string $school_year;
    public ?string $title;
    public ?string $slug;
    public ?string $short_description;
    public ?string $description;
    public ?string $image_url;
    public ?int $detail_page_id;
    public ?string $category;
    public ?string $leader_name;
    public ?string $allowed_grades;
    public ?int $only_grade_7;
    public ?int $is_active;
    public ?int $is_visible;
    public ?int $registration_open;
    public ?int $sort_order;
    public ?string $created_at;
    public ?string $updated_at;
    public array $slots = array();

    public function __construct(array $data = array())
    {
        parent::__construct($data['id'] ?? null);
        $this->school_year = $data['school_year'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->slug = $data['slug'] ?? null;
        $this->short_description = $data['short_description'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->image_url = $data['image_url'] ?? null;
        $this->detail_page_id = isset($data['detail_page_id']) ? (int) $data['detail_page_id'] : 0;
        $this->category = $data['category'] ?? null;
        $this->leader_name = $data['leader_name'] ?? null;
        $this->allowed_grades = $data['allowed_grades'] ?? '';
        $this->only_grade_7 = isset($data['only_grade_7']) ? (int) $data['only_grade_7'] : 0;
        $this->is_active = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $this->is_visible = isset($data['is_visible']) ? (int) $data['is_visible'] : 1;
        $this->registration_open = isset($data['registration_open']) ? (int) $data['registration_open'] : 1;
        $this->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public static function find_for_school_year(string $school_year, bool $public_only = false): array
    {
        $visibility_sql = $public_only ? ' AND is_active = 1 AND is_visible = 1' : '';
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE school_year = %s' . $visibility_sql
            . ' ORDER BY sort_order ASC, title ASC';

        return static::query_models($sql, array($school_year), 'Laden der AGs für ein Schuljahr');
    }

    public static function find_public_by_detail_page_id(int $detail_page_id, string $school_year): ?self
    {
        $sql = 'SELECT * FROM ' . static::table_name()
            . ' WHERE detail_page_id = %d AND school_year = %s AND is_active = 1 AND is_visible = 1'
            . ' ORDER BY sort_order ASC, title ASC LIMIT 1';
        $models = static::query_models($sql, array($detail_page_id, $school_year), 'Laden einer AG zur Detailseite');

        return $models[0] ?? null;
    }

    protected static function get_table_schema(): string
    {
        return "(
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            school_year varchar(20) NOT NULL,
            title varchar(190) NOT NULL,
            slug varchar(200) NOT NULL,
            short_description text NULL,
            description longtext NULL,
            image_url varchar(500) NULL,
            detail_page_id bigint(20) unsigned NULL,
            category varchar(190) NULL,
            leader_name varchar(190) NULL,
            allowed_grades varchar(100) NOT NULL DEFAULT '',
            only_grade_7 tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_visible tinyint(1) NOT NULL DEFAULT 1,
            registration_open tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY school_year (school_year),
            KEY slug (slug),
            KEY detail_page_id (detail_page_id),
            KEY active_visible (is_active, is_visible)
        )";
    }

    protected function prepareDataForSaving(): array
    {
        if (trim((string) $this->school_year) === '' || trim((string) $this->title) === '') {
            throw FlzWpdbObjectsException::invalid_model_state(
                static::class,
                'Schuljahr und Titel müssen vor dem Speichern gesetzt sein.'
            );
        }

        return array(
            'school_year' => $this->school_year,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'detail_page_id' => $this->detail_page_id,
            'category' => $this->category,
            'leader_name' => $this->leader_name,
            'allowed_grades' => $this->allowed_grades,
            'only_grade_7' => $this->only_grade_7,
            'is_active' => $this->is_active,
            'is_visible' => $this->is_visible,
            'registration_open' => $this->registration_open,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        );
    }
}
