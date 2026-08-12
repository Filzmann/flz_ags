<?php
/**
 * Plugin Name: FLZ AG-Verwaltung
 * Description: Verwaltung und Anmeldung für Arbeitsgemeinschaften mit Schuljahr, Vorschaubildern, wöchentlichen Slots, Klassenlogik, Demo-Setup und CSV-Export.
 * Version: 0.3.10
 * Author: Tagore-Gymnasium / Simon
 * Text Domain: flz-ags
 * Requires Plugins: flz_wpdb_objects, flz_ui_components
 */

defined('ABSPATH') || exit;

define('FLZ_AGS_VERSION', '0.3.10');
define('FLZ_AGS_FILE', __FILE__);
define('FLZ_AGS_DIR', plugin_dir_path(__FILE__));
define('FLZ_AGS_URL', plugins_url('flz_ags/'));

require_once FLZ_AGS_DIR . 'includes/helpers.php';

const FLZ_AGS_MIN_WPDB_OBJECTS_VERSION = '1.4.0';
const FLZ_AGS_MIN_UI_COMPONENTS_VERSION = '0.1.11';

function flz_ags_dependencies_available(): bool
{
    return defined('FLZ_WPDB_OBJECTS_VERSION')
        && version_compare(FLZ_WPDB_OBJECTS_VERSION, FLZ_AGS_MIN_WPDB_OBJECTS_VERSION, '>=')
        && class_exists('flz_wpdb_objects\\FlzWpdbObject')
        && defined('FLZ_UI_COMPONENTS_VERSION')
        && version_compare(FLZ_UI_COMPONENTS_VERSION, FLZ_AGS_MIN_UI_COMPONENTS_VERSION, '>=')
        && function_exists('flz_ui');
}

function flz_ags_dependency_notice(): void
{
    add_action('admin_notices', static function (): void {
        echo wp_kses_post(
            flz_ags_notice(
                'FLZ AGs benötigt aktuelle, aktive Versionen von flz_wpdb_objects und flz_ui_components. Die AG-Verwaltung wurde nicht gestartet.',
                'error'
            )
        );
    });
}

function flz_ags_bootstrap(): bool
{
    static $loaded = false;

    if ($loaded) {
        return true;
    }
    if (!flz_ags_dependencies_available()) {
        flz_ags_dependency_notice();
        return false;
    }

    require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-model.php';
    require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-course.php';
    require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-slot.php';
    require_once FLZ_AGS_DIR . 'includes/models/class-flz-ags-registration.php';
    require_once FLZ_AGS_DIR . 'activate-deactivate.php';
    require_once FLZ_AGS_DIR . 'backend/backend.php';
    require_once FLZ_AGS_DIR . 'frontend/frontend.php';
    require_once FLZ_AGS_DIR . 'includes/class-flz-ags.php';

    try {
        flz_ags_maybe_upgrade();
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
        return false;
    }
    FLZ_AGS_Plugin::instance();
    $loaded = true;

    return true;
}

function flz_ags_activate_plugin(): void
{
    if (!flz_ags_bootstrap()) {
        wp_die(esc_html__('Aktivierung abgebrochen: Erforderliche FLZ-Plugins fehlen oder sind zu alt.', 'flz-ags'));
    }
    flz_ags_activate();
}

register_activation_hook(__FILE__, 'flz_ags_activate_plugin');
add_action('plugins_loaded', 'flz_ags_bootstrap', 20);

if (did_action('plugins_loaded')) {
    flz_ags_bootstrap();
}
