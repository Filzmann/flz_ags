<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

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

    private function render_course_list(): void
    {
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $courses = $this->get_courses($school_year, false);
        $ui = flz_ui();

        echo '<p>';
        echo $ui->button_new(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'new')), 'label' => 'Neue AG anlegen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo ' ';
        echo $ui->button_view(array('href' => flz_ags_admin_url(array('page' => 'flz-ags-demo')), 'label' => 'Demo-Setup anzeigen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</p>';

        echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year, 'placeholder' => '2026/2027', 'class' => 'flz-ui-field--inline')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_filter(array('label' => 'AG-Liste filtern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.

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
            echo '<td>' . $ui->button_edit(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => (int) $course->id)), 'label' => 'AG bearbeiten')) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_course_form(int $course_id): void
    {
        $course = $course_id > 0 ? $this->get_course($course_id) : null;
        $slots = $course ? $this->get_course_slots((int) $course->id, true) : array();
        $weekdays = flz_ags_weekdays();
        $ui = flz_ui();

        echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'class' => 'flz-ags-admin-form', 'nonce' => 'flz_ags_save_course', 'hidden' => array('action' => 'flz_ags_save_course', 'course_id' => $course_id))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.

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
        echo '<p>Eine Anmeldung bezieht sich auf genau einen aktiven Slot. Weitere Leerzeilen können über den Plus-Button ergänzt werden.</p>';
        $slot_rows = array();
        foreach ($slots as $index => $slot) {
            $slot_rows[] = array(
                'id' => $slot->id ?? 0,
                'is_active' => !empty($slot->is_active),
                'weekday' => (string) ($slot->weekday ?? 0),
                'start_time' => flz_ui_format_time($slot->start_time ?? ''),
                'end_time' => flz_ui_format_time($slot->end_time ?? ''),
                'room' => $slot->room ?? '',
                'max_participants' => $slot->max_participants ?? 0,
                'sort_order' => $slot->sort_order ?? $index,
            );
        }
        if (empty($slot_rows)) {
            $slot_rows[] = array('id' => 0, 'is_active' => true, 'weekday' => '0', 'sort_order' => 0);
        }

        $slot_matrix_args = array(
            'id' => 'flz-ags-slots',
            'class' => 'widefat striped flz-ags-slots-table',
            'rows' => $slot_rows,
            'min_rows' => 1,
            'add_label' => 'Slot-Zeile hinzufügen',
            'hidden_fields' => array(
                array('name' => 'slots[{index}][id]', 'value_key' => 'id', 'default' => 0),
            ),
            'columns' => array(
                array(
                    'label' => 'Aktiv',
                    'field' => array('type' => 'checkbox', 'name' => 'slots[{index}][is_active]', 'label' => 'aktiv', 'checked_key' => 'is_active'),
                ),
                array(
                    'label' => 'Wochentag',
                    'field' => array('type' => 'select', 'name' => 'slots[{index}][weekday]', 'value_key' => 'weekday', 'default' => '0', 'options' => array('0' => '–') + $weekdays, 'aria_label' => 'Wochentag'),
                ),
                array(
                    'label' => 'Beginn',
                    'field' => array('type' => 'time', 'name' => 'slots[{index}][start_time]', 'value_key' => 'start_time', 'aria_label' => 'Beginn'),
                ),
                array(
                    'label' => 'Ende',
                    'field' => array('type' => 'time', 'name' => 'slots[{index}][end_time]', 'value_key' => 'end_time', 'aria_label' => 'Ende'),
                ),
                array(
                    'label' => 'Raum',
                    'field' => array('type' => 'text', 'name' => 'slots[{index}][room]', 'value_key' => 'room', 'aria_label' => 'Raum'),
                ),
                array(
                    'label' => 'Max. TN',
                    'field' => array('type' => 'number', 'name' => 'slots[{index}][max_participants]', 'value_key' => 'max_participants', 'default' => 0, 'min' => 0, 'aria_label' => 'Maximale Teilnehmerzahl'),
                ),
                array(
                    'label' => 'Sortierung',
                    'field' => array('type' => 'number', 'name' => 'slots[{index}][sort_order]', 'value_key' => 'sort_order', 'default' => 0, 'aria_label' => 'Sortierung'),
                ),
            ),
        );
        echo $ui->field_matrix($slot_matrix_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Field-Matrix.

        echo '<p>' . $ui->button_save(array('label' => 'AG speichern')) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
    }

    private function admin_text_row(string $label, string $name, string $value, string $description = ''): void
    {
        $ui = flz_ui();
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo $ui->input('text', array('name' => $name, 'id' => $name, 'value' => $value, 'description' => $description, 'input_class' => 'regular-text', 'attrs' => array('aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</td></tr>';
    }

    private function admin_image_row(string $label, string $name, string $value): void
    {
        $ui = flz_ui();
        $preview = flz_ags_course_image_url($value);
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<div class="flz-ags-image-field">';
        echo '<img class="flz-ags-image-preview" data-flz-ags-image-preview src="' . esc_url($preview) . '" alt="">';
        echo $ui->input('url', array('name' => $name, 'id' => $name, 'value' => $value, 'placeholder' => 'https://...', 'input_class' => 'regular-text', 'attrs' => array('data-flz-ags-image-input' => true, 'aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_media(array('label' => 'Bild aus Mediathek wählen', 'attrs' => array('data-flz-ags-media-button' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_clear(array('label' => 'Bild entfernen', 'attrs' => array('data-flz-ags-clear-image' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</div><p class="description">URL eines Vorschaubilds. Über die Mediathek eingefügte Bilder bleiben in WordPress verwaltet.</p>';
        echo '</td></tr>';
    }

    private function admin_textarea_row(string $label, string $name, string $value, int $rows): void
    {
        $ui = flz_ui();
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo $ui->field(array('type' => 'textarea', 'name' => $name, 'id' => $name, 'value' => $value, 'rows' => $rows, 'input_class' => 'large-text', 'attrs' => array('aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</td></tr>';
    }

    private function admin_checkbox(string $name, string $label, bool $checked): void
    {
        echo flz_ui()->field(array('type' => 'checkbox', 'name' => $name, 'label' => $label, 'checked' => $checked, 'class' => 'flz-ags-admin-checkbox')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    }

    public function handle_save_course(): void
    {
        $this->assert_admin_permission();
        check_admin_referer('flz_ags_save_course');

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

        $slots = array();
        if (isset($_POST['slots']) && is_array($_POST['slots'])) {
            $slots = map_deep(wp_unslash($_POST['slots']), 'sanitize_text_field');
        }

        try {
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
            $this->redirect_admin_error(
                $error,
                'Speichern einer AG',
                'save-course',
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
        $ui = flz_ui();

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Einstellungen</h1>';
        if (isset($_GET['saved'])) {
            echo wp_kses_post(flz_ags_notice('Einstellungen gespeichert.'));
        }

        echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'nonce' => 'flz_ags_save_settings', 'hidden' => array('action' => 'flz_ags_save_settings'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="flz_ags_current_school_year">Aktuelles Schuljahr</label></th><td>';
        echo $ui->input('text', array('name' => 'current_school_year', 'id' => 'flz_ags_current_school_year', 'value' => flz_ags_current_school_year(), 'description' => 'Format: 2026/2027. AGs und Anmeldungen werden schuljahrbezogen geführt.', 'input_class' => 'regular-text', 'attrs' => array('aria-label' => 'Aktuelles Schuljahr'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="flz_ags_classes">Klassen/Kurse</label></th><td>';
        echo $ui->field(array('type' => 'textarea', 'name' => 'classes_text', 'id' => 'flz_ags_classes', 'value' => implode("\n", flz_ags_get_classes()), 'rows' => 12, 'description' => 'Eine Klasse/ein Kurs pro Zeile. Diese Liste wird im Anmeldeformular als Dropdown verwendet.', 'input_class' => 'large-text code', 'attrs' => array('aria-label' => 'Klassen/Kurse'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</td></tr>';
        echo '</tbody></table>';
        echo '<p>' . $ui->button_save(array('label' => 'Einstellungen speichern')) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
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

        flz_ags_safe_redirect(flz_ags_admin_url(array('page' => 'flz-ags-settings', 'saved' => 1)));
    }

    public function render_admin_demo_page(): void
    {
        $this->assert_admin_permission();
        $school_year = isset($_GET['school_year']) ? flz_ags_sanitize_school_year(sanitize_text_field(wp_unslash($_GET['school_year']))) : flz_ags_current_school_year();
        $demo = flz_ags_demo_courses();
        $ui = flz_ui();

        echo '<div class="wrap flz-ags-admin">';
        echo '<h1>FLZ AGs – Demo-Setup</h1>';
        if (isset($_GET['flz_ags_error'])) {
            $error_code = sanitize_key(wp_unslash($_GET['flz_ags_error']));
            echo wp_kses_post(flz_ags_notice(flz_ags_error_message($error_code), 'error'));
        }
        echo '<p>Legt eine Auswahl vorhandener AGs als Demo-Datensatz für das gewählte Schuljahr an. Vorhandene AGs mit gleichem Slug und Schuljahr werden nicht dupliziert.</p>';
        echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-demo'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_view(array('label' => 'Demo-Setup anzeigen', 'type' => 'submit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
        echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'nonce' => 'flz_ags_install_demo', 'hidden' => array('action' => 'flz_ags_install_demo', 'school_year' => $school_year))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo '<p>' . $ui->button_new(array('label' => 'Demo-AGs für ' . $school_year . ' anlegen', 'type' => 'submit')) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.

        echo '<h2>Enthaltene Demo-AGs</h2>';
        echo '<div class="flz-ags-grid flz-ags-demo-grid">';
        foreach ($demo as $course) {
            $demo_card_args = array(
                'class'     => 'flz-ags-card',
                'image_url' => $course['image_url'],
                'image_alt' => $course['title'],
                'title'     => $course['title'],
                'text'      => $course['short_description'],
                'meta'      => array(
                    'Bereich'   => $course['category'],
                    'Jahrgänge' => $course['allowed_grades'],
                ),
            );
            echo $ui->card($demo_card_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Demo-Karte.
        }
        echo '</div>';
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
                            'info_url' => esc_url_raw($course_data['info_url']),
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
            'registration_url' => '',
        ), (array) $atts, 'flz_ag_liste');

        $school_year = flz_ags_sanitize_school_year($atts['school_year']);
        $registration_url = esc_url_raw((string) $atts['registration_url']);
        try {
            $courses = $this->get_public_courses_with_slots($school_year);
        } catch (Throwable $error) {
            flz_ags_log_error($error, 'Anzeigen der öffentlichen AG-Liste');
            return '<div class="flz-ags">' . flz_ags_notice('Die AG-Liste kann derzeit nicht geladen werden. Bitte später erneut versuchen.', 'error') . '</div>';
        }

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
        $ui = flz_ui();
        echo '<div class="flz-ags-filters">';
        if ($show_class_filter && $include_classes) {
            echo $ui->field(array('type' => 'select', 'name' => 'class_name', 'label' => 'Klasse/Kurs', 'required' => true, 'placeholder' => '– Bitte auswählen –', 'options' => array_combine(flz_ags_get_classes(), flz_ags_get_classes()), 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        } elseif ($show_class_filter) {
            echo $ui->field(array('type' => 'select', 'name' => 'class_filter', 'label' => 'Klasse/Kurs', 'placeholder' => 'alle anzeigen', 'options' => array_combine(flz_ags_get_classes(), flz_ags_get_classes()), 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        }

        echo $ui->field(array('type' => 'select', 'name' => 'weekday_filter', 'label' => 'Wochentag', 'placeholder' => 'alle Tage', 'options' => $weekdays, 'attrs' => array('data-flz-ags-weekday-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '</div>';
    }

    private function render_course_card(object $course, string $registration_url = ''): void
    {
        $ui = flz_ui();
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
        $meta = array();
        if (!empty($course->category)) {
            $meta['Bereich'] = $course->category;
        }
        if (!empty($course->leader_name)) {
            $meta['Leitung'] = $course->leader_name;
        }
        $meta['Zielgruppe'] = $target;
        $meta['Termine'] = implode(' | ', $slot_lines);
        $meta['Plätze'] = $total_max > 0 ? $total_free . ' frei von ' . $total_max : 'keine Begrenzung hinterlegt';

        $actions = array();
        if (!empty($course->info_url)) {
            $actions[] = array(
                'href'     => $course->info_url,
                'label'    => 'Details anzeigen',
                'variant'  => 'secondary',
                'icon'     => 'view',
                'icon_alt' => 'Details anzeigen',
            );
        }
        if ($registration_url !== '' && !$all_full && !empty($course->registration_open)) {
            $actions[] = array(
                'href'     => $registration_url,
                'label'    => 'Zur Anmeldung',
                'variant'  => 'primary',
                'icon'     => 'check',
                'icon_alt' => 'Zur Anmeldung',
            );
        }

        $course_card_args = array(
            'class'     => 'flz-ags-card flz-ags-course-card',
            'attrs'     => array(
                'data-flz-ags-filter-item' => true,
                'data-weekdays'            => implode(',', $weekdays),
                'data-only-grade-7'        => (int) $course->only_grade_7,
                'data-allowed-grades'      => $course->allowed_grades,
                'data-full'                => $all_full ? '1' : '0',
            ),
            'image_url' => flz_ags_course_image_url($course->image_url ?? ''),
            'image_alt' => (string) $course->title,
            'title'     => (string) $course->title,
            'text'      => (string) $course->short_description,
            'meta'      => $meta,
            'badge'     => $all_full ? 'ausgebucht' : '',
            'actions'   => $actions,
        );
        echo $ui->card($course_card_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die öffentliche AG-Karte.
    }

    private function render_slot_card(object $slot, string $registration_url = ''): void
    {
        $ui = flz_ui();
        $taken = $this->count_active_registrations((int) $slot->id);
        $max = (int) $slot->max_participants;
        $free = $max > 0 ? max(0, $max - $taken) : null;
        $is_full = $max > 0 && $taken >= $max;

        $meta = array(
            'Zeit' => flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time),
        );
        if (!empty($slot->room)) {
            $meta['Raum'] = $slot->room;
        }
        if (!empty($slot->leader_name)) {
            $meta['Leitung'] = $slot->leader_name;
        }
        $meta['Zielgruppe'] = $slot->only_grade_7 ? 'nur Klasse 7' : ($slot->allowed_grades ? 'Jahrgänge ' . $slot->allowed_grades : 'alle Jahrgänge');
        $meta['Plätze'] = $max > 0 ? ($free . ' frei von ' . $max) : 'keine Begrenzung hinterlegt';

        $actions = array();
        if ($registration_url !== '' && !$is_full && !empty($slot->registration_open)) {
            $actions[] = array(
                'href'     => $registration_url,
                'label'    => 'Zur Anmeldung',
                'variant'  => 'primary',
                'icon'     => 'check',
                'icon_alt' => 'Zur Anmeldung',
            );
        }

        $slot_card_args = array(
            'class'     => 'flz-ags-card flz-ags-slot-option',
            'attrs'     => array(
                'data-flz-ags-filter-item' => true,
                'data-weekday'             => $slot->weekday,
                'data-only-grade-7'        => (int) $slot->only_grade_7,
                'data-allowed-grades'      => $slot->allowed_grades,
                'data-full'                => $is_full ? '1' : '0',
            ),
            'image_url' => flz_ags_course_image_url($slot->image_url ?? ''),
            'image_alt' => (string) $slot->title,
            'title'     => (string) $slot->title,
            'text'      => (string) $slot->short_description,
            'meta'      => $meta,
            'badge'     => $is_full ? 'ausgebucht' : '',
            'actions'   => $actions,
        );
        echo $ui->card($slot_card_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die öffentliche Slot-Karte.
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
        try {
            $ui = flz_ui();
            echo '<div class="flz-ags flz-ags-registration">';
            echo '<h2>AG-Anmeldung ' . esc_html($school_year) . '</h2>';

            foreach ($messages as $message) {
                echo $ui->notice($message, $success ? 'success' : 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice.
            }

            if (!$success) {
                $this->render_registration_form($school_year);
            }

            echo '</div>';
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            flz_ags_log_error($error, 'Anzeigen der AG-Anmeldung');
            return '<div class="flz-ags">' . flz_ags_notice('Die AG-Anmeldung kann derzeit nicht geladen werden. Bitte später erneut versuchen.', 'error') . '</div>';
        }
    }

    private function render_registration_form(string $school_year): void
    {
        $slots = array_filter($this->get_public_slots($school_year), static function ($slot) {
            return !empty($slot->registration_open);
        });
        $ui = flz_ui();

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

        echo $ui->form_start(array('method' => 'post', 'class' => 'flz-ags-registration-form', 'nonce' => 'flz_ags_frontend_registration', 'nonce_name' => 'flz_ags_nonce', 'hidden' => array('flz_ags_registration_submit' => '1'), 'attrs' => array('data-flz-ags-registration-form' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo '<div class="flz-ags-form-grid">';
        echo $ui->field(array('type' => 'select', 'name' => 'class_name', 'label' => 'Klasse/Kurs', 'value' => $posted['class_name'], 'required' => true, 'placeholder' => '– Bitte auswählen –', 'options' => array_combine(flz_ags_get_classes(), flz_ags_get_classes()), 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->input('text', array('name' => 'student_first_name', 'label' => 'Vorname Schüler*in', 'value' => $posted['student_first_name'], 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->input('text', array('name' => 'student_last_name', 'label' => 'Nachname Schüler*in', 'value' => $posted['student_last_name'], 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->input('email', array('name' => 'guardian_email', 'label' => 'E-Mail Erziehungsberechtigte*r / Kontakt', 'value' => $posted['guardian_email'], 'autocomplete' => 'email')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
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
                $time_label = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time);
                $target_label = $slot->only_grade_7 ? 'nur Klasse 7' : ($slot->allowed_grades ? 'Jahrgänge ' . $slot->allowed_grades : 'alle Jahrgänge');

                $slot_choice_args = array(
                    'name'      => 'slot_id',
                    'value'     => $slot->id,
                    'checked'   => $checked,
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
                    'title'     => (string) $slot->title,
                    'kicker'    => $target_label,
                    'meta'      => array(
                        'Zeit'   => $time_label,
                        'Raum'   => $slot->room,
                        'Plätze' => $free_label,
                    ),
                    'badge'     => $is_full ? 'ausgebucht' : '',
                );
                echo $ui->choice_card($slot_choice_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die auswählbare Slot-Karte.
            }
        }
        echo '</fieldset>';

        echo $ui->field(array('type' => 'checkbox', 'name' => 'consent_privacy', 'label' => 'Ich habe die Datenschutzhinweise zur AG-Anmeldung zur Kenntnis genommen und stimme der Verarbeitung der Angaben für die AG-Anmeldung zu.', 'required' => true, 'class' => 'flz-ags-consent')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo '<p>' . $ui->button(array('label' => 'AG verbindlich anmelden', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'check', 'icon_alt' => 'AG verbindlich anmelden')) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
    }

    private function handle_frontend_registration(string $school_year): array
    {
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

        try {
            return FLZ_AGS_Model::transaction(
                function () use ($school_year, $class_name, $first_name, $last_name, $guardian_email, $slot_id, $consent): array {
                    // Die Sperre serialisiert Kapazitätsprüfungen und Insert für diesen Termin.
                    $slot = $this->get_slot_with_course($slot_id, true);
                    if (
                        !$slot
                        || $slot->school_year !== $school_year
                        || empty($slot->is_active)
                        || empty($slot->course_active)
                        || empty($slot->course_visible)
                        || empty($slot->registration_open)
                    ) {
                        return array('success' => false, 'messages' => array('Der gewählte AG-Slot ist nicht verfügbar.'));
                    }

                    if (!flz_ags_grade_is_allowed($class_name, (string) $slot->allowed_grades, !empty($slot->only_grade_7))) {
                        return array('success' => false, 'messages' => array('Dieser AG-Slot ist für die gewählte Klasse nicht freigegeben.'));
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
                        'guardian_email' => $guardian_email,
                        'status' => 'active',
                        'consent_privacy' => $consent,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
                    $registration->save();

                    return array(
                        'success' => true,
                        'messages' => array('Die AG-Anmeldung wurde gespeichert. Die Teilnahme gilt bis auf Widerruf.'),
                    );
                },
                'Prüfen und Speichern einer AG-Anmeldung'
            );
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
        $ui = flz_ui();
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

        echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-registrations'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
        echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->field(array('type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $status, 'options' => flz_ags_status_labels())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_filter(array('label' => 'Anmeldungen filtern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->button_export(array('href' => wp_nonce_url(admin_url('admin-post.php?action=flz_ags_export_csv&school_year=' . rawurlencode($school_year) . '&status=' . rawurlencode($status)), 'flz_ags_export_csv'), 'label' => 'CSV exportieren')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.

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
            echo '<td>' . esc_html(flz_ui_format_datetime($registration->created_at, (string) $registration->created_at)) . '</td>';
            echo '<td>';
            if ($registration->status === 'active') {
                echo $ui->action_form_button(array('preset' => 'reset', 'label' => 'Anmeldung widerrufen', 'method' => 'post', 'action' => admin_url('admin-post.php'), 'nonce' => 'flz_ags_update_registration', 'hidden' => array('action' => 'flz_ags_update_registration', 'registration_id' => $registration->id, 'new_status' => 'withdrawn'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
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

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_key(wp_unslash($_POST['new_status'])) : '';

        if ($registration_id <= 0 || !array_key_exists($new_status, flz_ags_status_labels())) {
            wp_die('Ungültige Anfrage.');
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
                    $row->class_name,
                    $row->grade_key,
                    $row->student_last_name,
                    $row->student_first_name,
                    $row->guardian_email,
                    $row->title,
                    flz_ags_weekday_label($row->weekday),
                    flz_ags_format_time($row->start_time),
                    flz_ags_format_time($row->end_time),
                    $row->room,
                    flz_ui_format_datetime($row->created_at, (string) $row->created_at),
                );
            }
            $csv = flz_wpdb_objects_build_csv_string(
                array('Schuljahr', 'Status', 'Klasse', 'Jahrgang', 'Nachname', 'Vorname', 'E-Mail', 'AG', 'Wochentag', 'Beginn', 'Ende', 'Raum', 'Anmeldedatum'),
                $csv_rows
            );
        } catch (Throwable $error) {
            $this->redirect_admin_error(
                $error,
                'Erstellen des AG-CSV-Exports',
                'export',
                array('page' => 'flz-ags-registrations', 'school_year' => $school_year, 'status' => $status)
            );
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="flz-ag-anmeldungen-' . sanitize_file_name($school_year) . '-' . sanitize_file_name($status) . '.csv"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Kontrolliert erzeugte CSV-Datei, kein HTML-Kontext.
        echo $csv;
        exit;
    }
}
