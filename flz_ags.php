<?php
/**
 * Plugin Name: Tagore AG-Verwaltung
 * Description: Verwaltung und Anmeldung für Arbeitsgemeinschaften mit Schuljahr, Vorschaubildern, wöchentlichen Slots, Klassenlogik, Demo-Setup und CSV-Export.
 * Version: 0.3.1
 * Author: Tagore-Gymnasium / Simon
 * Text Domain: flz-ags
 * Requires Plugins: flz_wpdb_objects, flz_ui_components
 */

defined('ABSPATH') || exit;

define('FLZ_AGS_VERSION', '0.3.1');
define('FLZ_AGS_FILE', __FILE__);
define('FLZ_AGS_DIR', plugin_dir_path(__FILE__));
define('FLZ_AGS_URL', plugins_url('flz_ags/'));

require_once FLZ_AGS_DIR . 'includes/helpers.php';

$flz_ags_wpdb_objects_file = WP_PLUGIN_DIR . '/flz_wpdb_objects/FlzWpdbObject.php';
if (!class_exists('flz_wpdb_objects\\FlzWpdbObject') && is_readable($flz_ags_wpdb_objects_file)) {
    require_once $flz_ags_wpdb_objects_file;
}

if (!class_exists('flz_wpdb_objects\\FlzWpdbObject')) {
    add_action('admin_notices', static function (): void {
        echo wp_kses_post(
            flz_ags_notice(
                'FLZ AGs benötigt das aktive Plugin flz_wpdb_objects. Die AG-Verwaltung wurde nicht gestartet.',
                'error'
            )
        );
    });
    return;
}

$flz_ags_ui_components_file = WP_PLUGIN_DIR . '/flz_ui_components/flz_ui_components.php';
if (!function_exists('flz_ui') && is_readable($flz_ags_ui_components_file)) {
    require_once $flz_ags_ui_components_file;
}

if (!function_exists('flz_ui')) {
    add_action('admin_notices', static function (): void {
        echo wp_kses_post(
            flz_ags_notice(
                'FLZ AGs benötigt das aktive Plugin flz_ui_components. Die AG-Verwaltung wurde nicht gestartet.',
                'error'
            )
        );
    });
    return;
}

require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-model.php';
require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-course.php';
require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-slot.php';
require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-registration.php';
require_once FLZ_AGS_DIR . 'includes/database.php';
require_once FLZ_AGS_DIR . 'includes/class-flz-ags.php';

register_activation_hook(__FILE__, array('FLZ_AGS_Database', 'activate'));

add_action('plugins_loaded', static function () {
    try {
        FLZ_AGS_Database::maybe_upgrade();
    } catch (Throwable $error) {
        flz_ags_log_error($error, 'Aktualisieren des AG-Datenbankschemas');
        add_action('admin_notices', static function (): void {
            echo wp_kses_post(
                flz_ags_notice(
                    'Die AG-Datenbank konnte nicht aktualisiert werden. Details stehen im Serverprotokoll.',
                    'error'
                )
            );
        });
        return;
    }
    FLZ_AGS_Plugin::instance();
});
