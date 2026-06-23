<?php

defined('ABSPATH') || exit;

class FLZ_AGS_Database
{
    public static function activate(): void
    {
        self::create_tables();
        add_option('flz_ags_current_school_year', flz_ags_default_school_year());
        add_option('flz_ags_classes', flz_ags_default_classes());
        update_option('flz_ags_db_version', FLZ_AGS_VERSION, false);
    }

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option('flz_ags_db_version', '');
        if ($installed !== FLZ_AGS_VERSION) {
            self::create_tables();
            if (get_option('flz_ags_current_school_year', '') === '') {
                add_option('flz_ags_current_school_year', flz_ags_default_school_year());
            }
            $classes = get_option('flz_ags_classes', array());
            if (!is_array($classes) || empty($classes)) {
                add_option('flz_ags_classes', flz_ags_default_classes());
            }
            update_option('flz_ags_db_version', FLZ_AGS_VERSION, false);
        }
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $courses = flz_ags_table('courses');
        $slots = flz_ags_table('slots');
        $registrations = flz_ags_table('registrations');

        $sql_courses = "CREATE TABLE {$courses} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            school_year varchar(20) NOT NULL,
            title varchar(190) NOT NULL,
            slug varchar(200) NOT NULL,
            short_description text NULL,
            description longtext NULL,
            image_url varchar(500) NULL,
            info_url varchar(500) NULL,
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
            KEY active_visible (is_active, is_visible)
        ) {$charset_collate};";

        $sql_slots = "CREATE TABLE {$slots} (
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
        ) {$charset_collate};";

        $sql_registrations = "CREATE TABLE {$registrations} (
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
        ) {$charset_collate};";

        dbDelta($sql_courses);
        dbDelta($sql_slots);
        dbDelta($sql_registrations);
    }
}
