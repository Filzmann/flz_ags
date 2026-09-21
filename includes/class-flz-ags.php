<?php

defined('ABSPATH') || exit;

function flz_ags_has_active_registration_conflict(Throwable $error): bool
{
    $current = $error;
    do {
        $message = strtolower($current->getMessage());
        if (str_contains($message, 'active_student_key') || str_contains($message, 'duplicate entry')) {
            return true;
        }
        $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return false;
}

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

class FLZ_AGS_Plugin
{
    private static ?FLZ_AGS_Plugin $instance = null;
    private bool $rendering_embedded_registration = false;

    public static function instance(): FLZ_AGS_Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_post_flz_ags_save_course', array($this, 'handle_save_course'));
        add_action('admin_post_flz_ags_quick_edit_course', array($this, 'handle_quick_edit_course'));
        add_action('admin_post_flz_ags_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_flz_ags_update_registration', array($this, 'handle_update_registration'));
        add_action('admin_post_flz_ags_export_csv', array($this, 'handle_export_csv'));
        add_action('admin_post_flz_ags_import_registrations_csv', array($this, 'handle_import_registrations_csv'));
        add_action('admin_post_flz_ags_delete_old_registrations', array($this, 'handle_delete_old_registrations'));
        add_action('admin_post_flz_ags_clear_mock_mail_capture', array($this, 'handle_clear_mock_mail_capture'));
        add_action('admin_post_flz_ags_export_courses_csv', array($this, 'handle_export_courses_csv'));
        add_action('admin_post_flz_ags_import_courses_csv', array($this, 'handle_import_courses_csv'));
        add_action('admin_post_flz_ags_install_demo', array($this, 'handle_install_demo'));
        add_action('wp_ajax_flz_ags_search_detail_pages', array($this, 'ajax_search_detail_pages'));
        add_action('wp_ajax_flz_ags_create_detail_page', array($this, 'ajax_create_detail_page'));

        add_shortcode('flz_ag_liste', array($this, 'shortcode_list'));
        add_shortcode('flz_ag_anmeldung', array($this, 'shortcode_registration'));

        add_action('init', array($this, 'register_blocks'));
        add_filter('the_content', array($this, 'append_registration_to_detail_page'));
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        add_action('flz_ags_daily_registration_cleanup', array($this, 'handle_scheduled_registration_cleanup'));
    }

    public function register_blocks(): void
    {
        if (!function_exists('flz_ui_register_shortcode_block')) {
            return;
        }

        $school_year_selection = flz_ags_block_school_year_selection();

        flz_ui_register_shortcode_block(array(
            'name' => 'flz/ags-list',
            'shortcode' => 'flz_ag_liste',
            'title' => 'FLZ AG-Liste',
            'description' => 'Öffentliche Übersicht der veröffentlichten AGs mit Detailseiten.',
            'icon' => 'groups',
            'keywords' => array('ag', 'arbeitsgemeinschaft', 'flz'),
            'attributes' => array(
                'school_year' => array(
                    'type' => 'string',
                    'default' => $school_year_selection['default'],
                ),
            ),
            'fields' => array(
                'school_year' => array(
                    'label' => 'Schuljahr',
                    'description' => 'Vorhandenes AG-Schuljahr auswählen.',
                    'control' => 'select',
                    'options' => $school_year_selection['options'],
                ),
            ),
        ));

        flz_ui_register_shortcode_block(array(
            'name' => 'flz/ag-registration',
            'shortcode' => 'flz_ag_anmeldung',
            'title' => 'FLZ AG-Anmeldung',
            'description' => 'Anmeldeformular für die verknüpfte AG-Detailseite.',
            'icon' => 'forms',
            'keywords' => array('ag', 'anmeldung', 'flz'),
            'attributes' => array(
                'school_year' => array(
                    'type' => 'string',
                    'default' => $school_year_selection['default'],
                ),
                'course_id' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
            'fields' => array(
                'school_year' => array(
                    'label' => 'Schuljahr',
                    'description' => 'Vorhandenes AG-Schuljahr auswählen.',
                    'control' => 'select',
                    'options' => $school_year_selection['options'],
                ),
                'course_id' => array(
                    'label' => 'AG-ID',
                    'description' => 'Optional. Normalerweise leer lassen, damit die Detailseite die AG bestimmt.',
                ),
            ),
        ));
    }

    public function register_frontend_assets(): void
    {
        wp_register_style('flz-ags', FLZ_AGS_URL . 'assets/css/flz-ags.css', array('flz-ui-components'), flz_ags_asset_version('assets/css/flz-ags.css'));
        wp_register_script('flz-ags', FLZ_AGS_URL . 'assets/js/flz-ags.js', array('flz-ui-components'), flz_ags_asset_version('assets/js/flz-ags.js'), true);
    }

    public function register_admin_assets(string $hook): void
    {
        if (strpos($hook, 'flz-ags') === false) {
            return;
        }

        wp_enqueue_style('flz-ags-admin', FLZ_AGS_URL . 'assets/css/flz-ags.css', array('flz-ui-components'), flz_ags_asset_version('assets/css/flz-ags.css'));
        wp_enqueue_media();
        wp_enqueue_script('flz-ags-admin', FLZ_AGS_URL . 'assets/js/flz-ags-admin.js', array('jquery'), flz_ags_asset_version('assets/js/flz-ags-admin.js'), true);
        wp_localize_script('flz-ags-admin', 'flzAgsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flz_ags_detail_page'),
            'strings' => array(
                'searching' => 'Suche läuft …',
                'noResults' => 'Keine passende Seite gefunden.',
                'searchError' => 'Die Seitensuche konnte nicht geladen werden.',
                'createError' => 'Die Detailseite konnte nicht angelegt werden.',
                'noSelection' => 'Keine Detailseite ausgewählt.',
                'createNeedsTitle' => 'Bitte zuerst einen AG-Titel eintragen.',
            ),
        ));
    }

    public function register_admin_menu(): void
    {
        $capability = flz_ags_manage_capability();
        if (current_user_can($capability)) {
            add_menu_page('FLZ AGs', 'FLZ AGs', $capability, 'flz-ags', array($this, 'render_admin_courses_page'), 'dashicons-groups', 26);
            add_submenu_page('flz-ags', 'AGs', 'AGs', $capability, 'flz-ags', array($this, 'render_admin_courses_page'));
            add_submenu_page('flz-ags', 'Anmeldungen', 'Anmeldungen', $capability, 'flz-ags-registrations', array($this, 'render_admin_registrations_page'));
            add_submenu_page('flz-ags', 'Demo-Setup', 'Demo-Setup', $capability, 'flz-ags-demo', array($this, 'render_admin_demo_page'));
            add_submenu_page('flz-ags', 'Einstellungen', 'Einstellungen', $capability, 'flz-ags-settings', array($this, 'render_admin_settings_page'));
            return;
        }

        add_menu_page('Meine AG-Anmeldungen', 'Meine AGs', flz_ags_view_assigned_courses_capability(), 'flz-ags-registrations', array($this, 'render_admin_registrations_page'), 'dashicons-groups', 26);
    }

    private function assert_admin_permission(): void
    {
        if (!current_user_can(flz_ags_manage_capability())) {
            wp_die(esc_html__('Keine Berechtigung.', 'flz-ags'));
        }
    }

    private function assert_ajax_permission(): void
    {
        if (!current_user_can(flz_ags_manage_capability())) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }
    }

    private function is_manager(): bool
    {
        return current_user_can(flz_ags_manage_capability());
    }

    private function assert_registration_read_permission(): void
    {
        if (!$this->is_manager() && !current_user_can(flz_ags_view_assigned_courses_capability())) {
            wp_die(esc_html__('Keine Berechtigung.', 'flz-ags'));
        }
    }

    public function ajax_search_detail_pages(): void
    {
        $this->assert_ajax_permission();
        check_ajax_referer('flz_ags_detail_page', 'nonce');

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (strlen($term) < 2) {
            wp_send_json_success(array('pages' => array()));
        }

        try {
            $pages = get_posts(array(
                'post_type'      => 'page',
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 10,
                'orderby'        => 'title',
                'order'          => 'ASC',
                's'              => $term,
            ));

            $payload = array();
            foreach ($pages as $page) {
                if ($page instanceof WP_Post) {
                    $payload[] = $this->build_page_payload((int) $page->ID);
                }
            }

            wp_send_json_success(array('pages' => $payload));
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Suchen einer AG-Detailseite');
            wp_send_json_error(array('message' => 'Die Seitensuche konnte nicht ausgeführt werden.'), 500);
        }
    }

    public function ajax_create_detail_page(): void
    {
        $this->assert_ajax_permission();
        check_ajax_referer('flz_ags_detail_page', 'nonce');

        if (!current_user_can('publish_pages')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung zum Anlegen veröffentlichter Seiten.'), 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($title === '') {
            wp_send_json_error(array('message' => 'Bitte zuerst einen AG-Titel eintragen.'), 400);
        }

        try {
            $parent_id = flz_ags_detail_parent_page_id();
            if ($parent_id <= 0) {
                wp_send_json_error(array('message' => 'Die AG-Hauptseite wurde nicht gefunden. Bitte zuerst unter „FLZ AGs → Einstellungen“ auswählen.'), 400);
            }

            $page_id = wp_insert_post(
                wp_slash(array(
                    'post_title' => $title,
                    'post_name' => sanitize_title($title),
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_parent' => $parent_id,
                    'post_content' => '<p>Informationen zu dieser AG werden hier ergänzt.</p>',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                )),
                true
            );

            if (is_wp_error($page_id)) {
                wp_send_json_error(array('message' => $page_id->get_error_message()), 500);
            }

            wp_send_json_success(array('page' => $this->build_page_payload((int) $page_id)));
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Anlegen einer AG-Detailseite');
            wp_send_json_error(array('message' => 'Die Detailseite konnte nicht angelegt werden.'), 500);
        }
    }

    /**
     * Protokolliert einen technischen Fehler und leitet mit sicherem Fehlercode
     * auf eine interne Administrationsseite zurück.
     */
    private function redirect_admin_error(Throwable $error, string $context, string $code, array $args): void
    {
        flz_ags_log_error($error, $context);
        $args['flz_ags_error'] = $code;
        flz_ags_safe_redirect(flz_ags_admin_url($args));
    }

    public function append_registration_to_detail_page(string $content): string
    {
        if (
            is_admin()
            || $this->rendering_embedded_registration
            || !is_singular('page')
            || !in_the_loop()
            || !is_main_query()
            || has_shortcode($content, 'flz_ag_anmeldung')
        ) {
            return $content;
        }

        $page_id = (int) get_queried_object_id();
        if ($page_id <= 0) {
            return $content;
        }

        try {
            $course = $this->get_public_course_for_detail_page($page_id, flz_ags_current_school_year());
            if (!$course instanceof FLZ_AGS_Course) {
                return $content;
            }

            $this->rendering_embedded_registration = true;
            $registration = $this->shortcode_registration(array(
                'school_year' => (string) $course->school_year,
                'course_id' => (int) $course->id,
            ));
            $this->rendering_embedded_registration = false;

            return $content . flz_ui()->floating_action_panel(array(
                'id' => 'flz-ags-registration-panel-' . (int) $course->id,
                'title' => 'AG-Anmeldung',
                'button_label' => !empty($course->registration_open) ? 'Jetzt AG anmelden' : 'AG-Anmeldung anzeigen',
                'button_icon' => 'check',
                'content' => $registration,
                'open' => $this->is_registration_post_for_course((int) $course->id),
                'class' => 'flz-ags-registration-panel',
            ));
        } catch (Throwable $error) {
            $this->rendering_embedded_registration = false;
            flz_ags_log_error($error, 'Automatisches Einbetten der AG-Anmeldung');
            return $content;
        }
    }

    public function render_admin_courses_page(): void
    {
        $this->assert_admin_permission();

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
        $csv_report = null;

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs</h1>';

        if (isset($_GET['saved'])) {
            echo wp_kses_post(flz_ags_notice('AG gespeichert.'));
        }
        if (isset($_GET['quick_saved'])) {
            echo wp_kses_post(flz_ags_notice('AG per Schnellbearbeitung gespeichert.'));
        }
        if (isset($_GET['demo'])) {
            $count = absint($_GET['demo']);
            echo wp_kses_post(flz_ags_notice($count . ' Demo-AGs wurden angelegt. Bereits vorhandene Demo-AGs wurden übersprungen.'));
        }
        if (isset($_GET['csv_imported'])) {
            $stored_report = get_transient($this->course_csv_report_key());
            if (is_array($stored_report)) {
                $csv_report = $stored_report;
                $notice_type = empty($csv_report['warnings']) ? 'success' : 'warning';
                echo wp_kses_post(flz_ags_notice(sprintf(
                    'CSV-Import abgeschlossen: %d AGs neu, %d AGs aktualisiert, %d Termine neu und %d Termine aktualisiert. %d Hinweise wurden protokolliert. %s',
                    (int) ($csv_report['courses_created'] ?? 0),
                    (int) ($csv_report['courses_updated'] ?? 0),
                    (int) ($csv_report['slots_created'] ?? 0),
                    (int) ($csv_report['slots_updated'] ?? 0),
                    count((array) ($csv_report['warnings'] ?? array())),
                    (string) ($csv_report['school_year_note'] ?? '')
                ), $notice_type));
            } else {
                echo wp_kses_post(flz_ags_notice('Der AG-CSV-Import wurde verarbeitet.'));
            }
            delete_transient($this->course_csv_report_key());
        }
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            $error_message = flz_ags_error_message($error_code);
            if ('course-import' === $error_code) {
                $csv_error = get_transient($this->course_csv_notice_key());
                if (is_string($csv_error) && '' !== trim($csv_error)) {
                    $error_message = $csv_error;
                }
                delete_transient($this->course_csv_notice_key());
            }
            echo wp_kses_post(flz_ags_notice($error_message, 'error'));
        }

        try {
            if ($action === 'new' || ($action === 'edit' && $course_id > 0)) {
                $this->render_course_form($course_id);
            } else {
                $this->render_course_list($csv_report);
            }
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Anzeigen der AG-Verwaltung');
            echo wp_kses_post(
                flz_ags_notice('Die AG-Daten konnten nicht geladen werden. Details stehen im Serverprotokoll.', 'error')
            );
        }

        echo '</div>';
    }

    private function get_course(int $course_id): ?object
    {
        return FLZ_AGS_Course::get_by_id($course_id);
    }

    private function get_courses(string $school_year, bool $public_only = false): array
    {
        return FLZ_AGS_Course::find_for_school_year($school_year, $public_only);
    }

    private function get_course_slots(int $course_id, bool $include_inactive = true): array
    {
        return FLZ_AGS_Slot::find_for_course($course_id, $include_inactive);
    }

    private function get_slot_with_course(int $slot_id, bool $for_update = false): ?object
    {
        return FLZ_AGS_Slot::find_with_course($slot_id, $for_update);
    }

    private function validate_detail_page_id(int $detail_page_id): int
    {
        if ($detail_page_id <= 0) {
            return 0;
        }

        $page = get_post($detail_page_id);
        if (!$page instanceof WP_Post || $page->post_type !== 'page' || in_array($page->post_status, array('trash', 'auto-draft'), true)) {
            throw new UnexpectedValueException('Die ausgewählte AG-Detailseite wurde nicht gefunden oder ist nicht verwendbar.');
        }

        return $detail_page_id;
    }

    private function get_public_course_by_id(int $course_id, string $school_year): ?FLZ_AGS_Course
    {
        $course = $course_id > 0 ? FLZ_AGS_Course::get_by_id($course_id) : null;
        if (
            !$course instanceof FLZ_AGS_Course
            || $course->school_year !== $school_year
            || empty($course->is_active)
            || empty($course->is_visible)
        ) {
            return null;
        }

        return $course;
    }

    private function get_public_course_for_detail_page(int $page_id, string $school_year): ?FLZ_AGS_Course
    {
        if ($page_id <= 0) {
            return null;
        }

        $course = FLZ_AGS_Course::find_public_by_detail_page_id($page_id, $school_year);
        if ($course instanceof FLZ_AGS_Course) {
            return $course;
        }

        return null;
    }

    private function is_registration_post_for_course(int $course_id): bool
    {
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('post' !== $request_method) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Hier wird nur entschieden, ob das bereits gerenderte Panel nach einem POST offen angezeigt wird. Die Verarbeitung prüft die Nonce in handle_frontend_registration().
        $posted_course_id = isset($_POST['flz_ags_course_id']) ? absint(wp_unslash($_POST['flz_ags_course_id'])) : 0;

        return $course_id > 0 && $posted_course_id === $course_id;
    }

    private function build_page_payload(int $page_id): array
    {
        $page = get_post($page_id);
        if (!$page instanceof WP_Post || $page->post_type !== 'page') {
            throw new UnexpectedValueException('Die WordPress-Seite wurde nicht gefunden.');
        }

        $permalink = get_permalink($page_id);
        $edit_url = get_edit_post_link($page_id, '');

        return array(
            'id' => $page_id,
            'title' => get_the_title($page_id),
            'label' => flz_ags_page_label($page_id),
            'status' => flz_ags_page_status_label($page->post_status),
            'url' => is_string($permalink) ? $permalink : '',
            'editUrl' => is_string($edit_url) ? $edit_url : '',
        );
    }

    private function render_course_list(?array $csv_report = null): void
    {
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $courses = $this->get_courses($school_year, false);
        $target_school_year = flz_ags_default_school_year();
        $school_year_options = flz_ags_block_school_year_selection()['options'];
        $school_year_options[$school_year] = $school_year;
        $school_year_options[$target_school_year] = $target_school_year;
        if (preg_match('/^(\d{4})\/(\d{4})$/', $target_school_year, $matches)) {
            for ($offset = 1; $offset <= 3; ++$offset) {
                $future_school_year = ((int) $matches[1] + $offset) . '/' . ((int) $matches[2] + $offset);
                $school_year_options[$future_school_year] = $future_school_year;
            }
        }
        krsort($school_year_options, SORT_STRING);
        flz_ags_render_backend_template('course-list', array(
            'school_year' => $school_year,
            'courses' => $courses,
            'csv_report' => $csv_report,
            'target_school_year' => $target_school_year,
            'school_year_options' => $school_year_options,
        ));
    }

    private function render_course_form(int $course_id): void
    {
        $course = $course_id > 0 ? $this->get_course($course_id) : null;
        $slots = $course ? $this->get_course_slots((int) $course->id, true) : array();
        flz_ags_render_backend_template('course-form', array(
            'course_id' => $course_id,
            'course' => $course,
            'slots' => $slots,
        ));
    }

    public function handle_save_course(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_save_course');

        $now = current_time('mysql');
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $save_and_new = isset($_POST['save_and_new']);
        $save_and_close = isset($_POST['save_and_close']);

        if ($title === '') {
            wp_die(esc_html__('Der Titel ist erforderlich.', 'flz-ags'));
        }

        $school_year = isset($_POST['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year']))) : flz_ags_current_school_year();
        $registration_open = isset($_POST['registration_open']) ? 1 : 0;
        $detail_page_id = isset($_POST['detail_page_id']) ? absint($_POST['detail_page_id']) : 0;

        $slots = array();
        if (isset($_POST['slots']) && is_array($_POST['slots'])) {
            $slots = map_deep(wp_unslash($_POST['slots']), 'sanitize_text_field');
        }

        try {
            $detail_page_id = $this->validate_detail_page_id($detail_page_id);
            if ($registration_open && $detail_page_id > 0 && get_post_status($detail_page_id) !== 'publish') {
                throw new UnexpectedValueException('Für eine geöffnete AG-Anmeldung muss die Detailseite veröffentlicht sein.');
            }

            $data = array(
                'school_year' => $school_year,
                'title' => $title,
                'slug' => sanitize_title($title),
                'short_description' => isset($_POST['short_description']) ? sanitize_textarea_field(wp_unslash($_POST['short_description'])) : '',
                'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '',
                'image_url' => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
                'detail_page_id' => $detail_page_id,
                'category' => isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '',
                'leader_name' => isset($_POST['leader_name']) ? sanitize_text_field(wp_unslash($_POST['leader_name'])) : '',
                'leader_user_id' => isset($_POST['leader_user_id']) ? absint($_POST['leader_user_id']) : 0,
                'allowed_grades' => isset($_POST['allowed_grades']) ? flz_ags_sanitize_allowed_grades(sanitize_text_field(wp_unslash($_POST['allowed_grades']))) : '',
                'only_grade_7' => isset($_POST['only_grade_7']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                'registration_open' => $registration_open,
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0,
                'updated_at' => $now,
            );

            if ($data['leader_user_id'] > 0 && !user_can($data['leader_user_id'], flz_ags_view_assigned_courses_capability())) {
                throw new UnexpectedValueException('Die ausgewählte AG-Leitung besitzt nicht die Rolle AG-Leiter.');
            }

            $course_id = FLZ_AGS_Model::transaction(
                function () use ($course_id, $data, $slots, $school_year, $now): int {
                    $course = $course_id > 0 ? FLZ_AGS_Course::get_by_id($course_id) : new FLZ_AGS_Course();
                    if (!$course instanceof FLZ_AGS_Course) {
                        throw new UnexpectedValueException('Die zu aktualisierende AG wurde nicht gefunden.');
                    }

                    foreach ($data as $property => $value) {
                        $course->{$property} = $value;
                    }
                    if ($course->created_at === null) {
                        $course->created_at = $now;
                    }
                    $course->save();
                    $saved_course_id = (int) $course->id;

                    foreach ($slots as $index => $slot_data) {
                        if (!is_array($slot_data)) {
                            throw new UnexpectedValueException('AG-Termin ' . ($index + 1) . ' hat ein ungültiges Datenformat.');
                        }

                        $slot_id = isset($slot_data['id']) ? absint($slot_data['id']) : 0;
                        $weekday = isset($slot_data['weekday']) ? absint($slot_data['weekday']) : 0;
                        $start_time = isset($slot_data['start_time']) ? sanitize_text_field($slot_data['start_time']) : '';
                        $end_time = isset($slot_data['end_time']) ? sanitize_text_field($slot_data['end_time']) : '';
                        $room = isset($slot_data['room']) ? sanitize_text_field($slot_data['room']) : '';
                        $has_values = $weekday > 0 || $start_time !== '' || $end_time !== '' || $room !== '';

                        if (!$has_values && $slot_id === 0) {
                            continue;
                        }

                        $slot = $slot_id > 0
                            ? FLZ_AGS_Slot::get_by_fields(array('id' => $slot_id, 'course_id' => $saved_course_id))
                            : new FLZ_AGS_Slot();
                        if (!$slot instanceof FLZ_AGS_Slot) {
                            throw new UnexpectedValueException('AG-Termin ' . ($index + 1) . ' gehört nicht zur bearbeiteten AG.');
                        }

                        if (!$has_values) {
                            $slot->is_active = 0;
                            $slot->updated_at = $now;
                            $slot->save();
                            continue;
                        }

                        if (
                            $weekday < 1 || $weekday > 7
                            || !preg_match('/^\d{2}:\d{2}$/', $start_time)
                            || !preg_match('/^\d{2}:\d{2}$/', $end_time)
                        ) {
                            throw new UnexpectedValueException('AG-Termin ' . ($index + 1) . ' enthält ungültige Zeitangaben.');
                        }

                        $slot->course_id = $saved_course_id;
                        $slot->school_year = $school_year;
                        $slot->weekday = $weekday;
                        $slot->start_time = $start_time . ':00';
                        $slot->end_time = $end_time . ':00';
                        $slot->room = $room;
                        $slot->max_participants = isset($slot_data['max_participants'])
                            ? max(0, intval($slot_data['max_participants']))
                            : 0;
                        $slot->is_active = isset($slot_data['is_active']) ? 1 : 0;
                        $slot->sort_order = isset($slot_data['sort_order']) ? intval($slot_data['sort_order']) : 0;
                        $slot->updated_at = $now;
                        if ($slot->created_at === null) {
                            $slot->created_at = $now;
                        }
                        $slot->save();
                    }

                    return $saved_course_id;
                },
                'Speichern einer AG mit ihren Terminen'
            );
        } catch (Throwable $error) {
            $error_code = 'save-course';
            if ($error instanceof UnexpectedValueException && strpos($error->getMessage(), 'Detailseite') !== false) {
                $error_code = 'save-course-detail-page';
            }
            $this->redirect_admin_error(
                $error,
                'Speichern einer AG',
                $error_code,
                array('page' => 'flz-ags', 'action' => $course_id > 0 ? 'edit' : 'new', 'course_id' => $course_id)
            );
        }

        $redirect_args = flz_ags_course_save_redirect_args($course_id, $save_and_new, $save_and_close);

        flz_ags_safe_redirect(flz_ags_admin_url($redirect_args));
    }

    /**
     * Speichert die bewusst begrenzte Feldauswahl der AG-Listenzeile.
     */
    public function handle_quick_edit_course(): void
    {
        $this->assert_admin_permission();

        $course_id = isset($_POST['course_id']) ? absint(wp_unslash($_POST['course_id'])) : 0;
        check_admin_referer('flz_ags_quick_edit_course_' . $course_id);

        $school_year = isset($_POST['school_year_return'])
            ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year_return'])))
            : flz_ags_current_school_year();

        try {
            $course = $course_id > 0 ? FLZ_AGS_Course::get_by_id($course_id) : null;
            if (!$course instanceof FLZ_AGS_Course) {
                throw new UnexpectedValueException('Die zu aktualisierende AG wurde nicht gefunden.');
            }

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                throw new UnexpectedValueException('Der Titel ist erforderlich.');
            }

            $course->title = $title;
            $course->slug = sanitize_title($title);
            $course->short_description = isset($_POST['short_description']) ? sanitize_textarea_field(wp_unslash($_POST['short_description'])) : '';
            $course->category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
            $course->leader_name = isset($_POST['leader_name']) ? sanitize_text_field(wp_unslash($_POST['leader_name'])) : '';
            $course->allowed_grades = isset($_POST['allowed_grades'])
                ? flz_ags_sanitize_allowed_grades(sanitize_text_field(wp_unslash($_POST['allowed_grades'])))
                : '';
            $course->only_grade_7 = isset($_POST['only_grade_7']) ? 1 : 0;
            $course->is_active = isset($_POST['is_active']) ? 1 : 0;
            $course->is_visible = isset($_POST['is_visible']) ? 1 : 0;
            $registration_open = isset($_POST['registration_open']) ? 1 : 0;
            if ($registration_open && (int) $course->detail_page_id > 0 && get_post_status((int) $course->detail_page_id) !== 'publish') {
                throw new UnexpectedValueException('Für eine geöffnete AG-Anmeldung muss die Detailseite veröffentlicht sein.');
            }
            $course->registration_open = $registration_open;
            $course->sort_order = isset($_POST['sort_order']) ? intval(wp_unslash($_POST['sort_order'])) : 0;
            $course->updated_at = current_time('mysql');
            $course->save();
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Schnellbearbeitung einer AG',
                'quick-edit-course',
                array('page' => 'flz-ags', 'school_year' => $school_year)
            );
        }

        flz_ags_safe_redirect(flz_ags_admin_url(array(
            'page' => 'flz-ags',
            'school_year' => $school_year,
            'quick_saved' => 1,
        )));
    }

    public function render_admin_settings_page(): void
    {
        $this->assert_admin_permission();
        $parent_page_id = flz_ags_detail_parent_page_id();
        $configured_parent_page_id = flz_ags_configured_detail_parent_page_id();
        $parent_hint = $parent_page_id > 0
            ? flz_ags_page_label($parent_page_id)
            : 'Noch keine AG-Hauptseite ausgewählt oder automatisch gefunden.';

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Einstellungen</h1>';
        if (isset($_GET['saved'])) {
            echo wp_kses_post(flz_ags_notice('Einstellungen gespeichert.'));
        }
        if (isset($_GET['deleted'])) {
            $deleted = absint(wp_unslash($_GET['deleted']));
            echo wp_kses_post(flz_ags_notice($deleted . ' alte Anmeldung(en) wurden dauerhaft gelöscht.'));
        }
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }

        flz_ags_render_backend_template('settings', array(
            'parent_page_id' => $parent_page_id,
            'configured_parent_page_id' => $configured_parent_page_id,
            'parent_hint' => $parent_hint,
            'registration_retention_enabled' => (bool) get_option('flz_ags_registration_retention_enabled', 0),
            'registration_retention_months' => flz_ags_registration_retention_months(),
            'mock_mail_capture_exists' => false !== get_transient('flz_ags_mock_confirmation_mail_last'),
        ));
        echo '</div>';
    }

    public function handle_save_settings(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_save_settings');

        $school_year = isset($_POST['current_school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['current_school_year']))) : flz_ags_default_school_year();
        $classes_text = isset($_POST['classes_text']) ? sanitize_textarea_field(wp_unslash($_POST['classes_text'])) : '';
        $classes = isset($_POST['reset_classes']) ? flz_ags_default_classes() : flz_ags_sanitize_classes_from_text($classes_text);
        $parent_page_id = isset($_POST['parent_page_id']) ? absint(wp_unslash($_POST['parent_page_id'])) : 0;
        $retention_enabled = isset($_POST['registration_retention_enabled']) ? 1 : 0;
        $retention_months = isset($_POST['registration_retention_months'])
            ? max(1, min(120, absint(wp_unslash($_POST['registration_retention_months']))))
            : 24;
        $multiple_registrations_enabled = isset($_POST['multiple_registrations_enabled']) ? 1 : 0;
        $registration_form_notice = isset($_POST['registration_form_notice'])
            ? wp_kses_post(wp_unslash($_POST['registration_form_notice']))
            : 'Die AG-Anmeldung gilt nur für ein Schulhalbjahr.';

        try {
            if ($parent_page_id > 0) {
                $parent_page_id = $this->validate_detail_page_id($parent_page_id);
            }

            $retention_enabled ? flz_ags_schedule_registration_cleanup() : flz_ags_unschedule_registration_cleanup();
            update_option('flz_ags_current_school_year', $school_year, false);
            update_option('flz_ags_classes', !empty($classes) ? $classes : flz_ags_default_classes(), false);
            update_option('flz_ags_parent_page_id', $parent_page_id, false);
            update_option('flz_ags_registration_retention_months', $retention_months, false);
            update_option('flz_ags_registration_retention_enabled', $retention_enabled, false);
            update_option('flz_ags_multiple_registrations_enabled', $multiple_registrations_enabled, false);
            update_option('flz_ags_registration_form_notice', $registration_form_notice, false);
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Speichern der AG-Einstellungen',
                'save-settings',
                array('page' => 'flz-ags-settings')
            );
        }

        flz_ags_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-settings', 'saved' => 1)));
    }

    public function handle_clear_mock_mail_capture(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_clear_mock_mail_capture');
        delete_transient('flz_ags_mock_confirmation_mail_last');
        flz_ags_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-settings', 'mail_capture_cleared' => 1)));
    }

    public function render_admin_demo_page(): void
    {
        $this->assert_admin_permission();
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $demo = flz_ags_demo_courses();

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Demo-Setup</h1>';
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }
        flz_ags_render_backend_template('demo', array(
            'school_year' => $school_year,
            'demo' => $demo,
        ));
        echo '</div>';
    }

    public function handle_install_demo(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_install_demo');

        $school_year = isset($_POST['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year']))) : flz_ags_current_school_year();
        $now = current_time('mysql');
        try {
            $inserted = FLZ_AGS_Model::transaction(
                static function () use ($school_year, $now): int {
                    $inserted_count = 0;
                    foreach (flz_ags_demo_courses() as $course_data) {
                        $slug = sanitize_title($course_data['title']);
                        $exists = FLZ_AGS_Course::count_by(array('school_year' => $school_year, 'slug' => $slug));
                        if ($exists > 0) {
                            continue;
                        }

                        $course = new FLZ_AGS_Course(array(
                            'school_year' => $school_year,
                            'title' => sanitize_text_field($course_data['title']),
                            'slug' => $slug,
                            'short_description' => sanitize_textarea_field($course_data['short_description']),
                            'description' => wp_kses_post($course_data['description']),
                            'image_url' => esc_url_raw($course_data['image_url']),
                            'detail_page_id' => absint($course_data['detail_page_id']),
                            'category' => sanitize_text_field($course_data['category']),
                            'leader_name' => sanitize_text_field($course_data['leader_name']),
                            'allowed_grades' => flz_ags_sanitize_allowed_grades($course_data['allowed_grades']),
                            'only_grade_7' => !empty($course_data['only_grade_7']) ? 1 : 0,
                            'is_active' => 1,
                            'is_visible' => 1,
                            'registration_open' => 1,
                            'sort_order' => intval($course_data['sort_order']),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ));
                        $course->save();

                        foreach ($course_data['slots'] as $index => $slot_data) {
                            $slot = new FLZ_AGS_Slot(array(
                                'course_id' => (int) $course->id,
                                'school_year' => $school_year,
                                'weekday' => absint($slot_data['weekday']),
                                'start_time' => sanitize_text_field($slot_data['start_time']) . ':00',
                                'end_time' => sanitize_text_field($slot_data['end_time']) . ':00',
                                'room' => sanitize_text_field($slot_data['room']),
                                'max_participants' => max(0, intval($slot_data['max_participants'])),
                                'is_active' => 1,
                                'sort_order' => $index,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ));
                            $slot->save();
                        }
                        $inserted_count++;
                    }

                    return $inserted_count;
                },
                'Anlegen der Demo-AGs'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Anlegen der Demo-AGs',
                'install-demo',
                array('page' => 'flz-ags-demo', 'school_year' => $school_year)
            );
        }

        flz_ags_safe_redirect(
            flz_ags_admin_url(array('page' => 'flz-ags', 'school_year' => $school_year, 'demo' => $inserted))
        );
    }

    private function count_active_registrations(int $slot_id): int
    {
        return FLZ_AGS_Registration::count_by(array('slot_id' => $slot_id, 'status' => 'active'));
    }

    /**
     * Verweigert eine weitere aktive Anmeldung, wenn der bereits gesperrte Slot voll ist.
     *
     * Der Aufrufer muss den Slot innerhalb einer Transaktion mit FOR UPDATE geladen haben,
     * damit Zählung und anschließendes Speichern nicht konkurrieren können.
     */
    private function assert_slot_capacity_available(object $slot): void
    {
        $max_participants = (int) ($slot->max_participants ?? 0);
        if ($max_participants <= 0) {
            return;
        }

        $taken = $this->count_active_registrations((int) $slot->id);
        if ($taken >= $max_participants) {
            throw new UnexpectedValueException('Dieser AG-Slot ist inzwischen ausgebucht.');
        }
    }

    private function get_public_slots(string $school_year): array
    {
        return FLZ_AGS_Slot::find_public_for_school_year($school_year);
    }

    private function get_public_courses_with_slots(string $school_year): array
    {
        $courses = $this->get_courses($school_year, true);
        $result = array();
        foreach ($courses as $course) {
            $course->slots = $this->get_course_slots((int) $course->id, false);
            if (!empty($course->slots)) {
                $result[] = $course;
            }
        }
        return $result;
    }

    public function shortcode_list($atts): string
    {
        $atts = shortcode_atts(array(
            'school_year' => flz_ags_current_school_year(),
        ), (array) $atts, 'flz_ag_liste');

        $school_year = flz_ags_sanitize_school_year($atts['school_year']);
        try {
            $courses = $this->get_public_courses_with_slots($school_year);
            foreach ($courses as $course) {
                $course->registration_html = '';
                $course->registration_panel_open = false;
                if (flz_ags_course_detail_page_id($course) <= 0 && !empty($course->registration_open)) {
                    $course->registration_html = $this->shortcode_registration(array(
                        'school_year' => $school_year,
                        'course_id' => (int) $course->id,
                    ));
                    $course->registration_panel_open = $this->is_registration_post_for_course((int) $course->id);
                }
            }
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Anzeigen der öffentlichen AG-Liste');
            return '<div class="flz-ags">' . flz_ags_notice('Die AG-Liste kann derzeit nicht geladen werden. Bitte später erneut versuchen.', 'error') . '</div>';
        }

        wp_enqueue_style('flz-ags');
        wp_enqueue_script('flz-ags');

        $buffer_level = ob_get_level();
        try {
            ob_start();
            flz_ags_render_frontend_template('list', array(
                'school_year' => $school_year,
                'courses' => $courses,
            ));

            return (string) ob_get_clean();
        } catch (Throwable $error) {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
            flz_ags_log_error($error, 'Rendern der öffentlichen AG-Liste');
            return '<div class="flz-ags">' . flz_ags_notice('Die AG-Liste kann derzeit nicht angezeigt werden. Bitte später erneut versuchen.', 'error') . '</div>';
        }
    }

    public function shortcode_registration($atts): string
    {
        $atts = shortcode_atts(array(
            'school_year' => flz_ags_current_school_year(),
            'course_id' => 0,
        ), (array) $atts, 'flz_ag_anmeldung');

        $school_year = flz_ags_sanitize_school_year($atts['school_year']);
        $messages = array();
        $success = false;

        wp_enqueue_style('flz-ags');
        wp_enqueue_script('flz-ags');

        $form_buffer_started = false;
        try {
            $course_id = absint($atts['course_id']);
            $course = $course_id > 0
                ? $this->get_public_course_by_id($course_id, $school_year)
                : $this->get_public_course_for_detail_page((int) get_queried_object_id(), $school_year);

            if (!$course instanceof FLZ_AGS_Course) {
                return flz_ags_get_frontend_template('registration', array(
                    'heading' => 'AG-Anmeldung',
                    'messages' => array('Bitte die AG-Anmeldung über die Detailseite der jeweiligen AG aufrufen.'),
                    'success' => false,
                    'form_html' => '',
                ));
            }

            $detail_page_id = flz_ags_course_detail_page_id($course);
            if (
                !$this->rendering_embedded_registration
                && $detail_page_id > 0
                && $detail_page_id !== (int) get_queried_object_id()
            ) {
                return flz_ags_get_frontend_template('registration', array(
                    'heading' => 'AG-Anmeldung: ' . (string) $course->title,
                    'messages' => array('Diese AG-Anmeldung ist nur auf der verknüpften AG-Detailseite verfügbar.'),
                    'success' => false,
                    'form_html' => '',
                ));
            }

            $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
            if (
                'post' === $request_method
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Die Nonce wird direkt in handle_frontend_registration() geprüft und liefert dort die fachliche Fehlermeldung.
                && isset($_POST['flz_ags_registration_submit'])
                && $this->is_registration_post_for_course((int) $course->id)
            ) {
                $result = $this->handle_frontend_registration($school_year, (int) $course->id);
                $messages = $result['messages'];
                $success = $result['success'];
            }

            $form_html = '';
            if (!$success) {
                $form_buffer_started = true;
                ob_start();
                $this->render_registration_form($school_year, $course);
                $form_html = (string) ob_get_clean();
                $form_buffer_started = false;
            }

            return flz_ags_get_frontend_template('registration', array(
                'heading' => 'AG-Anmeldung: ' . (string) $course->title,
                'messages' => $messages,
                'success' => $success,
                'form_html' => $form_html,
            ));
        } catch (Throwable $error) {
            if ($form_buffer_started && ob_get_level() > 0) {
                ob_end_clean();
            }
            flz_ags_log_error($error, 'Anzeigen der AG-Anmeldung');
            return '<div class="flz-ags">' . flz_ags_notice('Die AG-Anmeldung kann derzeit nicht geladen werden. Bitte später erneut versuchen.', 'error') . '</div>';
        }
    }

    private function send_registration_confirmation_email(FLZ_AGS_Registration $registration, object $slot): void
    {
        $recipient = sanitize_email((string) $registration->student_email);
        if ($recipient === '' || !is_email($recipient)) {
            throw new UnexpectedValueException('Für die AG-Bestätigungsmail fehlt eine gültige Empfängeradresse.');
        }

        $site_name = sanitize_text_field(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES));
        $admin_email = sanitize_email((string) get_option('admin_email'));
        $detail_url = get_permalink((int) get_queried_object_id());
        $slot_line = flz_ags_weekday_label($slot->weekday)
            . ', '
            . flz_ags_format_time($slot->start_time)
            . '–'
            . flz_ags_format_time($slot->end_time)
            . (!empty($slot->room) ? ', Raum ' . $slot->room : '');

        $subject = 'AG-Anmeldung bestätigt: ' . (string) $slot->title;
        $message = implode("\n", array_filter(array(
            'Hallo,',
            '',
            'die AG-Anmeldung wurde gespeichert.',
            '',
            'Schüler*in: ' . (string) $registration->student_first_name . ' ' . (string) $registration->student_last_name,
            'Klasse: ' . flz_ags_class_label((string) $registration->class_name),
            'AG: ' . (string) $slot->title,
            'Termin: ' . $slot_line,
            'Schuljahr: ' . (string) $registration->school_year,
            '',
            is_string($detail_url) && $detail_url !== '' ? 'Detailseite: ' . $detail_url : '',
            '',
            'Die Anmeldung gilt bis auf Widerruf. Änderungen oder Widerrufe bitte über die Schule veranlassen.',
            '',
            'Viele Grüße',
            $site_name !== '' ? $site_name : 'Tagore-Gymnasium',
        )));
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        if ($admin_email !== '') {
            $headers[] = 'From: ' . ($site_name !== '' ? $site_name : 'Tagore-Gymnasium') . ' <' . $admin_email . '>';
        }

        /**
         * Erlaubt lokale Tests oder spätere Fachanpassungen ohne Änderung der
         * Anmeldelogik. DDEV fängt wp_mail() üblicherweise in Mailpit/MailHog ab.
         */
        $mail = apply_filters(
            'flz_ags_confirmation_mail',
            array(
                'to'      => $recipient,
                'subject' => $subject,
                'message' => $message,
                'headers' => $headers,
            ),
            $registration,
            $slot
        );

        if (!is_array($mail) || empty($mail['to']) || empty($mail['subject']) || empty($mail['message'])) {
            throw new UnexpectedValueException('Die AG-Bestätigungsmail wurde durch einen Filter ungültig konfiguriert.');
        }

        $sent = wp_mail(
            (string) $mail['to'],
            (string) $mail['subject'],
            (string) $mail['message'],
            isset($mail['headers']) && is_array($mail['headers']) ? $mail['headers'] : array()
        );

        if (!$sent) {
            if ($this->mock_registration_confirmation_email($mail, $registration, $slot)) {
                return;
            }

            throw new RuntimeException('wp_mail() meldete einen Fehler beim Senden der AG-Bestätigungsmail.');
        }
    }

    /**
     * Speichert lokale Mock-Mails, wenn die DDEV-Mailzustellung blockiert ist.
     *
     * In DDEV sollte Mailpit normale wp_mail()-Aufrufe abfangen. Falls ein
     * lokales SMTP-Plugin den Versand vorher ablehnt, bleibt der
     * Anmeldeprozess mit diesem lokalen Fallback trotzdem testbar. Außerhalb
     * der lokalen Umgebung ist der Mock standardmäßig aus.
     *
     * @param array<string,mixed> $mail Normalisierte Maildaten.
     */
    private function mock_registration_confirmation_email(array $mail, FLZ_AGS_Registration $registration, object $slot): bool
    {
        $should_mock = wp_get_environment_type() === 'local';
        $should_mock = (bool) apply_filters(
            'flz_ags_mock_confirmation_mail',
            $should_mock,
            $mail,
            $registration,
            $slot
        );

        if (!$should_mock) {
            return false;
        }

        set_transient('flz_ags_mock_confirmation_mail_last', array(
            'created_at' => current_time('mysql'),
            'course_id' => (int) $registration->course_id,
            'slot_id' => (int) $registration->slot_id,
        ), HOUR_IN_SECONDS);
        return true;
    }

    private function render_registration_form(string $school_year, FLZ_AGS_Course $course): void
    {
        $slots = array_filter($this->get_public_slots($school_year), static function ($slot) use ($course) {
            return (int) $slot->course_id === (int) $course->id && !empty($slot->registration_open);
        });

        $posted = array(
            'class_name' => '',
            'student_first_name' => '',
            'student_last_name' => '',
            'student_email' => '',
            'slot_id' => 0,
        );

        if (
            $this->is_registration_post_for_course((int) $course->id)
            && isset($_POST['flz_ags_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flz_ags_nonce'])), 'flz_ags_frontend_registration')
        ) {
            $posted['class_name'] = isset($_POST['class_name']) ? flz_ags_normalize_class_name(sanitize_text_field(wp_unslash($_POST['class_name']))) : '';
            $posted['student_first_name'] = isset($_POST['student_first_name']) ? sanitize_text_field(wp_unslash($_POST['student_first_name'])) : '';
            $posted['student_last_name'] = isset($_POST['student_last_name']) ? sanitize_text_field(wp_unslash($_POST['student_last_name'])) : '';
            $posted['student_email'] = isset($_POST['student_email']) ? sanitize_email(wp_unslash($_POST['student_email'])) : '';
            $posted['slot_id'] = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        }

        $slot_choices = array();
        foreach ($slots as $slot) {
            $taken = $this->count_active_registrations((int) $slot->id);
            $max = (int) $slot->max_participants;
            $is_full = $max > 0 && $taken >= $max;
            $free_label = $max > 0 ? max(0, $max - $taken) . ' freie Plätze' : 'keine Begrenzung hinterlegt';
            $time_label = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time);
            $target_label = $slot->only_grade_7 ? 'nur Klasse 7' : flz_ags_allowed_grades_label((string) $slot->allowed_grades);

            $slot_choices[] = array(
                'name'      => 'slot_id',
                'value'     => $slot->id,
                'checked'   => $posted['slot_id'] === (int) $slot->id,
                'required'  => true,
                'disabled'  => $is_full,
                'class'     => 'flz-ags-slot-choice flz-ags-slot-option',
                'attrs'     => array(
                    'data-flz-ags-filter-item' => true,
                    'data-weekday'             => $slot->weekday,
                    'data-only-grade-7'        => (int) $slot->only_grade_7,
                    'data-allowed-grades'      => $slot->allowed_grades,
                    'data-full'                => $is_full ? '1' : '0',
                ),
                'image_url' => flz_ags_course_image_url($slot->image_url ?? ''),
                'image_alt' => (string) $slot->title,
                'title'     => $time_label,
                'kicker'    => $target_label,
                'meta'      => array('Zeit' => $time_label, 'Plätze' => $free_label),
                'badge'     => $is_full ? 'ausgebucht' : '',
            );
        }

        flz_ags_render_frontend_template('registration-form', array(
            'school_year' => $school_year,
            'course' => $course,
            'posted' => $posted,
            'slot_choices' => $slot_choices,
        ));
    }

    private function handle_frontend_registration(string $school_year, int $course_id): array
    {
        $messages = array();
        if (!isset($_POST['flz_ags_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flz_ags_nonce'])), 'flz_ags_frontend_registration')) {
            return array('success' => false, 'messages' => array('Die Anmeldung konnte aus Sicherheitsgründen nicht verarbeitet werden. Bitte Formular neu laden.'));
        }

        $posted_course_id = isset($_POST['flz_ags_course_id']) ? absint($_POST['flz_ags_course_id']) : 0;
        $class_name = isset($_POST['class_name']) ? flz_ags_normalize_class_name(sanitize_text_field(wp_unslash($_POST['class_name']))) : '';
        $first_name = isset($_POST['student_first_name']) ? sanitize_text_field(wp_unslash($_POST['student_first_name'])) : '';
        $last_name = isset($_POST['student_last_name']) ? sanitize_text_field(wp_unslash($_POST['student_last_name'])) : '';
        $student_email = isset($_POST['student_email']) ? sanitize_email(wp_unslash($_POST['student_email'])) : '';
        $slot_id = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        $consent = isset($_POST['consent_privacy']) ? 1 : 0;

        if ($posted_course_id !== $course_id) {
            $messages[] = 'Die AG-Zuordnung der Anmeldung ist ungültig. Bitte die Detailseite neu laden.';
        }
        if (!flz_ags_is_valid_class($class_name)) {
            $messages[] = 'Bitte eine gültige Klasse auswählen.';
        }
        if ($first_name === '' || $last_name === '') {
            $messages[] = 'Bitte Vor- und Nachname der Schülerin/des Schülers eintragen.';
        }
        if ($student_email === '' || !is_email($student_email)) {
            $messages[] = 'Bitte eine gültige E-Mail-Adresse der Schülerin/des Schülers für die Bestätigung eintragen.';
        }
        if ($slot_id <= 0) {
            $messages[] = 'Bitte einen AG-Slot auswählen.';
        }
        if (!$consent) {
            $messages[] = 'Die Datenschutzhinweise müssen bestätigt werden.';
        }

        if (!empty($messages)) {
            return array('success' => false, 'messages' => $messages);
        }

        try {
            $result = FLZ_AGS_Model::transaction(
                function () use ($school_year, $course_id, $class_name, $first_name, $last_name, $student_email, $slot_id, $consent): array {
                    // Die Sperre serialisiert Kapazitätsprüfungen und Insert für diesen Termin.
                    $slot = $this->get_slot_with_course($slot_id, true);
                    if (
                        !$slot
                        || (int) $slot->course_id !== $course_id
                        || $slot->school_year !== $school_year
                        || empty($slot->is_active)
                        || empty($slot->course_active)
                        || empty($slot->course_visible)
                        || empty($slot->registration_open)
                    ) {
                        return array('success' => false, 'messages' => array('Der gewählte AG-Slot ist nicht verfügbar.'));
                    }

                    if (!flz_ags_grade_is_allowed($class_name, (string) $slot->allowed_grades, !empty($slot->only_grade_7))) {
                        return array('success' => false, 'messages' => array('Dieser AG-Slot ist für den gewählten Jahrgang nicht freigegeben.'));
                    }

                    try {
                        $this->assert_slot_capacity_available($slot);
                    } catch (UnexpectedValueException $error) {
                        return array('success' => false, 'messages' => array($error->getMessage()));
                    }

                    $duplicate_criteria = array(
                        'school_year' => $school_year,
                        'class_name' => $class_name,
                        'student_first_name' => $first_name,
                        'student_last_name' => $last_name,
                        'status' => 'active',
                    );
                    if (flz_ags_multiple_registrations_enabled()) {
                        $duplicate_criteria['course_id'] = (int) $slot->course_id;
                    }
                    $duplicate = FLZ_AGS_Registration::count_by($duplicate_criteria);
                    if ($duplicate > 0) {
                        return array(
                            'success' => false,
                            'messages' => array('Für diese Schüler*in existiert in diesem Schuljahr bereits eine aktive AG-Anmeldung. Änderungen bitte über die Schule veranlassen.'),
                        );
                    }

                    $now = current_time('mysql');
                    $registration = new FLZ_AGS_Registration(array(
                        'course_id' => (int) $slot->course_id,
                        'slot_id' => (int) $slot->id,
                        'school_year' => $school_year,
                        'class_name' => $class_name,
                        'grade_key' => flz_ags_extract_grade_key($class_name),
                        'student_first_name' => $first_name,
                        'student_last_name' => $last_name,
                        'student_email' => $student_email,
                        'status' => 'active',
                        'consent_privacy' => $consent,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
                    $registration->save();

                    return array(
                        'success' => true,
                        'messages' => array('Die AG-Anmeldung wurde gespeichert. Eine Bestätigung wurde per E-Mail versendet.'),
                        'registration' => $registration,
                        'slot' => $slot,
                    );
                },
                'Prüfen und Speichern einer AG-Anmeldung'
            );

            if (
                !empty($result['success'])
                && isset($result['registration'], $result['slot'])
                && $result['registration'] instanceof FLZ_AGS_Registration
                && is_object($result['slot'])
            ) {
                try {
                    $this->send_registration_confirmation_email($result['registration'], $result['slot']);
                } catch (Throwable $mail_error) {
                    flz_ags_log_error($mail_error, 'Senden der AG-Bestätigungsmail nach gespeicherter Anmeldung');
                    $result['messages'] = array(
                        'Die AG-Anmeldung wurde gespeichert, aber die Bestätigungsmail konnte nicht versendet werden. Bitte kontaktieren Sie die Schule.',
                    );
                }
            }

            unset($result['registration'], $result['slot']);
            return $result;
        } catch (Throwable $error) {
            if (flz_ags_has_active_registration_conflict($error)) {
                return array(
                    'success' => false,
                    'messages' => array('Für diese Schüler*in existiert in diesem Schuljahr bereits eine aktive AG-Anmeldung. Änderungen bitte über die Schule veranlassen.'),
                );
            }
            flz_ags_log_error($error, 'Speichern einer öffentlichen AG-Anmeldung');
            return array(
                'success' => false,
                'messages' => array('Die Anmeldung konnte wegen eines technischen Fehlers nicht gespeichert werden. Bitte später erneut versuchen.'),
            );
        }
    }

    public function render_admin_registrations_page(): void
    {
        $this->assert_registration_read_permission();

        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();

        $registrations = array();
        $slots = array();
        $courses = array();
        $load_failed = false;
        try {
            if ($this->is_manager()) {
                $registrations = FLZ_AGS_Registration::find_for_admin($school_year, 'all');
                $slots = $this->get_public_slots($school_year);
            } else {
                $registrations = FLZ_AGS_Registration::find_for_leader($school_year, get_current_user_id());
                $courses = FLZ_AGS_Course::find_for_leader($school_year, get_current_user_id());
            }
        } catch (Throwable $error) {
            $load_failed = true;
            flz_ags_log_error($error, 'Laden der AG-Anmeldungen im Backend');
        }

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>' . esc_html($this->is_manager() ? 'AG-Anmeldungen' : 'Meine AG-Anmeldungen') . '</h1>';
        if (isset($_GET['updated'])) {
            echo wp_kses_post(flz_ags_notice('Anmeldung aktualisiert.'));
        }
        $csv_report = $this->is_manager() ? get_transient($this->registration_csv_report_key()) : null;
        if ($this->is_manager() && is_array($csv_report)) {
            delete_transient($this->registration_csv_report_key());
        } else {
            $csv_report = null;
        }
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }
        if ($load_failed) {
            echo wp_kses_post(
                flz_ags_notice('Die Anmeldungen konnten nicht geladen werden. Details stehen im Serverprotokoll.', 'error')
            );
        }

        flz_ags_render_backend_template($this->is_manager() ? 'registrations' : 'leader-registrations', array(
            'school_year' => $school_year,
            'registrations' => $registrations,
            'slots' => $slots,
            'courses' => $courses,
            'csv_report' => $csv_report,
        ));
        echo '</div>';
    }

    public function handle_update_registration(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_update_registration');

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_key(wp_unslash($_POST['new_status'])) : '';
        $target_course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $target_slot_id = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        $class_name = isset($_POST['class_name'])
            ? flz_ags_normalize_class_name(sanitize_text_field(wp_unslash($_POST['class_name'])))
            : '';
        $first_name = isset($_POST['student_first_name']) ? sanitize_text_field(wp_unslash($_POST['student_first_name'])) : '';
        $last_name = isset($_POST['student_last_name']) ? sanitize_text_field(wp_unslash($_POST['student_last_name'])) : '';
        $student_email = isset($_POST['student_email']) ? sanitize_email(wp_unslash($_POST['student_email'])) : '';

        if (
            $registration_id <= 0
            || $target_course_id <= 0
            || $target_slot_id <= 0
            || !array_key_exists($new_status, flz_ags_status_labels())
            || !flz_ags_is_valid_class($class_name)
            || $first_name === ''
            || $last_name === ''
            || !is_email($student_email)
        ) {
            wp_die(esc_html__('Ungültige Anfrage.', 'flz-ags'));
        }

        try {
            FLZ_AGS_Model::transaction(
                function () use ($registration_id, $new_status, $target_course_id, $target_slot_id, $class_name, $first_name, $last_name, $student_email): void {
                    $registration = FLZ_AGS_Registration::get_by_id($registration_id);
                    if (!$registration instanceof FLZ_AGS_Registration) {
                        throw new UnexpectedValueException('Die zu aktualisierende AG-Anmeldung wurde nicht gefunden.');
                    }

                    $slot = $this->get_slot_with_course($target_slot_id, true);
                    if (
                        !$slot
                        || (int) $slot->course_id !== $target_course_id
                        || $slot->school_year !== $registration->school_year
                        || empty($slot->is_active)
                        || empty($slot->course_active)
                    ) {
                        throw new UnexpectedValueException('Der gewählte AG-Slot ist nicht verfügbar.');
                    }
                    if (!flz_ags_grade_is_allowed($class_name, (string) $slot->allowed_grades, !empty($slot->only_grade_7))) {
                        throw new UnexpectedValueException('Der gewählte AG-Slot ist für die angegebene Klasse nicht freigegeben.');
                    }

                    if ('active' === $new_status) {
                        $active = flz_ags_multiple_registrations_enabled()
                            ? FLZ_AGS_Registration::find_active_for_student_in_course(
                                (string) $registration->school_year,
                                (int) $slot->course_id,
                                $class_name,
                                $first_name,
                                $last_name
                            )
                            : FLZ_AGS_Registration::find_active_for_student(
                                (string) $registration->school_year,
                                $class_name,
                                $first_name,
                                $last_name
                            );
                        if ($active instanceof FLZ_AGS_Registration && $active->id !== $registration->id) {
                            throw new UnexpectedValueException('Für diese Schüler*in existiert in diesem Schuljahr bereits eine andere aktive AG-Anmeldung.');
                        }
                    }

                    if (
                        'active' === $new_status
                        && ('active' !== $registration->status || (int) $slot->id !== (int) $registration->slot_id)
                    ) {
                        $this->assert_slot_capacity_available($slot);
                    }

                    $registration->course_id = (int) $slot->course_id;
                    $registration->slot_id = (int) $slot->id;
                    $registration->class_name = $class_name;
                    $registration->grade_key = flz_ags_extract_grade_key($class_name);
                    $registration->student_first_name = $first_name;
                    $registration->student_last_name = $last_name;
                    $registration->student_email = $student_email;
                    $registration->status = $new_status;
                    $registration->updated_at = current_time('mysql');
                    if ($new_status === 'withdrawn') {
                        $registration->withdrawn_at = current_time('mysql');
                    } else {
                        $registration->withdrawn_at = null;
                        $registration->withdrawn_reason = null;
                    }
                    $registration->save();
                },
                'Aktualisieren des Status einer AG-Anmeldung'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Aktualisieren einer AG-Anmeldung',
                'update-registration',
                array('page' => 'flz-ags-registrations')
            );
        }

        flz_ags_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-registrations', 'updated' => 1)));
    }

    /**
     * Streamt die AGs des gewählten Schuljahrs einschließlich ihrer Termine.
     */
    public function handle_export_courses_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_export_courses_csv');

        $school_year = isset($_GET['school_year'])
            ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year'])))
            : flz_ags_current_school_year();
        $include_slots = isset($_GET['include_slots']);

        try {
            $courses = $this->get_courses($school_year, false);
            $rows = FLZ_AGS_Course_CSV::export_rows(
                $courses,
                fn(int $course_id): array => $include_slots ? $this->get_course_slots($course_id, true) : array(),
                static function (int $detail_page_id): string {
                    $path = get_page_uri($detail_page_id);
                    if (!is_string($path) || '' === trim($path)) {
                        throw new UnexpectedValueException('Eine verknüpfte AG-Detailseite besitzt keinen exportierbaren Seitenpfad.');
                    }
                    return $path;
                }
            );
            flz_wpdb_objects_send_csv_download(
                FLZ_AGS_Course_CSV::header(),
                $rows,
                'flz-ags-' . sanitize_file_name($school_year) . ($include_slots ? '-mit-slots' : '-ohne-slots') . '.csv'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Erstellen des AG-/Slot-CSV-Exports',
                'course-export',
                array('page' => 'flz-ags', 'school_year' => $school_year)
            );
        }
    }

    /**
     * Importiert alle brauchbaren Teile einer versionierten AG-/Slot-CSV-Datei.
     */
    public function handle_import_courses_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_import_courses_csv');

        $is_import = isset($_POST['submit_csv']);
        $school_year_mode = isset($_POST['school_year_mode'])
            ? sanitize_key(wp_unslash($_POST['school_year_mode']))
            : 'preserve';
        if (!in_array($school_year_mode, array('preserve', 'replace'), true)) {
            $school_year_mode = 'preserve';
        }
        $target_school_year = isset($_POST['target_school_year'])
            ? sanitize_text_field(wp_unslash($_POST['target_school_year']))
            : flz_ags_default_school_year();
        $school_year = isset($_POST['school_year'])
            ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year'])))
            : flz_ags_current_school_year();
        if (!$is_import) {
            wp_die(esc_html__('Ungültige CSV-Aktion.', 'flz-ags'));
        }

        try {
            $this->assert_course_csv_upload();
            $parsed = FLZ_AGS_Course_CSV::parse_tolerant(
                flz_wpdb_objects_read_uploaded_csv(
                    'course-csv',
                    'Einlesen der AG-/Slot-CSV-Datei',
                    false
                )
            );
            if ('replace' === $school_year_mode) {
                $parsed = FLZ_AGS_Course_CSV::remap_school_year($parsed, $target_school_year);
            }
            $plan = null;
            FLZ_AGS_Model::transaction(
                function () use ($parsed, &$plan): void {
                    $plan = $this->build_course_csv_import_plan($parsed['courses'], $parsed['warnings']);
                    $this->apply_course_csv_import_plan($plan);
                },
                'Importieren von AGs und Terminen aus CSV'
            );
            if (!is_array($plan)) {
                throw new RuntimeException('Der AG-CSV-Import konnte keinen Verarbeitungsbericht erstellen.');
            }
            $report = array(
                'courses_created' => (int) $plan['courses_created'],
                'courses_updated' => (int) $plan['courses_updated'],
                'slots_created' => (int) $plan['slots_created'],
                'slots_updated' => (int) $plan['slots_updated'],
                'warnings' => array_values(array_map('strval', (array) $plan['warnings'])),
                'school_year_note' => 'replace' === $school_year_mode
                    ? 'Alle importierten AGs wurden dem Schuljahr ' . $target_school_year . ' zugeordnet.'
                    : 'Die Schuljahre aus der CSV wurden beibehalten.',
            );
            set_transient($this->course_csv_report_key(), $report, 10 * MINUTE_IN_SECONDS);
            flz_ags_safe_redirect(flz_ags_admin_url(array(
                'page' => 'flz-ags',
                'school_year' => 'replace' === $school_year_mode ? $target_school_year : $school_year,
                'csv_imported' => 1,
            )));
        } catch (Throwable $error) {
            if ($error instanceof UnexpectedValueException || $error instanceof InvalidArgumentException) {
                set_transient($this->course_csv_notice_key(), $error->getMessage(), 5 * MINUTE_IN_SECONDS);
            }
            $this->redirect_admin_error(
                $error,
                'Prüfen oder Importieren der AG-/Slot-CSV-Datei',
                'course-import',
                array('page' => 'flz-ags', 'school_year' => $school_year)
            );
        }
    }

    /**
     * @param array<string,array{course:array<string,mixed>,slots:array<int,array<string,mixed>>}> $courses
     * @return array<string,mixed>
     */
    private function build_course_csv_import_plan(array $courses, array $warnings = array()): array
    {
        $existing_by_key = array();
        $ambiguous_course_keys = array();
        $years = array_values(array_unique(array_map(
            static fn(array $item): string => (string) $item['course']['school_year'],
            array_values($courses)
        )));
        foreach ($years as $year) {
            foreach ($this->get_courses($year, false) as $existing_course) {
                $key = FLZ_AGS_Course_CSV::course_key((string) $existing_course->school_year, (string) $existing_course->slug);
                if (isset($existing_by_key[$key])) {
                    unset($existing_by_key[$key]);
                    $ambiguous_course_keys[$key] = true;
                    continue;
                }
                if (!isset($ambiguous_course_keys[$key])) {
                    $existing_by_key[$key] = $existing_course;
                }
            }
        }

        $plan = array(
            'items' => array(),
            'courses_created' => 0,
            'courses_updated' => 0,
            'slots_created' => 0,
            'slots_updated' => 0,
            'warnings' => $warnings,
        );

        foreach ($courses as $key => $item) {
            if (isset($ambiguous_course_keys[$key])) {
                $plan['warnings'][] = 'AG „' . $key . '“ wurde übersprungen: Im vorhandenen Bestand gibt es diesen Schlüssel mehrfach.';
                continue;
            }
            $course_data = $item['course'];
            $detail_page_path = (string) $course_data['detail_page_path'];
            unset($course_data['detail_page_path']);
            $detail_page = '' !== $detail_page_path ? get_page_by_path($detail_page_path, OBJECT, 'page') : null;
            if ('' !== $detail_page_path && !$detail_page instanceof WP_Post) {
                $plan['warnings'][] = 'AG „' . $key . '“ wurde ohne Detailseite importiert: Der Seitenpfad „' . $detail_page_path . '“ wurde nicht gefunden.';
            }
            try {
                $detail_page_id = $detail_page instanceof WP_Post
                    ? $this->validate_detail_page_id((int) $detail_page->ID)
                    : 0;
            } catch (UnexpectedValueException $error) {
                $detail_page_id = 0;
                $plan['warnings'][] = 'AG „' . $key . '“ wurde ohne Detailseite importiert: Die gefundene Seite ist nicht verwendbar.';
            }
            if (!empty($course_data['registration_open']) && $detail_page_id > 0 && get_post_status($detail_page_id) !== 'publish') {
                $detail_page_id = 0;
                $plan['warnings'][] = 'AG „' . $key . '“ wurde ohne Detailseite importiert: Die gefundene Seite ist nicht veröffentlicht.';
            }
            $course_data['detail_page_id'] = $detail_page_id;

            $existing_course = $existing_by_key[$key] ?? null;
            $existing_slots = $existing_course instanceof FLZ_AGS_Course
                ? $this->get_course_slots((int) $existing_course->id, true)
                : array();
            $existing_slots_by_key = array();
            $ambiguous_slot_keys = array();
            foreach ($existing_slots as $existing_slot) {
                $slot_key = $this->course_csv_slot_key($existing_slot);
                if (isset($existing_slots_by_key[$slot_key])) {
                    unset($existing_slots_by_key[$slot_key]);
                    $ambiguous_slot_keys[$slot_key] = true;
                    continue;
                }
                if (!isset($ambiguous_slot_keys[$slot_key])) {
                    $existing_slots_by_key[$slot_key] = $existing_slot;
                }
            }

            $slot_plans = array();
            foreach ($item['slots'] as $slot_data) {
                $slot_key = $this->course_csv_slot_key($slot_data);
                if (isset($ambiguous_slot_keys[$slot_key])) {
                    $plan['warnings'][] = 'Ein Termin der AG „' . $key . '“ wurde übersprungen: Im vorhandenen Bestand gibt es Wochentag, Zeiten und Raum mehrfach.';
                    continue;
                }
                $existing_slot = $existing_slots_by_key[$slot_key] ?? null;
                $slot_plans[] = array('data' => $slot_data, 'existing' => $existing_slot);
                $existing_slot instanceof FLZ_AGS_Slot ? ++$plan['slots_updated'] : ++$plan['slots_created'];
            }

            $existing_course instanceof FLZ_AGS_Course ? ++$plan['courses_updated'] : ++$plan['courses_created'];
            $plan['items'][] = array(
                'data' => $course_data,
                'existing' => $existing_course,
                'slots' => $slot_plans,
            );
        }

        return $plan;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function apply_course_csv_import_plan(array $plan): void
    {
        $now = current_time('mysql');
        foreach ($plan['items'] as $item) {
            $course = $item['existing'] instanceof FLZ_AGS_Course ? $item['existing'] : new FLZ_AGS_Course();
            foreach ($item['data'] as $property => $value) {
                $course->{$property} = $value;
            }
            $course->updated_at = $now;
            if ($course->created_at === null) {
                $course->created_at = $now;
            }
            $course->save();

            foreach ($item['slots'] as $slot_item) {
                $slot = $slot_item['existing'] instanceof FLZ_AGS_Slot ? $slot_item['existing'] : new FLZ_AGS_Slot();
                foreach ($slot_item['data'] as $property => $value) {
                    $slot->{$property} = $value;
                }
                $slot->course_id = (int) $course->id;
                $slot->school_year = (string) $course->school_year;
                $slot->updated_at = $now;
                if ($slot->created_at === null) {
                    $slot->created_at = $now;
                }
                $slot->save();
            }
        }
    }

    private function course_csv_slot_key($slot): string
    {
        $value = static function ($source, string $field) {
            return is_array($source) ? ($source[$field] ?? '') : ($source->{$field} ?? '');
        };
        return implode('|', array(
            (int) $value($slot, 'weekday'),
            (string) $value($slot, 'start_time'),
            (string) $value($slot, 'end_time'),
            strtolower((string) $value($slot, 'room')),
        ));
    }

    private function course_csv_notice_key(): string
    {
        return 'flz_ags_course_csv_notice_' . get_current_user_id();
    }

    private function assert_course_csv_upload(): void
    {
        // Nonce und Berechtigung wurden im aufrufenden Admin-Handler bereits geprüft.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- PHP stellt Fehlercode und temporären Uploadpfad bereit; gelesen wird die Datei anschließend durch den zentralen CSV-Helper.
        $upload_error = isset($_FILES['course-csv']['error']) ? (int) $_FILES['course-csv']['error'] : UPLOAD_ERR_NO_FILE;
        if (UPLOAD_ERR_OK === $upload_error) {
            return;
        }

        $messages = array(
            UPLOAD_ERR_INI_SIZE => 'Die CSV-Datei ist größer als die serverseitig erlaubte Uploadgröße.',
            UPLOAD_ERR_FORM_SIZE => 'Die CSV-Datei ist größer als die im Formular erlaubte Uploadgröße.',
            UPLOAD_ERR_PARTIAL => 'Die CSV-Datei wurde nur teilweise hochgeladen. Bitte erneut versuchen.',
            UPLOAD_ERR_NO_FILE => 'Bitte zuerst eine CSV-Datei auswählen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Der Server kann derzeit keine Uploads zwischenspeichern.',
            UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die hochgeladene CSV-Datei nicht zwischenspeichern.',
            UPLOAD_ERR_EXTENSION => 'Der CSV-Upload wurde durch eine Servererweiterung abgebrochen.',
        );
        throw new UnexpectedValueException($messages[$upload_error] ?? 'Der CSV-Upload ist mit einem unbekannten Fehler fehlgeschlagen.');
    }

    private function course_csv_report_key(): string
    {
        return 'flz_ags_course_csv_report_' . get_current_user_id();
    }

    public function handle_delete_old_registrations(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_delete_old_registrations');
        if (!isset($_POST['confirm_delete_old_registrations'])) {
            wp_die(esc_html__('Bitte bestätigen Sie die dauerhafte Löschung alter Anmeldungen.', 'flz-ags'));
        }

        try {
            $deleted = FLZ_AGS_Model::transaction(
                fn(): int => $this->delete_registrations_before(flz_ags_registration_retention_cutoff()),
                'Manuelles Löschen alter AG-Anmeldungen'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Manuelles Löschen alter AG-Anmeldungen',
                'delete-old-registrations',
                array('page' => 'flz-ags-settings')
            );
        }

        flz_ags_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-settings', 'deleted' => $deleted)));
    }

    public function handle_scheduled_registration_cleanup(): void
    {
        if (!(bool) get_option('flz_ags_registration_retention_enabled', 0)) {
            return;
        }
        try {
            FLZ_AGS_Model::transaction(
                fn(): int => $this->delete_registrations_before(flz_ags_registration_retention_cutoff()),
                'Automatisches Löschen alter AG-Anmeldungen'
            );
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Automatisches Löschen alter AG-Anmeldungen');
        }
    }

    private function delete_registrations_before(string $cutoff): int
    {
        $deleted = 0;
        foreach (FLZ_AGS_Registration::find_created_before($cutoff) as $registration) {
            $deleted += $registration->delete();
        }
        return $deleted;
    }

    /**
     * Stellt möglichst viele Anmeldungen aus einem portablen Backup wieder her.
     * Nicht auflösbare Referenzen werden ausgelassen; der akzeptierte Teil wird atomar gespeichert.
     */
    public function handle_import_registrations_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_import_registrations_csv');
        $school_year = isset($_POST['school_year'])
            ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year'])))
            : flz_ags_current_school_year();
        if (!isset($_POST['confirm_registration_import'])) {
            wp_die(esc_html__('Bitte bestätigen Sie, dass die CSV personenbezogene Anmeldungsdaten enthält.', 'flz-ags'));
        }

        try {
            $this->assert_registration_csv_upload();
            $parsed = FLZ_AGS_Registration_CSV::parse_tolerant(
                flz_wpdb_objects_read_uploaded_csv(
                    'registration-csv',
                    'Einlesen des Anmeldungs-Backups',
                    false
                )
            );
            $report = FLZ_AGS_Model::transaction(
                fn(): array => $this->apply_registration_csv_import($parsed),
                'Importieren von AG-Anmeldungen aus CSV'
            );
            set_transient($this->registration_csv_report_key(), $report, 10 * MINUTE_IN_SECONDS);
            flz_ags_safe_redirect(flz_ags_admin_url(array(
                'page' => 'flz-ags-registrations',
                'school_year' => $school_year,
                'csv_imported' => 1,
            )));
        } catch (Throwable $error) {
            if ($error instanceof UnexpectedValueException || $error instanceof InvalidArgumentException) {
                set_transient($this->registration_csv_report_key(), array(
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'warnings' => array($error->getMessage()),
                    'error' => true,
                ), 10 * MINUTE_IN_SECONDS);
            }
            $this->redirect_admin_error(
                $error,
                'Importieren des Anmeldungs-Backups',
                'registration-import',
                array('page' => 'flz-ags-registrations', 'school_year' => $school_year)
            );
        }
    }

    /** @param array{registrations:array<int,array<string,mixed>>,warnings:array<int,string>} $parsed */
    private function apply_registration_csv_import(array $parsed): array
    {
        $report = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'warnings' => array_values(array_map('strval', $parsed['warnings'])),
        );
        $now = current_time('mysql');

        foreach ($parsed['registrations'] as $data) {
            $line = (int) $data['source_line'];
            try {
                $course = FLZ_AGS_Course::find_by_school_year_and_slug(
                    (string) $data['school_year'],
                    sanitize_title((string) $data['course_slug'])
                );
                if (!$course instanceof FLZ_AGS_Course) {
                    ++$report['skipped'];
                    $report['warnings'][] = 'Zeile ' . $line . ' wurde übersprungen: Die AG „' . $data['course_slug'] . '“ existiert in ' . $data['school_year'] . ' nicht.';
                    continue;
                }
                $slot = FLZ_AGS_Slot::find_for_backup_reference(
                    (int) $course->id,
                    (string) $data['school_year'],
                    (int) $data['slot_weekday'],
                    (string) $data['slot_start_time'],
                    (string) $data['slot_end_time'],
                    sanitize_text_field((string) $data['slot_room'])
                );
                if (!$slot instanceof FLZ_AGS_Slot) {
                    ++$report['skipped'];
                    $report['warnings'][] = 'Zeile ' . $line . ' wurde übersprungen: Der zugehörige Termin der AG „' . $course->title . '“ wurde nicht gefunden.';
                    continue;
                }

                $slot = $this->get_slot_with_course((int) $slot->id, true);
                if (!$slot instanceof FLZ_AGS_Slot) {
                    throw new UnexpectedValueException('Der zugehörige AG-Slot wurde nicht gefunden.');
                }

                $record = array(
                    'course_id' => (int) $course->id,
                    'slot_id' => (int) $slot->id,
                    'school_year' => (string) $data['school_year'],
                    'class_name' => sanitize_text_field((string) $data['class_name']),
                    'grade_key' => sanitize_key((string) $data['grade_key']),
                    'student_first_name' => sanitize_text_field((string) $data['student_first_name']),
                    'student_last_name' => sanitize_text_field((string) $data['student_last_name']),
                    'student_email' => sanitize_email((string) $data['student_email']),
                    'status' => sanitize_key((string) $data['status']),
                    'withdrawn_at' => '' !== $data['withdrawn_at'] ? (string) $data['withdrawn_at'] : null,
                    'withdrawn_reason' => sanitize_textarea_field((string) $data['withdrawn_reason']),
                    'consent_privacy' => (int) $data['consent_privacy'],
                    'created_at' => '' !== $data['created_at'] ? (string) $data['created_at'] : $now,
                    'updated_at' => '' !== $data['updated_at'] ? (string) $data['updated_at'] : $now,
                );
                $existing = FLZ_AGS_Registration::find_backup_match($record);
                if ('active' === $record['status']) {
                    $active = flz_ags_multiple_registrations_enabled()
                        ? FLZ_AGS_Registration::find_active_for_student_in_course(
                            $record['school_year'],
                            (int) $record['course_id'],
                            $record['class_name'],
                            $record['student_first_name'],
                            $record['student_last_name']
                        )
                        : FLZ_AGS_Registration::find_active_for_student(
                            $record['school_year'],
                            $record['class_name'],
                            $record['student_first_name'],
                            $record['student_last_name']
                        );
                    if ($active instanceof FLZ_AGS_Registration && (!$existing instanceof FLZ_AGS_Registration || $active->id !== $existing->id)) {
                        ++$report['skipped'];
                        $report['warnings'][] = 'Zeile ' . $line . ' wurde übersprungen: Für diese Schüler*in besteht bereits eine andere aktive Anmeldung im Schuljahr.';
                        continue;
                    }

                    if (!$existing instanceof FLZ_AGS_Registration || 'active' !== $existing->status) {
                        $this->assert_slot_capacity_available($slot);
                    }
                }

                $registration = $existing instanceof FLZ_AGS_Registration ? $existing : new FLZ_AGS_Registration();
                foreach ($record as $property => $value) {
                    $registration->{$property} = $value;
                }
                $registration->save();
                $existing instanceof FLZ_AGS_Registration ? ++$report['updated'] : ++$report['created'];
            } catch (UnexpectedValueException $error) {
                ++$report['skipped'];
                $report['warnings'][] = 'Zeile ' . $line . ' wurde übersprungen: ' . $error->getMessage();
            }
        }
        return $report;
    }

    private function assert_registration_csv_upload(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fehlercode und temporärer Uploadpfad stammen von PHP; der zentrale CSV-Helper liest die Datei.
        $upload_error = isset($_FILES['registration-csv']['error']) ? (int) $_FILES['registration-csv']['error'] : UPLOAD_ERR_NO_FILE;
        if (UPLOAD_ERR_OK === $upload_error) {
            return;
        }
        $messages = array(
            UPLOAD_ERR_INI_SIZE => 'Die Anmeldungs-CSV ist größer als die serverseitig erlaubte Uploadgröße.',
            UPLOAD_ERR_FORM_SIZE => 'Die Anmeldungs-CSV ist größer als die im Formular erlaubte Uploadgröße.',
            UPLOAD_ERR_PARTIAL => 'Die Anmeldungs-CSV wurde nur teilweise hochgeladen. Bitte erneut versuchen.',
            UPLOAD_ERR_NO_FILE => 'Bitte zuerst eine Anmeldungs-CSV auswählen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Der Server kann derzeit keine Uploads zwischenspeichern.',
            UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die hochgeladene CSV-Datei nicht zwischenspeichern.',
            UPLOAD_ERR_EXTENSION => 'Der CSV-Upload wurde durch eine Servererweiterung abgebrochen.',
        );
        throw new UnexpectedValueException($messages[$upload_error] ?? 'Der Anmeldungs-CSV-Upload ist mit einem unbekannten Fehler fehlgeschlagen.');
    }

    private function registration_csv_report_key(): string
    {
        return 'flz_ags_registration_csv_report_' . get_current_user_id();
    }

    public function handle_export_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_export_csv');

        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();

        try {
            $rows = FLZ_AGS_Registration::find_for_admin($school_year, 'all', true);
            flz_wpdb_objects_send_csv_download(
                FLZ_AGS_Registration_CSV::header(),
                FLZ_AGS_Registration_CSV::export_rows($rows),
                'flz-ag-anmeldungen-backup-' . sanitize_file_name($school_year) . '.csv'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Erstellen des AG-CSV-Exports',
                'export',
                array('page' => 'flz-ags-registrations', 'school_year' => $school_year)
            );
        }
    }
}
