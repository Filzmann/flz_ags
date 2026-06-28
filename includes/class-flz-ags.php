<?php

defined('ABSPATH') || exit;

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
        add_action('admin_post_flz_ags_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_flz_ags_update_registration', array($this, 'handle_update_registration'));
        add_action('admin_post_flz_ags_export_csv', array($this, 'handle_export_csv'));
        add_action('admin_post_flz_ags_install_demo', array($this, 'handle_install_demo'));
        add_action('wp_ajax_flz_ags_search_detail_pages', array($this, 'ajax_search_detail_pages'));
        add_action('wp_ajax_flz_ags_create_detail_page', array($this, 'ajax_create_detail_page'));

        add_shortcode('flz_ag_liste', array($this, 'shortcode_list'));
        add_shortcode('flz_ag_anmeldung', array($this, 'shortcode_registration'));

        add_action('init', array($this, 'register_blocks'));
        add_filter('the_content', array($this, 'append_registration_to_detail_page'));
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
    }

    public function register_blocks(): void
    {
        if (!function_exists('flz_ui_register_shortcode_block')) {
            return;
        }

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
                    'default' => '',
                ),
            ),
            'fields' => array(
                'school_year' => array(
                    'label' => 'Schuljahr',
                    'description' => 'Optional, z. B. 2026/2027. Leer verwendet das aktuelle AG-Schuljahr.',
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
                    'default' => '',
                ),
                'course_id' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
            'fields' => array(
                'school_year' => array(
                    'label' => 'Schuljahr',
                    'description' => 'Optional, z. B. 2026/2027. Leer verwendet das aktuelle AG-Schuljahr.',
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
        wp_register_style('flz-ags', FLZ_AGS_URL . 'assets/css/flz-ags.css', array('flz-ui-components'), FLZ_AGS_VERSION);
        wp_register_script('flz-ags', FLZ_AGS_URL . 'assets/js/flz-ags.js', array('flz-ui-components'), FLZ_AGS_VERSION, true);
    }

    public function register_admin_assets(string $hook): void
    {
        if (strpos($hook, 'flz-ags') === false) {
            return;
        }

        wp_enqueue_style('flz-ags-admin', FLZ_AGS_URL . 'assets/css/flz-ags.css', array('flz-ui-components'), FLZ_AGS_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('flz-ags-admin', FLZ_AGS_URL . 'assets/js/flz-ags-admin.js', array('jquery'), FLZ_AGS_VERSION, true);
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

        add_menu_page(
            'FLZ AGs',
            'FLZ AGs',
            $capability,
            'flz-ags',
            array($this, 'render_admin_courses_page'),
            'dashicons-groups',
            26
        );

        add_submenu_page('flz-ags', 'AGs', 'AGs', $capability, 'flz-ags', array($this, 'render_admin_courses_page'));
        add_submenu_page('flz-ags', 'Anmeldungen', 'Anmeldungen', $capability, 'flz-ags-registrations', array($this, 'render_admin_registrations_page'));
        add_submenu_page('flz-ags', 'Demo-Setup', 'Demo-Setup', $capability, 'flz-ags-demo', array($this, 'render_admin_demo_page'));
        add_submenu_page('flz-ags', 'Einstellungen', 'Einstellungen', $capability, 'flz-ags-settings', array($this, 'render_admin_settings_page'));
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

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs</h1>';

        if (isset($_GET['saved'])) {
            echo wp_kses_post(flz_ags_notice('AG gespeichert.'));
        }
        if (isset($_GET['demo'])) {
            $count = absint($_GET['demo']);
            echo wp_kses_post(flz_ags_notice($count . ' Demo-AGs wurden angelegt. Bereits vorhandene Demo-AGs wurden übersprungen.'));
        }
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }

        try {
            if ($action === 'new' || ($action === 'edit' && $course_id > 0)) {
                $this->render_course_form($course_id);
            } else {
                $this->render_course_list();
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

    private function render_course_list(): void
    {
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $courses = $this->get_courses($school_year, false);
        flz_ags_render_backend_template('course-list', array(
            'school_year' => $school_year,
            'courses' => $courses,
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
            if ($registration_open && $detail_page_id <= 0) {
                throw new UnexpectedValueException('Für eine geöffnete AG-Anmeldung muss eine Detailseite ausgewählt oder angelegt werden.');
            }
            if ($registration_open && get_post_status($detail_page_id) !== 'publish') {
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
                'allowed_grades' => isset($_POST['allowed_grades']) ? flz_ags_sanitize_allowed_grades(sanitize_text_field(wp_unslash($_POST['allowed_grades']))) : '',
                'only_grade_7' => isset($_POST['only_grade_7']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                'registration_open' => $registration_open,
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0,
                'updated_at' => $now,
            );

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

        flz_ags_safe_redirect(
            flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => $course_id, 'saved' => 1))
        );
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
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }

        flz_ags_render_backend_template('settings', array(
            'parent_page_id' => $parent_page_id,
            'configured_parent_page_id' => $configured_parent_page_id,
            'parent_hint' => $parent_hint,
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

        try {
            if ($parent_page_id > 0) {
                $parent_page_id = $this->validate_detail_page_id($parent_page_id);
            }

            update_option('flz_ags_current_school_year', $school_year, false);
            update_option('flz_ags_classes', !empty($classes) ? $classes : flz_ags_default_classes(), false);
            update_option('flz_ags_parent_page_id', $parent_page_id, false);
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
                && ($detail_page_id <= 0 || $detail_page_id !== (int) get_queried_object_id())
            ) {
                return flz_ags_get_frontend_template('registration', array(
                    'heading' => 'AG-Anmeldung: ' . (string) $course->title,
                    'messages' => array('Diese AG-Anmeldung ist nur auf der verknüpften AG-Detailseite verfügbar.'),
                    'success' => false,
                    'form_html' => '',
                ));
            }

            $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Die Nonce wird direkt in handle_frontend_registration() geprüft und liefert dort die fachliche Fehlermeldung.
            if ('post' === $request_method && isset($_POST['flz_ags_registration_submit'])) {
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

        $mails = get_option('flz_ags_mock_confirmation_mails', array());
        if (!is_array($mails)) {
            $mails = array();
        }

        $mails[] = array(
            'created_at' => current_time('mysql'),
            'to' => (string) $mail['to'],
            'subject' => (string) $mail['subject'],
            'message' => (string) $mail['message'],
            'course_id' => (int) $registration->course_id,
            'slot_id' => (int) $registration->slot_id,
            'student' => trim((string) $registration->student_first_name . ' ' . (string) $registration->student_last_name),
            'class_name' => flz_ags_class_label((string) $registration->class_name),
            'course_title' => (string) $slot->title,
        );

        update_option('flz_ags_mock_confirmation_mails', array_slice($mails, -20), false);
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

        if (isset($_POST['flz_ags_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flz_ags_nonce'])), 'flz_ags_frontend_registration')) {
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
                'meta'      => array(
                    'Zeit'   => $time_label,
                    'Raum'   => $slot->room,
                    'Plätze' => $free_label,
                ),
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

                    $taken = $this->count_active_registrations((int) $slot->id);
                    if ((int) $slot->max_participants > 0 && $taken >= (int) $slot->max_participants) {
                        return array('success' => false, 'messages' => array('Dieser AG-Slot ist inzwischen ausgebucht.'));
                    }

                    $duplicate = FLZ_AGS_Registration::count_by(array(
                        'school_year' => $school_year,
                        'class_name' => $class_name,
                        'student_first_name' => $first_name,
                        'student_last_name' => $last_name,
                        'status' => 'active',
                    ));
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
            flz_ags_log_error($error, 'Speichern einer öffentlichen AG-Anmeldung');
            return array(
                'success' => false,
                'messages' => array('Die Anmeldung konnte wegen eines technischen Fehlers nicht gespeichert werden. Bitte später erneut versuchen.'),
            );
        }
    }

    public function render_admin_registrations_page(): void
    {
        $this->assert_admin_permission();

        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'active';
        if (!array_key_exists($status, flz_ags_status_labels())) {
            $status = 'active';
        }

        $registrations = array();
        $load_failed = false;
        try {
            $registrations = FLZ_AGS_Registration::find_for_admin($school_year, $status);
        } catch (Throwable $error) {
            $load_failed = true;
            flz_ags_log_error($error, 'Laden der AG-Anmeldungen im Backend');
        }

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>AG-Anmeldungen</h1>';
        if (isset($_GET['updated'])) {
            echo wp_kses_post(flz_ags_notice('Anmeldung aktualisiert.'));
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

        flz_ags_render_backend_template('registrations', array(
            'school_year' => $school_year,
            'status' => $status,
            'registrations' => $registrations,
        ));
        echo '</div>';
    }

    public function handle_update_registration(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_update_registration');

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_key(wp_unslash($_POST['new_status'])) : '';

        if ($registration_id <= 0 || !array_key_exists($new_status, flz_ags_status_labels())) {
            wp_die(esc_html__('Ungültige Anfrage.', 'flz-ags'));
        }

        try {
            $registration = FLZ_AGS_Registration::get_by_id($registration_id);
            if (!$registration instanceof FLZ_AGS_Registration) {
                throw new UnexpectedValueException('Die zu aktualisierende AG-Anmeldung wurde nicht gefunden.');
            }
            $registration->status = $new_status;
            $registration->updated_at = current_time('mysql');
            if ($new_status === 'withdrawn') {
                $registration->withdrawn_at = current_time('mysql');
            }
            $registration->save();
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

    public function handle_export_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_export_csv');

        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'active';
        if (!array_key_exists($status, flz_ags_status_labels())) {
            $status = 'active';
        }

        try {
            $rows = FLZ_AGS_Registration::find_for_admin($school_year, $status, true);
            $csv_rows = array();
            foreach ($rows as $row) {
                $csv_rows[] = array(
                    $row->school_year,
                    flz_ags_status_label($row->status),
                    flz_ags_class_label((string) $row->class_name),
                    $row->grade_key,
                    $row->student_last_name,
                    $row->student_first_name,
                    $row->student_email,
                    $row->title,
                    flz_ags_weekday_label($row->weekday),
                    flz_ags_format_time($row->start_time),
                    flz_ags_format_time($row->end_time),
                    $row->room,
                    flz_ui_format_datetime($row->created_at, (string) $row->created_at),
                );
            }
            flz_wpdb_objects_send_csv_download(
                array('Schuljahr', 'Status', 'Klasse', 'Jahrgangsschlüssel', 'Nachname', 'Vorname', 'E-Mail Schüler*in', 'AG', 'Wochentag', 'Beginn', 'Ende', 'Raum', 'Anmeldedatum'),
                $csv_rows,
                'flz-ag-anmeldungen-' . sanitize_file_name($school_year) . '-' . sanitize_file_name($status) . '.csv'
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Erstellen des AG-CSV-Exports',
                'export',
                array('page' => 'flz-ags-registrations', 'school_year' => $school_year, 'status' => $status)
            );
        }
    }
}
