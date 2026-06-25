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
        self::delete_legacy_tables();
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
