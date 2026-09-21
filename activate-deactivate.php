<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Erstellt die modellbasierten AG-Tabellen.
 *
 * Die Tabellenstruktur liegt ausschließlich in den Modellklassen. Diese Datei
 * koordiniert nur Aktivierung und Versionswechsel des Plugins.
 */
function flz_ags_create_model_tables(): void
{
    FLZ_AGS_Course::create_table();
    FLZ_AGS_Slot::create_table();
    FLZ_AGS_Registration::create_table();
}

/**
 * Aktiviert das AG-Plugin und legt fehlende Standardoptionen an.
 */
function flz_ags_activate(): void
{
    flz_ags_maybe_upgrade();
    flz_ags_create_leader_role();
    add_option('flz_ags_current_school_year', flz_ags_default_school_year());
    add_option('flz_ags_classes', flz_ags_default_classes());
    add_option('flz_ags_parent_page_id', flz_ags_detect_detail_parent_page_id());
    add_option('flz_ags_registration_retention_enabled', 0);
    add_option('flz_ags_registration_retention_months', 24);
    if ((bool) get_option('flz_ags_registration_retention_enabled', 0)) {
        flz_ags_schedule_registration_cleanup();
    }
}

/**
 * Führt kleine Upgrade-Schritte ohne eigene SQL-Schicht aus.
 */
function flz_ags_maybe_upgrade(): void
{
    $installed = (string) get_option('flz_ags_db_version', '');
    if (version_compare($installed, FLZ_AGS_DB_VERSION, '>=')) {
        return;
    }

    if (flz_ags_registration_table_exists()) {
        if (version_compare($installed, '2.0.0', '<')) {
            flz_ags_upgrade_registration_identity_v2();
        }
    }
    flz_ags_create_model_tables();

    if (version_compare($installed, '2.1.0', '<')) {
        flz_ags_upgrade_leader_assignment_and_multiple_registrations_v21();
    }

    if (get_option('flz_ags_current_school_year', '') === '') {
        add_option('flz_ags_current_school_year', flz_ags_default_school_year());
    }

    $classes = get_option('flz_ags_classes', array());
    $classes = is_array($classes) ? flz_ags_normalize_classes($classes) : array();
    if (flz_ags_classes_are_grade_only($classes)) {
        $classes = flz_ags_default_classes();
    }
    update_option('flz_ags_classes', !empty($classes) ? $classes : flz_ags_default_classes(), false);

    if (get_option('flz_ags_parent_page_id', null) === null) {
        add_option('flz_ags_parent_page_id', flz_ags_detect_detail_parent_page_id());
    }

    add_option('flz_ags_registration_retention_enabled', 0);
    add_option('flz_ags_registration_retention_months', 24);
    add_option('flz_ags_multiple_registrations_enabled', 0);
    add_option('flz_ags_registration_form_notice', 'Die AG-Anmeldung gilt nur für ein Schulhalbjahr.');
    flz_ags_create_leader_role();
    if ((bool) get_option('flz_ags_registration_retention_enabled', 0)) {
        flz_ags_schedule_registration_cleanup();
    }

    // Frühere lokale Fallback-Mails enthielten Formularwerte dauerhaft.
    // Seit 0.6.0 bleibt nur noch ein kurzlebiger, datensparsamer Transient.
    delete_option('flz_ags_mock_confirmation_mails');

    update_option('flz_ags_db_version', FLZ_AGS_DB_VERSION, false);
}

function flz_ags_create_leader_role(): void
{
    $role = get_role(flz_ags_leader_role_slug());
    if (!$role instanceof WP_Role) {
        add_role(
            flz_ags_leader_role_slug(),
            'AG-Leiter',
            array('read' => true, flz_ags_view_assigned_courses_capability() => true)
        );
        return;
    }
    $role->add_cap('read');
    $role->add_cap(flz_ags_view_assigned_courses_capability());
}

function flz_ags_upgrade_leader_assignment_and_multiple_registrations_v21(): void
{
    global $wpdb;

    $course_table = $wpdb->prefix . 'flz_ags_courses';
    $registration_table = $wpdb->prefix . 'flz_ags_registrations';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Feste Plugin-Tabellennamen mit WordPress-Präfix.
    if (null === $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $course_table LIKE %s", 'leader_user_id'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $course_table ADD leader_user_id bigint(20) unsigned NULL AFTER leader_name")) {
            throw new RuntimeException('Der AG-Tabelle konnte keine WordPress-Leitungszuordnung hinzugefügt werden.');
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Indexname wird gebunden.
    if (null === $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM $course_table WHERE Key_name = %s", 'leader_user_id'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $course_table ADD KEY leader_user_id (leader_user_id)")) {
            throw new RuntimeException('Der Index für die AG-Leitungszuordnung konnte nicht eingerichtet werden.');
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Indexname wird gebunden.
    if (null !== $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM $registration_table WHERE Key_name = %s", 'active_student_key'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $registration_table DROP INDEX active_student_key")) {
            throw new RuntimeException('Die bisherige globale AG-Anmeldungseindeutigkeit konnte nicht umgestellt werden.');
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Indexname wird gebunden.
    if (null === $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM $registration_table WHERE Key_name = %s", 'active_student_course_key'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $registration_table ADD UNIQUE KEY active_student_course_key (active_student_key, course_id)")) {
            throw new RuntimeException('Die Eindeutigkeit je AG konnte nicht eingerichtet werden.');
        }
    }
}

function flz_ags_registration_table_exists(): bool
{
    global $wpdb;

    $table = $wpdb->prefix . 'flz_ags_registrations';
    return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
}

/**
 * Ergänzt die globale aktive Schüler-Eindeutigkeit additiv und idempotent.
 */
function flz_ags_upgrade_registration_identity_v2(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'flz_ags_registrations';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Nutzwert wird gebunden.
    if (null === $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'student_key'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $table ADD student_key char(64) NULL AFTER updated_at")) {
            throw new RuntimeException('Der AG-Anmeldetabelle konnte kein Schüler-Schlüssel hinzugefügt werden.');
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Nutzwert wird gebunden.
    if (null === $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'active_student_key'))) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $table ADD active_student_key char(64) NULL AFTER student_key")) {
            throw new RuntimeException('Der AG-Anmeldetabelle konnte kein Aktivschlüssel hinzugefügt werden.');
        }
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
    $rows = $wpdb->get_results("SELECT id, school_year, class_name, student_first_name, student_last_name, status FROM $table ORDER BY id ASC");
    if (!is_array($rows)) {
        throw new RuntimeException('Die AG-Anmeldungen konnten für das Schema-Upgrade nicht gelesen werden.');
    }

    $active = array();
    foreach ($rows as $row) {
        $key = flz_ags_registration_student_key(
            (string) $row->school_year,
            (string) $row->class_name,
            (string) $row->student_first_name,
            (string) $row->student_last_name
        );
        $active_key = 'active' === (string) $row->status ? $key : null;
        if (null !== $active_key && isset($active[$active_key])) {
            throw new RuntimeException('Der AG-Bestand enthält mehrere aktive Anmeldungen derselben Schüler*in im selben Schuljahr.');
        }
        if (null !== $active_key) {
            $active[$active_key] = true;
        }
        if (false === $wpdb->update(
            $table,
            array('student_key' => $key, 'active_student_key' => $active_key),
            array('id' => (int) $row->id)
        )) {
            throw new RuntimeException('Ein AG-Anmeldungsschlüssel konnte nicht gespeichert werden.');
        }
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
    if (false === $wpdb->query("ALTER TABLE $table MODIFY student_key char(64) NOT NULL")) {
        throw new RuntimeException('Der AG-Schüler-Schlüssel konnte nicht verpflichtend gesetzt werden.');
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix; der Nutzwert wird gebunden.
    $index = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM $table WHERE Key_name = %s", 'active_student_key'));
    if (null === $index) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fester Plugin-Tabellenname mit WordPress-Präfix.
        if (false === $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY active_student_key (active_student_key)")) {
            throw new RuntimeException('Die globale Eindeutigkeit aktiver AG-Anmeldungen konnte nicht eingerichtet werden.');
        }
    }
}

function flz_ags_schedule_registration_cleanup(): void
{
    if (!wp_next_scheduled('flz_ags_daily_registration_cleanup')) {
        $scheduled = wp_schedule_event(
            time() + HOUR_IN_SECONDS,
            'daily',
            'flz_ags_daily_registration_cleanup',
            array(),
            true
        );
        if (is_wp_error($scheduled) || false === $scheduled) {
            throw new RuntimeException('Der tägliche Löschtermin konnte nicht eingerichtet werden.');
        }
    }
}

function flz_ags_unschedule_registration_cleanup(): void
{
    $cleared = wp_clear_scheduled_hook('flz_ags_daily_registration_cleanup', array(), true);
    if (is_wp_error($cleared) || false === $cleared) {
        throw new RuntimeException('Der tägliche Löschtermin konnte nicht entfernt werden.');
    }
}

/** Deaktivierung beendet nur den Cron-Termin und löscht keine Fachdaten. */
function flz_ags_deactivate(): void
{
    try {
        flz_ags_unschedule_registration_cleanup();
    } catch (Throwable $error) {
        if (class_exists('flz_wpdb_objects\\FlzWpdbObjectsException')) {
            flz_ags_log_error($error, 'Entfernen des AG-Löschtermins bei Deaktivierung');
        } else {
            error_log('[flz_ags] Der tägliche Löschtermin konnte bei der Deaktivierung nicht entfernt werden.');
        }
    }
}
