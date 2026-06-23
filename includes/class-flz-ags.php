<?php

defined('ABSPATH') || exit;

class FLZ_AGS_Plugin
{
    private static ?FLZ_AGS_Plugin $instance = null;

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

        add_shortcode('flz_ag_liste', array($this, 'shortcode_list'));
        add_shortcode('flz_ag_anmeldung', array($this, 'shortcode_registration'));

        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
    }

    public function register_frontend_assets(): void
    {
        wp_register_style('flz-ags', FLZ_AGS_URL . 'assets/css/flz-ags.css', array(), FLZ_AGS_VERSION);
        wp_register_script('flz-ags', FLZ_AGS_URL . 'assets/js/flz-ags.js', array(), FLZ_AGS_VERSION, true);
    }

    public function register_admin_assets(string $hook): void
    {
        if (strpos($hook, 'flz-ags') === false) {
            return;
        }

        wp_enqueue_style('flz-ags-admin', FLZ_AGS_URL . 'assets/css/flz-ags.css', array(), FLZ_AGS_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('flz-ags-admin', FLZ_AGS_URL . 'assets/js/flz-ags-admin.js', array('jquery'), FLZ_AGS_VERSION, true);
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

        if ($action === 'new' || ($action === 'edit' && $course_id > 0)) {
            $this->render_course_form($course_id);
        } else {
            $this->render_course_list();
        }

        echo '</div>';
    }

    private function get_course(int $course_id): ?object
    {
        global $wpdb;
        $table = flz_ags_table('courses');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally.
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $course_id));
        return $course ?: null;
    }

    private function get_courses(string $school_year, bool $public_only = false): array
    {
        global $wpdb;
        $table = flz_ags_table('courses');
        $sql = "SELECT * FROM {$table} WHERE school_year = %s ORDER BY sort_order ASC, title ASC";

        if ($public_only) {
            $sql = "SELECT * FROM {$table} WHERE school_year = %s AND is_active = 1 AND is_visible = 1 ORDER BY sort_order ASC, title ASC";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated internally.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table names and placeholders.
        return $wpdb->get_results($wpdb->prepare($sql, $school_year));
    }

    private function get_course_slots(int $course_id, bool $include_inactive = true): array
    {
        global $wpdb;
        $table = flz_ags_table('slots');
        $sql = "SELECT * FROM {$table} WHERE course_id = %d ORDER BY sort_order ASC, weekday ASC, start_time ASC";

        if (!$include_inactive) {
            $sql = "SELECT * FROM {$table} WHERE course_id = %d AND is_active = 1 ORDER BY sort_order ASC, weekday ASC, start_time ASC";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated internally.
        return $wpdb->get_results($wpdb->prepare($sql, $course_id));
    }

    private function get_slot_with_course(int $slot_id): ?object
    {
        global $wpdb;

        $sql = 'SELECT s.*, c.title, c.allowed_grades, c.only_grade_7, c.registration_open, c.is_active AS course_active, c.is_visible AS course_visible
                FROM ' . flz_ags_table('slots') . ' s
                INNER JOIN ' . flz_ags_table('courses') . ' c ON c.id = s.course_id
                WHERE s.id = %d';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table names and placeholders.
        $row = $wpdb->get_row($wpdb->prepare($sql, $slot_id));
        return $row ?: null;
    }

    private function render_course_list(): void
    {
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $courses = $this->get_courses($school_year, false);

        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url(flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'new'))) . '">Neue AG anlegen</a> ';
        echo '<a class="button" href="' . esc_url(flz_ags_admin_url(array('page' => 'flz-ags-demo'))) . '">Demo-Setup</a>';
        echo '</p>';

        echo '<form method="get" class="flz-ags-admin-filter">';
        echo '<input type="hidden" name="page" value="flz-ags">';
        echo '<label>Schuljahr <input type="text" name="school_year" value="' . esc_attr($school_year) . '" placeholder="2026/2027"></label> ';
        echo '<button class="button">Filtern</button>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Bild</th><th>AG</th><th>Bereich</th><th>Zielgruppe</th><th>Slots</th><th>Status</th><th></th></tr></thead><tbody>';

        if (empty($courses)) {
            echo '<tr><td colspan="7">Keine AGs für dieses Schuljahr angelegt.</td></tr>';
        }

        foreach ($courses as $course) {
            $slots = $this->get_course_slots((int) $course->id, false);
            $slot_labels = array();
            foreach ($slots as $slot) {
                $slot_labels[] = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time) . ($slot->room ? ', ' . $slot->room : '');
            }

            $target = $course->only_grade_7 ? 'nur Klasse 7' : ($course->allowed_grades ? $course->allowed_grades : 'alle');
            $status = array();
            $status[] = $course->is_active ? 'aktiv' : 'inaktiv';
            $status[] = $course->is_visible ? 'sichtbar' : 'versteckt';
            $status[] = $course->registration_open ? 'Anmeldung offen' : 'Anmeldung geschlossen';

            echo '<tr>';
            echo '<td><img class="flz-ags-admin-thumb" src="' . esc_url(flz_ags_course_image_url($course->image_url ?? '')) . '" alt=""></td>';
            echo '<td><strong>' . esc_html($course->title) . '</strong><br><small>' . esc_html($course->school_year) . '</small></td>';
            echo '<td>' . esc_html((string) $course->category) . '</td>';
            echo '<td>' . esc_html($target) . '</td>';
            echo '<td>' . esc_html(implode(' | ', $slot_labels)) . '</td>';
            echo '<td>' . esc_html(implode(', ', $status)) . '</td>';
            echo '<td><a class="button" href="' . esc_url(flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => (int) $course->id))) . '">Bearbeiten</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_course_form(int $course_id): void
    {
        $course = $course_id > 0 ? $this->get_course($course_id) : null;
        $slots = $course ? $this->get_course_slots((int) $course->id, true) : array();
        $weekdays = flz_ags_weekdays();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="flz-ags-admin-form">';
        wp_nonce_field('flz_ags_save_course');
        echo '<input type="hidden" name="action" value="flz_ags_save_course">';
        echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';

        echo '<h2>' . ($course ? 'AG bearbeiten' : 'Neue AG') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->admin_text_row('Schuljahr', 'school_year', $course->school_year ?? flz_ags_current_school_year(), 'Format: 2026/2027');
        $this->admin_text_row('Titel', 'title', $course->title ?? '', 'Pflichtfeld');
        $this->admin_text_row('Bereich/Kategorie', 'category', $course->category ?? '', 'z. B. Sport, Musik, Naturwissenschaften');
        $this->admin_text_row('Leitung', 'leader_name', $course->leader_name ?? '', 'Name oder Funktionsbezeichnung');
        $this->admin_image_row('Vorschaubild', 'image_url', $course->image_url ?? '');
        $this->admin_text_row('Info-Link', 'info_url', $course->info_url ?? '', 'Optional: bestehende AG-Seite oder Detailseite');
        $this->admin_textarea_row('Kurzbeschreibung', 'short_description', $course->short_description ?? '', 3);
        $this->admin_textarea_row('Beschreibung', 'description', $course->description ?? '', 6);
        $this->admin_text_row('Erlaubte Jahrgänge', 'allowed_grades', $course->allowed_grades ?? '', 'Leer = alle; erlaubt: 7,8,9,10,11,12,WKK');

        echo '<tr><th scope="row">Regeln/Status</th><td>';
        $this->admin_checkbox('only_grade_7', 'nur Klasse 7', !empty($course->only_grade_7));
        $this->admin_checkbox('is_active', 'aktiv', $course ? !empty($course->is_active) : true);
        $this->admin_checkbox('is_visible', 'im Frontend sichtbar', $course ? !empty($course->is_visible) : true);
        $this->admin_checkbox('registration_open', 'Anmeldung geöffnet', $course ? !empty($course->registration_open) : true);
        echo '</td></tr>';
        $this->admin_text_row('Sortierung', 'sort_order', isset($course->sort_order) ? (string) $course->sort_order : '0', 'kleinere Zahl = weiter oben');
        echo '</tbody></table>';

        echo '<h2>Wöchentliche Slots</h2>';
        echo '<p>Eine Anmeldung bezieht sich auf genau einen aktiven Slot. Maximal vier Slots pro AG.</p>';
        echo '<table class="widefat striped flz-ags-slots-table"><thead><tr><th>Aktiv</th><th>Wochentag</th><th>Beginn</th><th>Ende</th><th>Raum</th><th>Max. TN</th><th>Sortierung</th></tr></thead><tbody>';

        for ($i = 0; $i < 4; $i++) {
            $slot = $slots[$i] ?? null;
            echo '<tr>';
            echo '<td><input type="hidden" name="slots[' . esc_attr($i) . '][id]" value="' . esc_attr($slot->id ?? 0) . '">';
            echo '<label><input type="checkbox" name="slots[' . esc_attr($i) . '][is_active]" value="1" ' . checked($slot ? (int) $slot->is_active : ($i === 0), true, false) . '> aktiv</label></td>';
            echo '<td><select name="slots[' . esc_attr($i) . '][weekday]"><option value="0">–</option>';
            foreach ($weekdays as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected((int) ($slot->weekday ?? 0), $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input type="time" name="slots[' . esc_attr($i) . '][start_time]" value="' . esc_attr(flz_ags_format_time($slot->start_time ?? '')) . '"></td>';
            echo '<td><input type="time" name="slots[' . esc_attr($i) . '][end_time]" value="' . esc_attr(flz_ags_format_time($slot->end_time ?? '')) . '"></td>';
            echo '<td><input type="text" name="slots[' . esc_attr($i) . '][room]" value="' . esc_attr($slot->room ?? '') . '"></td>';
            echo '<td><input type="number" min="0" name="slots[' . esc_attr($i) . '][max_participants]" value="' . esc_attr($slot->max_participants ?? 0) . '"></td>';
            echo '<td><input type="number" name="slots[' . esc_attr($i) . '][sort_order]" value="' . esc_attr($slot->sort_order ?? $i) . '"></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        submit_button('AG speichern');
        echo '</form>';
    }

    private function admin_text_row(string $label, string $name, string $value, string $description = ''): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="text" class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    private function admin_image_row(string $label, string $name, string $value): void
    {
        $preview = flz_ags_course_image_url($value);
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<div class="flz-ags-image-field">';
        echo '<img class="flz-ags-image-preview" data-flz-ags-image-preview src="' . esc_url($preview) . '" alt="">';
        echo '<input type="url" class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" data-flz-ags-image-input placeholder="https://..."> ';
        echo '<button type="button" class="button" data-flz-ags-media-button>Bild aus Mediathek wählen</button> ';
        echo '<button type="button" class="button" data-flz-ags-clear-image>Bild entfernen</button>';
        echo '</div><p class="description">URL eines Vorschaubilds. Über die Mediathek eingefügte Bilder bleiben in WordPress verwaltet.</p>';
        echo '</td></tr>';
    }

    private function admin_textarea_row(string $label, string $name, string $value, int $rows): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<textarea class="large-text" rows="' . esc_attr($rows) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
        echo '</td></tr>';
    }

    private function admin_checkbox(string $name, string $label, bool $checked): void
    {
        echo '<label class="flz-ags-admin-checkbox"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '> ' . esc_html($label) . '</label><br>';
    }

    public function handle_save_course(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_save_course');

        global $wpdb;
        $now = current_time('mysql');
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        if ($title === '') {
            wp_die('Der Titel ist erforderlich.');
        }

        $school_year = isset($_POST['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year']))) : flz_ags_current_school_year();
        $data = array(
            'school_year' => $school_year,
            'title' => $title,
            'slug' => sanitize_title($title),
            'short_description' => isset($_POST['short_description']) ? sanitize_textarea_field(wp_unslash($_POST['short_description'])) : '',
            'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '',
            'image_url' => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
            'info_url' => isset($_POST['info_url']) ? esc_url_raw(wp_unslash($_POST['info_url'])) : '',
            'category' => isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '',
            'leader_name' => isset($_POST['leader_name']) ? sanitize_text_field(wp_unslash($_POST['leader_name'])) : '',
            'allowed_grades' => isset($_POST['allowed_grades']) ? flz_ags_sanitize_allowed_grades(sanitize_text_field(wp_unslash($_POST['allowed_grades']))) : '',
            'only_grade_7' => isset($_POST['only_grade_7']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
            'registration_open' => isset($_POST['registration_open']) ? 1 : 0,
            'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0,
            'updated_at' => $now,
        );

        if ($course_id > 0) {
            $wpdb->update(flz_ags_table('courses'), $data, array('id' => $course_id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert(flz_ags_table('courses'), $data);
            $course_id = (int) $wpdb->insert_id;
        }

        $slots = array();
        if (isset($_POST['slots']) && is_array($_POST['slots'])) {
            $slots = map_deep(wp_unslash($_POST['slots']), 'sanitize_text_field');
        }
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $slot_id = isset($slot['id']) ? absint($slot['id']) : 0;
            $weekday = isset($slot['weekday']) ? absint($slot['weekday']) : 0;
            $start_time = isset($slot['start_time']) ? sanitize_text_field($slot['start_time']) : '';
            $end_time = isset($slot['end_time']) ? sanitize_text_field($slot['end_time']) : '';
            $room = isset($slot['room']) ? sanitize_text_field($slot['room']) : '';
            $max_participants = isset($slot['max_participants']) ? max(0, intval($slot['max_participants'])) : 0;
            $is_active = isset($slot['is_active']) ? 1 : 0;
            $sort_order = isset($slot['sort_order']) ? intval($slot['sort_order']) : 0;

            $has_values = $weekday > 0 || $start_time !== '' || $end_time !== '' || $room !== '';
            if (!$has_values && $slot_id === 0) {
                continue;
            }

            if (!$has_values && $slot_id > 0) {
                $wpdb->update(flz_ags_table('slots'), array('is_active' => 0, 'updated_at' => $now), array('id' => $slot_id, 'course_id' => $course_id));
                continue;
            }

            if ($weekday < 1 || $weekday > 7 || !preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
                continue;
            }

            $slot_data = array(
                'course_id' => $course_id,
                'school_year' => $school_year,
                'weekday' => $weekday,
                'start_time' => $start_time . ':00',
                'end_time' => $end_time . ':00',
                'room' => $room,
                'max_participants' => $max_participants,
                'is_active' => $is_active,
                'sort_order' => $sort_order,
                'updated_at' => $now,
            );

            if ($slot_id > 0) {
                $wpdb->update(flz_ags_table('slots'), $slot_data, array('id' => $slot_id, 'course_id' => $course_id));
            } else {
                $slot_data['created_at'] = $now;
                $wpdb->insert(flz_ags_table('slots'), $slot_data);
            }
        }

        wp_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => $course_id, 'saved' => 1)));
        exit;
    }

    public function render_admin_settings_page(): void
    {
        $this->assert_admin_permission();

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Einstellungen</h1>';
        if (isset($_GET['saved'])) {
            echo wp_kses_post(flz_ags_notice('Einstellungen gespeichert.'));
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('flz_ags_save_settings');
        echo '<input type="hidden" name="action" value="flz_ags_save_settings">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="flz_ags_current_school_year">Aktuelles Schuljahr</label></th><td>';
        echo '<input type="text" class="regular-text" id="flz_ags_current_school_year" name="current_school_year" value="' . esc_attr(flz_ags_current_school_year()) . '">';
        echo '<p class="description">Format: 2026/2027. AGs und Anmeldungen werden schuljahrbezogen geführt.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="flz_ags_classes">Klassen/Kurse</label></th><td>';
        echo '<textarea class="large-text code" rows="12" id="flz_ags_classes" name="classes_text">' . esc_textarea(implode("\n", flz_ags_get_classes())) . '</textarea>';
        echo '<p class="description">Eine Klasse/ein Kurs pro Zeile. Diese Liste wird im Anmeldeformular als Dropdown verwendet.</p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Einstellungen speichern');
        echo '</form>';
        echo '</div>';
    }

    public function handle_save_settings(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_save_settings');

        $school_year = isset($_POST['current_school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['current_school_year']))) : flz_ags_default_school_year();
        $classes_text = isset($_POST['classes_text']) ? sanitize_textarea_field(wp_unslash($_POST['classes_text'])) : '';
        $classes = flz_ags_sanitize_classes_from_text($classes_text);

        update_option('flz_ags_current_school_year', $school_year, false);
        update_option('flz_ags_classes', !empty($classes) ? $classes : flz_ags_default_classes(), false);

        wp_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-settings', 'saved' => 1)));
        exit;
    }

    public function render_admin_demo_page(): void
    {
        $this->assert_admin_permission();
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $demo = flz_ags_demo_courses();

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Demo-Setup</h1>';
        echo '<p>Legt eine Auswahl vorhandener AGs als Demo-Datensatz für das gewählte Schuljahr an. Vorhandene AGs mit gleichem Slug und Schuljahr werden nicht dupliziert.</p>';
        echo '<form method="get" class="flz-ags-admin-filter">';
        echo '<input type="hidden" name="page" value="flz-ags-demo">';
        echo '<label>Schuljahr <input type="text" name="school_year" value="' . esc_attr($school_year) . '"></label> ';
        echo '<button class="button">Anzeigen</button>';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('flz_ags_install_demo');
        echo '<input type="hidden" name="action" value="flz_ags_install_demo">';
        echo '<input type="hidden" name="school_year" value="' . esc_attr($school_year) . '">';
        submit_button('Demo-AGs für ' . $school_year . ' anlegen', 'primary');
        echo '</form>';

        echo '<h2>Enthaltene Demo-AGs</h2>';
        echo '<div class="flz-ags-grid flz-ags-demo-grid">';
        foreach ($demo as $course) {
            echo '<article class="flz-ags-card">';
            echo '<div class="flz-ags-card-image-wrap"><img class="flz-ags-card-image" src="' . esc_url($course['image_url']) . '" alt=""></div>';
            echo '<div class="flz-ags-card-body"><h3>' . esc_html($course['title']) . '</h3>';
            echo '<p>' . esc_html($course['short_description']) . '</p>';
            echo '<p><small>' . esc_html($course['category'] . ' · Jahrgänge: ' . $course['allowed_grades']) . '</small></p></div>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
    }

    public function handle_install_demo(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_install_demo');

        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are generated internally; user input remains sanitized before use.
        $school_year = isset($_POST['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_POST['school_year']))) : flz_ags_current_school_year();
        $now = current_time('mysql');
        $inserted = 0;

        foreach (flz_ags_demo_courses() as $course) {
            $slug = sanitize_title($course['title']);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated internally.
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . flz_ags_table('courses') . ' WHERE school_year = %s AND slug = %s',
                $school_year,
                $slug
            ));
            if ($exists > 0) {
                continue;
            }

            $wpdb->insert(flz_ags_table('courses'), array(
                'school_year' => $school_year,
                'title' => sanitize_text_field($course['title']),
                'slug' => $slug,
                'short_description' => sanitize_textarea_field($course['short_description']),
                'description' => wp_kses_post($course['description']),
                'image_url' => esc_url_raw($course['image_url']),
                'info_url' => esc_url_raw($course['info_url']),
                'category' => sanitize_text_field($course['category']),
                'leader_name' => sanitize_text_field($course['leader_name']),
                'allowed_grades' => flz_ags_sanitize_allowed_grades($course['allowed_grades']),
                'only_grade_7' => !empty($course['only_grade_7']) ? 1 : 0,
                'is_active' => 1,
                'is_visible' => 1,
                'registration_open' => 1,
                'sort_order' => intval($course['sort_order']),
                'created_at' => $now,
                'updated_at' => $now,
            ));

            $course_id = (int) $wpdb->insert_id;
            if ($course_id <= 0) {
                continue;
            }

            foreach ($course['slots'] as $index => $slot) {
                $wpdb->insert(flz_ags_table('slots'), array(
                    'course_id' => $course_id,
                    'school_year' => $school_year,
                    'weekday' => absint($slot['weekday']),
                    'start_time' => sanitize_text_field($slot['start_time']) . ':00',
                    'end_time' => sanitize_text_field($slot['end_time']) . ':00',
                    'room' => sanitize_text_field($slot['room']),
                    'max_participants' => max(0, intval($slot['max_participants'])),
                    'is_active' => 1,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
            }
            $inserted++;
        }

        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        wp_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags', 'school_year' => $school_year, 'demo' => $inserted)));
        exit;
    }

    private function count_active_registrations(int $slot_id): int
    {
        global $wpdb;
        $table = flz_ags_table('registrations');

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally; values are passed as placeholders.
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE slot_id = %d AND status = %s",
            $slot_id,
            'active'
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $count;
    }

    private function get_public_slots(string $school_year): array
    {
        global $wpdb;

        $slots_table = flz_ags_table('slots');
        $courses_table = flz_ags_table('courses');

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are generated internally; school year is passed as placeholder.
        $sql = "SELECT s.*, c.title, c.short_description, c.description, c.image_url, c.info_url, c.category, c.leader_name, c.allowed_grades, c.only_grade_7, c.registration_open
                FROM {$slots_table} s
                INNER JOIN {$courses_table} c ON c.id = s.course_id
                WHERE s.school_year = %s
                  AND s.is_active = 1
                  AND c.is_active = 1
                  AND c.is_visible = 1
                ORDER BY c.sort_order ASC, c.title ASC, s.weekday ASC, s.start_time ASC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $school_year));
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $results;
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
            'registration_url' => '',
        ), (array) $atts, 'flz_ag_liste');

        $school_year = flz_ags_sanitize_school_year($atts['school_year']);
        $registration_url = esc_url_raw((string) $atts['registration_url']);
        $courses = $this->get_public_courses_with_slots($school_year);

        wp_enqueue_style('flz-ags');
        wp_enqueue_script('flz-ags');

        ob_start();
        echo '<div class="flz-ags flz-ags-list" data-flz-ags-list>';
        echo '<h2>Arbeitsgemeinschaften ' . esc_html($school_year) . '</h2>';
        $this->render_frontend_filters(false);

        if (empty($courses)) {
            echo '<p>Derzeit sind keine AGs für dieses Schuljahr veröffentlicht.</p>';
        } else {
            echo '<div class="flz-ags-grid">';
            foreach ($courses as $course) {
                $this->render_course_card($course, $registration_url);
            }
            echo '</div>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private function render_frontend_filters(bool $include_classes, bool $show_class_filter = true): void
    {
        $weekdays = flz_ags_weekdays();
        echo '<div class="flz-ags-filters">';
        if ($show_class_filter && $include_classes) {
            echo '<label>Klasse/Kurs <select name="class_name" data-flz-ags-class-select required>';
            echo '<option value="">– Bitte auswählen –</option>';
            foreach (flz_ags_get_classes() as $class) {
                echo '<option value="' . esc_attr($class) . '">' . esc_html($class) . '</option>';
            }
            echo '</select></label>';
        } elseif ($show_class_filter) {
            echo '<label>Klasse/Kurs <select data-flz-ags-class-select>';
            echo '<option value="">alle anzeigen</option>';
            foreach (flz_ags_get_classes() as $class) {
                echo '<option value="' . esc_attr($class) . '">' . esc_html($class) . '</option>';
            }
            echo '</select></label>';
        }

        echo '<label>Wochentag <select data-flz-ags-weekday-select>';
        echo '<option value="">alle Tage</option>';
        foreach ($weekdays as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '</div>';
    }

    private function render_course_card(object $course, string $registration_url = ''): void
    {
        $weekdays = array();
        $slot_lines = array();
        $total_max = 0;
        $total_free = 0;
        $all_full = true;

        foreach ($course->slots as $slot) {
            $taken = $this->count_active_registrations((int) $slot->id);
            $max = (int) $slot->max_participants;
            $free = $max > 0 ? max(0, $max - $taken) : null;
            $weekdays[] = (string) $slot->weekday;
            $slot_lines[] = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time) . (!empty($slot->room) ? ' · ' . $slot->room : '');
            if ($max > 0) {
                $total_max += $max;
                $total_free += (int) $free;
                if ($free > 0) {
                    $all_full = false;
                }
            } else {
                $all_full = false;
            }
        }

        $weekdays = array_values(array_unique($weekdays));
        $target = $course->only_grade_7 ? 'nur Klasse 7' : ($course->allowed_grades ? 'Jahrgänge ' . $course->allowed_grades : 'alle Jahrgänge');

        echo '<article class="flz-ags-card flz-ags-course-card" data-flz-ags-filter-item data-weekdays="' . esc_attr(implode(',', $weekdays)) . '" data-only-grade-7="' . esc_attr((int) $course->only_grade_7) . '" data-allowed-grades="' . esc_attr($course->allowed_grades) . '" data-full="' . esc_attr($all_full ? '1' : '0') . '">';
        echo '<div class="flz-ags-card-image-wrap"><img class="flz-ags-card-image" src="' . esc_url(flz_ags_course_image_url($course->image_url ?? '')) . '" alt=""></div>';
        echo '<div class="flz-ags-card-body">';
        echo '<h3>' . esc_html($course->title) . '</h3>';
        if (!empty($course->short_description)) {
            echo '<p class="flz-ags-card-text">' . esc_html($course->short_description) . '</p>';
        }
        echo '<dl class="flz-ags-meta">';
        if (!empty($course->category)) {
            echo '<dt>Bereich</dt><dd>' . esc_html($course->category) . '</dd>';
        }
        if (!empty($course->leader_name)) {
            echo '<dt>Leitung</dt><dd>' . esc_html($course->leader_name) . '</dd>';
        }
        echo '<dt>Zielgruppe</dt><dd>' . esc_html($target) . '</dd>';
        echo '<dt>Termine</dt><dd>' . esc_html(implode(' | ', $slot_lines)) . '</dd>';
        if ($total_max > 0) {
            echo '<dt>Plätze</dt><dd>' . esc_html($total_free . ' frei von ' . $total_max) . '</dd>';
        } else {
            echo '<dt>Plätze</dt><dd>keine Begrenzung hinterlegt</dd>';
        }
        echo '</dl>';
        echo '<div class="flz-ags-card-actions">';
        if (!empty($course->info_url)) {
            echo '<a class="button" href="' . esc_url($course->info_url) . '">Details</a> ';
        }
        if ($registration_url !== '' && !$all_full && !empty($course->registration_open)) {
            echo '<a class="button button-primary" href="' . esc_url($registration_url) . '">Zur Anmeldung</a>';
        }
        echo '</div>';
        if ($all_full) {
            echo '<p class="flz-ags-badge">ausgebucht</p>';
        }
        echo '</div></article>';
    }

    private function render_slot_card(object $slot, string $registration_url = ''): void
    {
        $taken = $this->count_active_registrations((int) $slot->id);
        $max = (int) $slot->max_participants;
        $free = $max > 0 ? max(0, $max - $taken) : null;
        $is_full = $max > 0 && $taken >= $max;

        echo '<article class="flz-ags-card flz-ags-slot-option" data-flz-ags-filter-item data-weekday="' . esc_attr($slot->weekday) . '" data-only-grade-7="' . esc_attr((int) $slot->only_grade_7) . '" data-allowed-grades="' . esc_attr($slot->allowed_grades) . '" data-full="' . esc_attr($is_full ? '1' : '0') . '">';
        echo '<div class="flz-ags-card-image-wrap"><img class="flz-ags-card-image" src="' . esc_url(flz_ags_course_image_url($slot->image_url ?? '')) . '" alt=""></div>';
        echo '<div class="flz-ags-card-body"><h3>' . esc_html($slot->title) . '</h3>';
        if (!empty($slot->short_description)) {
            echo '<p>' . esc_html($slot->short_description) . '</p>';
        }
        echo '<dl class="flz-ags-meta">';
        echo '<dt>Zeit</dt><dd>' . esc_html(flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time)) . '</dd>';
        if (!empty($slot->room)) {
            echo '<dt>Raum</dt><dd>' . esc_html($slot->room) . '</dd>';
        }
        if (!empty($slot->leader_name)) {
            echo '<dt>Leitung</dt><dd>' . esc_html($slot->leader_name) . '</dd>';
        }
        echo '<dt>Zielgruppe</dt><dd>' . esc_html($slot->only_grade_7 ? 'nur Klasse 7' : ($slot->allowed_grades ? $slot->allowed_grades : 'alle')) . '</dd>';
        echo '<dt>Plätze</dt><dd>' . esc_html($max > 0 ? ($free . ' frei von ' . $max) : 'keine Begrenzung hinterlegt') . '</dd>';
        echo '</dl>';
        if ($registration_url !== '' && !$is_full && !empty($slot->registration_open)) {
            echo '<p><a class="button" href="' . esc_url($registration_url) . '">Zur Anmeldung</a></p>';
        }
        if ($is_full) {
            echo '<p class="flz-ags-badge">ausgebucht</p>';
        }
        echo '</div></article>';
    }

    public function shortcode_registration($atts): string
    {
        $atts = shortcode_atts(array(
            'school_year' => flz_ags_current_school_year(),
        ), (array) $atts, 'flz_ag_anmeldung');

        $school_year = flz_ags_sanitize_school_year($atts['school_year']);
        $messages = array();
        $success = false;

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' === $request_method) {
            $result = $this->handle_frontend_registration($school_year);
            $messages = $result['messages'];
            $success = $result['success'];
        }

        wp_enqueue_style('flz-ags');
        wp_enqueue_script('flz-ags');

        ob_start();
        echo '<div class="flz-ags flz-ags-registration">';
        echo '<h2>AG-Anmeldung ' . esc_html($school_year) . '</h2>';

        foreach ($messages as $message) {
            echo '<p class="flz-ags-message ' . ($success ? 'flz-ags-message-success' : 'flz-ags-message-error') . '">' . esc_html($message) . '</p>';
        }

        if (!$success) {
            $this->render_registration_form($school_year);
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private function render_registration_form(string $school_year): void
    {
        $slots = array_filter($this->get_public_slots($school_year), static function ($slot) {
            return !empty($slot->registration_open);
        });

        $posted = array(
            'class_name' => '',
            'student_first_name' => '',
            'student_last_name' => '',
            'guardian_email' => '',
            'slot_id' => 0,
        );

        if (isset($_POST['flz_ags_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flz_ags_nonce'])), 'flz_ags_frontend_registration')) {
            $posted['class_name'] = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
            $posted['student_first_name'] = isset($_POST['student_first_name']) ? sanitize_text_field(wp_unslash($_POST['student_first_name'])) : '';
            $posted['student_last_name'] = isset($_POST['student_last_name']) ? sanitize_text_field(wp_unslash($_POST['student_last_name'])) : '';
            $posted['guardian_email'] = isset($_POST['guardian_email']) ? sanitize_email(wp_unslash($_POST['guardian_email'])) : '';
            $posted['slot_id'] = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        }

        echo '<form method="post" class="flz-ags-registration-form" data-flz-ags-registration-form>';
        wp_nonce_field('flz_ags_frontend_registration', 'flz_ags_nonce');
        echo '<input type="hidden" name="flz_ags_registration_submit" value="1">';
        echo '<div class="flz-ags-form-grid">';
        echo '<label>Klasse/Kurs <select name="class_name" data-flz-ags-class-select required><option value="">– Bitte auswählen –</option>';
        foreach (flz_ags_get_classes() as $class) {
            $selected = $posted['class_name'] === $class;
            echo '<option value="' . esc_attr($class) . '" ' . selected($selected, true, false) . '>' . esc_html($class) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Vorname Schüler*in <input type="text" name="student_first_name" required value="' . esc_attr($posted['student_first_name']) . '"></label>';
        echo '<label>Nachname Schüler*in <input type="text" name="student_last_name" required value="' . esc_attr($posted['student_last_name']) . '"></label>';
        echo '<label>E-Mail Erziehungsberechtigte*r / Kontakt <input type="email" name="guardian_email" value="' . esc_attr($posted['guardian_email']) . '"></label>';
        echo '</div>';

        $this->render_frontend_filters(false, false);

        echo '<fieldset class="flz-ags-slot-fieldset"><legend>AG-Slot auswählen</legend>';
        if (empty($slots)) {
            echo '<p>Derzeit sind keine Anmeldungen möglich.</p>';
        } else {
            foreach ($slots as $slot) {
                $taken = $this->count_active_registrations((int) $slot->id);
                $max = (int) $slot->max_participants;
                $is_full = $max > 0 && $taken >= $max;
                $free_label = $max > 0 ? max(0, $max - $taken) . ' freie Plätze' : 'keine Begrenzung hinterlegt';
                $checked = $posted['slot_id'] === (int) $slot->id;

                echo '<label class="flz-ags-slot-choice flz-ags-slot-option" data-flz-ags-filter-item data-weekday="' . esc_attr($slot->weekday) . '" data-only-grade-7="' . esc_attr((int) $slot->only_grade_7) . '" data-allowed-grades="' . esc_attr($slot->allowed_grades) . '" data-full="' . esc_attr($is_full ? '1' : '0') . '">';
                echo '<input type="radio" name="slot_id" value="' . esc_attr($slot->id) . '" ' . checked($checked, true, false) . ' ' . disabled($is_full, true, false) . ' required> ';
                echo '<img class="flz-ags-slot-image" src="' . esc_url(flz_ags_course_image_url($slot->image_url ?? '')) . '" alt="">';
                echo '<span><strong>' . esc_html($slot->title) . '</strong><br>';
                echo esc_html(flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time));
                if (!empty($slot->room)) {
                    echo ' · ' . esc_html($slot->room);
                }
                echo '<br><small>' . esc_html(($slot->only_grade_7 ? 'nur Klasse 7' : ($slot->allowed_grades ? 'Jahrgänge: ' . $slot->allowed_grades : 'alle Jahrgänge')) . ' · ' . $free_label) . '</small>';
                echo '</span></label>';
            }
        }
        echo '</fieldset>';

        echo '<label class="flz-ags-consent"><input type="checkbox" name="consent_privacy" value="1" required> Ich habe die Datenschutzhinweise zur AG-Anmeldung zur Kenntnis genommen und stimme der Verarbeitung der Angaben für die AG-Anmeldung zu.</label>';
        echo '<p><button type="submit" class="button button-primary">AG verbindlich anmelden</button></p>';
        echo '</form>';
    }

    private function handle_frontend_registration(string $school_year): array
    {
        global $wpdb;

        $messages = array();
        if (!isset($_POST['flz_ags_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flz_ags_nonce'])), 'flz_ags_frontend_registration')) {
            return array('success' => false, 'messages' => array('Die Anmeldung konnte aus Sicherheitsgründen nicht verarbeitet werden. Bitte Formular neu laden.'));
        }

        $class_name = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
        $first_name = isset($_POST['student_first_name']) ? sanitize_text_field(wp_unslash($_POST['student_first_name'])) : '';
        $last_name = isset($_POST['student_last_name']) ? sanitize_text_field(wp_unslash($_POST['student_last_name'])) : '';
        $guardian_email = isset($_POST['guardian_email']) ? sanitize_email(wp_unslash($_POST['guardian_email'])) : '';
        $slot_id = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        $consent = isset($_POST['consent_privacy']) ? 1 : 0;

        if (!flz_ags_is_valid_class($class_name)) {
            $messages[] = 'Bitte eine gültige Klasse/einen gültigen Kurs auswählen.';
        }
        if ($first_name === '' || $last_name === '') {
            $messages[] = 'Bitte Vor- und Nachname der Schülerin/des Schülers eintragen.';
        }
        if ($guardian_email !== '' && !is_email($guardian_email)) {
            $messages[] = 'Bitte eine gültige E-Mail-Adresse eintragen oder das Feld leer lassen.';
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

        $slot = $this->get_slot_with_course($slot_id);
        if (!$slot || $slot->school_year !== $school_year || empty($slot->is_active) || empty($slot->course_active) || empty($slot->course_visible) || empty($slot->registration_open)) {
            return array('success' => false, 'messages' => array('Der gewählte AG-Slot ist nicht verfügbar.'));
        }

        if (!flz_ags_grade_is_allowed($class_name, (string) $slot->allowed_grades, !empty($slot->only_grade_7))) {
            return array('success' => false, 'messages' => array('Dieser AG-Slot ist für die gewählte Klasse nicht freigegeben.'));
        }

        $taken = $this->count_active_registrations((int) $slot->id);
        if ((int) $slot->max_participants > 0 && $taken >= (int) $slot->max_participants) {
            return array('success' => false, 'messages' => array('Dieser AG-Slot ist inzwischen ausgebucht.'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated internally.
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are generated internally; submitted values are sanitized before DB use.
        $duplicate = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . flz_ags_table('registrations') . ' WHERE school_year = %s AND class_name = %s AND student_first_name = %s AND student_last_name = %s AND status = %s',
            $school_year,
            $class_name,
            $first_name,
            $last_name,
            'active'
        ));

        if ($duplicate > 0) {
            return array('success' => false, 'messages' => array('Für diese Schüler*in existiert in diesem Schuljahr bereits eine aktive AG-Anmeldung. Änderungen bitte über die Schule veranlassen.'));
        }

        $now = current_time('mysql');
        $wpdb->insert(flz_ags_table('registrations'), array(
            'course_id' => (int) $slot->course_id,
            'slot_id' => (int) $slot->id,
            'school_year' => $school_year,
            'class_name' => $class_name,
            'grade_key' => flz_ags_extract_grade_key($class_name),
            'student_first_name' => $first_name,
            'student_last_name' => $last_name,
            'guardian_email' => $guardian_email,
            'status' => 'active',
            'consent_privacy' => $consent,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if ($wpdb->insert_id <= 0) {
            return array('success' => false, 'messages' => array('Die Anmeldung konnte nicht gespeichert werden.'));
        }

        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array('success' => true, 'messages' => array('Die AG-Anmeldung wurde gespeichert. Die Teilnahme gilt bis auf Widerruf.'));
    }

    public function render_admin_registrations_page(): void
    {
        $this->assert_admin_permission();
        global $wpdb;

        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'active';
        if (!array_key_exists($status, flz_ags_status_labels())) {
            $status = 'active';
        }

        $sql = 'SELECT r.*, c.title, s.weekday, s.start_time, s.end_time, s.room
                FROM ' . flz_ags_table('registrations') . ' r
                INNER JOIN ' . flz_ags_table('courses') . ' c ON c.id = r.course_id
                INNER JOIN ' . flz_ags_table('slots') . ' s ON s.id = r.slot_id
                WHERE r.school_year = %s AND r.status = %s
                ORDER BY r.class_name ASC, r.student_last_name ASC, r.student_first_name ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table names and placeholders.
        $registrations = $wpdb->get_results($wpdb->prepare($sql, $school_year, $status));

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>AG-Anmeldungen</h1>';
        if (isset($_GET['updated'])) {
            echo wp_kses_post(flz_ags_notice('Anmeldung aktualisiert.'));
        }

        echo '<form method="get" class="flz-ags-admin-filter">';
        echo '<input type="hidden" name="page" value="flz-ags-registrations">';
        echo '<label>Schuljahr <input type="text" name="school_year" value="' . esc_attr($school_year) . '"></label> ';
        echo '<label>Status <select name="status">';
        foreach (flz_ags_status_labels() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';
        echo '<button class="button">Filtern</button> ';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=flz_ags_export_csv&school_year=' . rawurlencode($school_year) . '&status=' . rawurlencode($status)), 'flz_ags_export_csv')) . '">CSV exportieren</a>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Schüler*in</th><th>Klasse</th><th>AG</th><th>Slot</th><th>Kontakt</th><th>Status</th><th>Datum</th><th></th></tr></thead><tbody>';
        if (empty($registrations)) {
            echo '<tr><td colspan="8">Keine Anmeldungen gefunden.</td></tr>';
        }
        foreach ($registrations as $registration) {
            echo '<tr>';
            echo '<td>' . esc_html($registration->student_last_name . ', ' . $registration->student_first_name) . '</td>';
            echo '<td>' . esc_html($registration->class_name) . '</td>';
            echo '<td>' . esc_html($registration->title) . '</td>';
            echo '<td>' . esc_html(flz_ags_weekday_label($registration->weekday) . ', ' . flz_ags_format_time($registration->start_time) . '–' . flz_ags_format_time($registration->end_time) . ($registration->room ? ', ' . $registration->room : '')) . '</td>';
            echo '<td>' . esc_html($registration->guardian_email) . '</td>';
            echo '<td>' . esc_html(flz_ags_status_label($registration->status)) . '</td>';
            echo '<td>' . esc_html($registration->created_at) . '</td>';
            echo '<td>';
            if ($registration->status === 'active') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('flz_ags_update_registration');
                echo '<input type="hidden" name="action" value="flz_ags_update_registration">';
                echo '<input type="hidden" name="registration_id" value="' . esc_attr($registration->id) . '">';
                echo '<input type="hidden" name="new_status" value="withdrawn">';
                echo '<button class="button button-small">widerrufen</button>';
                echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_update_registration(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_update_registration');

        global $wpdb;
        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_key(wp_unslash($_POST['new_status'])) : '';

        if ($registration_id <= 0 || !array_key_exists($new_status, flz_ags_status_labels())) {
            wp_die('Ungültige Anfrage.');
        }

        $data = array(
            'status' => $new_status,
            'updated_at' => current_time('mysql'),
        );
        if ($new_status === 'withdrawn') {
            $data['withdrawn_at'] = current_time('mysql');
        }

        $wpdb->update(flz_ags_table('registrations'), $data, array('id' => $registration_id));

        wp_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-registrations', 'updated' => 1)));
        exit;
    }

    public function handle_export_csv(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_export_csv');

        global $wpdb;
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'active';
        if (!array_key_exists($status, flz_ags_status_labels())) {
            $status = 'active';
        }

        $sql = 'SELECT r.*, c.title, s.weekday, s.start_time, s.end_time, s.room
                FROM ' . flz_ags_table('registrations') . ' r
                INNER JOIN ' . flz_ags_table('courses') . ' c ON c.id = r.course_id
                INNER JOIN ' . flz_ags_table('slots') . ' s ON s.id = r.slot_id
                WHERE r.school_year = %s AND r.status = %s
                ORDER BY c.title ASC, s.weekday ASC, r.class_name ASC, r.student_last_name ASC';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table names and placeholders.
        $rows = $wpdb->get_results($wpdb->prepare($sql, $school_year, $status), ARRAY_A);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="flz-ag-anmeldungen-' . sanitize_file_name($school_year) . '-' . sanitize_file_name($status) . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, array('Schuljahr', 'Status', 'Klasse', 'Jahrgang', 'Nachname', 'Vorname', 'E-Mail', 'AG', 'Wochentag', 'Beginn', 'Ende', 'Raum', 'Anmeldedatum'), ';');

        foreach ($rows as $row) {
            fputcsv($out, array(
                $row['school_year'],
                flz_ags_status_label($row['status']),
                $row['class_name'],
                $row['grade_key'],
                $row['student_last_name'],
                $row['student_first_name'],
                $row['guardian_email'],
                $row['title'],
                flz_ags_weekday_label($row['weekday']),
                flz_ags_format_time($row['start_time']),
                flz_ags_format_time($row['end_time']),
                $row['room'],
                $row['created_at'],
            ), ';');
        }

        fclose($out);
        exit;
    }
}
