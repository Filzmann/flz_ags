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
    flz_ags_create_model_tables();
    add_option('flz_ags_current_school_year', flz_ags_default_school_year());
    add_option('flz_ags_classes', flz_ags_default_classes());
    add_option('flz_ags_parent_page_id', flz_ags_detect_detail_parent_page_id());
    add_option('flz_ags_registration_retention_enabled', 0);
    add_option('flz_ags_registration_retention_months', 24);
    if ((bool) get_option('flz_ags_registration_retention_enabled', 0)) {
        flz_ags_schedule_registration_cleanup();
    }
    update_option('flz_ags_db_version', FLZ_AGS_VERSION, false);
}

/**
 * Führt kleine Upgrade-Schritte ohne eigene SQL-Schicht aus.
 */
function flz_ags_maybe_upgrade(): void
{
    $installed = (string) get_option('flz_ags_db_version', '');
    if ($installed === FLZ_AGS_VERSION) {
        return;
    }

    flz_ags_create_model_tables();

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
    if ((bool) get_option('flz_ags_registration_retention_enabled', 0)) {
        flz_ags_schedule_registration_cleanup();
    }

    update_option('flz_ags_db_version', FLZ_AGS_VERSION, false);
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
