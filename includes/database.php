<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

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
        FLZ_AGS_Course::create_table();
        FLZ_AGS_Slot::create_table();
        FLZ_AGS_Registration::create_table();
        self::delete_obsolete_columns();
        self::delete_legacy_tables();
    }

    /**
     * Entfernt Spalten aus nicht produktiven Zwischenständen.
     *
     * dbDelta ergänzt und ändert Spalten zuverlässig, entfernt aber keine
     * weggefallenen Felder. Da dieses Plugin noch nicht produktiv ist, halten
     * wir das tatsächliche lokale Schema bewusst eng an den aktuellen Modellen.
     */
    private static function delete_obsolete_columns(): void
    {
        global $wpdb;

        $table = FLZ_AGS_Course::database_table_name();
        try {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellenname stammt vom Modell, Spaltenname ist fest definiert.
            $column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'info_url'");
        } catch (Throwable $error) {
            throw flz_wpdb_objects\FlzWpdbObjectsException::operation(
                'Prüfen alter AG-Spalten',
                $table,
                $error
            );
        }

        if ($column === null) {
            return;
        }

        try {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellenname stammt vom Modell, Spaltenname ist fest definiert.
            $result = $wpdb->query("ALTER TABLE {$table} DROP COLUMN info_url");
        } catch (Throwable $error) {
            throw flz_wpdb_objects\FlzWpdbObjectsException::operation(
                'Entfernen alter AG-Spalten',
                $table,
                $error
            );
        }
        if ($result === false) {
            throw flz_wpdb_objects\FlzWpdbObjectsException::database(
                'Entfernen alter AG-Spalten',
                $table,
                (string) $wpdb->last_error
            );
        }
    }

    /**
     * Entfernt die nicht produktiven Tabellen der vorigen, manuell benannten
     * Datenbankschicht. Eine Datenmigration ist ausdrücklich nicht vorgesehen.
     */
    private static function delete_legacy_tables(): void
    {
        global $wpdb;

        // Erst Kindtabellen entfernen, damit mögliche Fremdschlüssel nicht stören.
        foreach (array('registrations', 'slots', 'courses') as $name) {
            $table = flz_ags_legacy_table($name);
            try {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fest definierter, intern validierter Alt-Tabellenname.
                $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
            } catch (Throwable $error) {
                throw flz_wpdb_objects\FlzWpdbObjectsException::operation(
                    'Entfernen der alten AG-Tabelle',
                    $table,
                    $error
                );
            }
            if ($result === false) {
                throw flz_wpdb_objects\FlzWpdbObjectsException::database(
                    'Entfernen der alten AG-Tabelle',
                    $table,
                    (string) $wpdb->last_error
                );
            }
        }
    }
}
