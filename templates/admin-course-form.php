<?php

defined('ABSPATH') || exit;

$ui = flz_ui();

$admin_text_row = static function (string $label, string $name, string $value, string $description = '') use ($ui): void {
    echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
    echo $ui->input('text', array('name' => $name, 'id' => $name, 'value' => $value, 'description' => $description, 'input_class' => 'regular-text', 'attrs' => array('aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo '</td></tr>';
};

$admin_image_row = static function (string $label, string $name, string $value) use ($ui): void {
    $preview = flz_ags_course_image_url($value);
    echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
    echo '<div class="flz-ags-image-field">';
    echo '<img class="flz-ags-image-preview" data-flz-ags-image-preview src="' . esc_url($preview) . '" alt="">';
    echo $ui->input('url', array('name' => $name, 'id' => $name, 'value' => $value, 'placeholder' => 'https://...', 'input_class' => 'regular-text', 'attrs' => array('data-flz-ags-image-input' => true, 'aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo $ui->button_media(array('label' => 'Bild aus Mediathek wählen', 'attrs' => array('data-flz-ags-media-button' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo $ui->button_clear(array('label' => 'Bild entfernen', 'attrs' => array('data-flz-ags-clear-image' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo '</div><p class="description">URL eines Vorschaubilds. Über die Mediathek eingefügte Bilder bleiben in WordPress verwaltet.</p>';
    echo '</td></tr>';
};

$admin_page_picker_row = static function (
    string $label,
    string $hidden_name,
    string $search_id,
    int $page_id,
    string $empty_label,
    string $search_label,
    string $description,
    bool $allow_create = false,
    string $create_label = '',
    string $title_source = ''
) use ($ui): void {
    $safe_hidden_name = sanitize_key($hidden_name);
    $safe_search_id = sanitize_key($search_id);
    $safe_search_label = sanitize_text_field($search_label);
    $selected_label = $page_id > 0 ? flz_ags_page_label($page_id) : $empty_label;
    $selected_permalink = $page_id > 0 ? get_permalink($page_id) : '';
    $selected_edit_url = $page_id > 0 ? get_edit_post_link($page_id, '') : '';

    echo '<tr><th scope="row"><label for="' . esc_attr($safe_search_id) . '">' . esc_html($label) . '</label></th><td>';
    echo '<div class="flz-ags-page-field" data-flz-ags-page-field data-empty-label="' . esc_attr($empty_label) . '">';
    echo '<input type="hidden" name="' . esc_attr($safe_hidden_name) . '" data-flz-ags-page-id value="' . esc_attr((string) $page_id) . '">';
    echo '<div class="flz-ags-page-search-row">';
    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped das Suchfeld inklusive Attribute.
    echo $ui->input('text', array(
        'name' => $safe_hidden_name . '_search',
        'id' => $safe_search_id,
        'value' => '',
        'placeholder' => 'Seitentitel suchen …',
        'input_class' => 'regular-text',
        'attrs' => array(
            'autocomplete' => 'off',
            'data-flz-ags-page-search' => true,
            'aria-label' => $safe_search_label,
        ),
    ));
    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    if ($allow_create) {
        echo $ui->button_new(array('label' => $create_label, 'type' => 'button', 'attrs' => array('data-flz-ags-create-detail-page' => true, 'data-title-source' => $title_source))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    }
    echo $ui->button_clear(array('label' => 'Auswahl entfernen', 'type' => 'button', 'attrs' => array('data-flz-ags-clear-detail-page' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo '</div>';
    echo '<div class="flz-ags-page-selected" data-flz-ags-page-selected>';
    echo '<strong>' . esc_html($selected_label) . '</strong>';
    if (is_string($selected_edit_url) && $selected_edit_url !== '') {
        echo ' · <a href="' . esc_url($selected_edit_url) . '">bearbeiten</a>';
    }
    if (is_string($selected_permalink) && $selected_permalink !== '') {
        echo ' · <a href="' . esc_url($selected_permalink) . '" target="_blank" rel="noopener noreferrer">ansehen</a>';
    }
    echo '</div>';
    echo '<div class="flz-ags-page-results" data-flz-ags-page-results role="listbox" aria-live="polite"></div>';
    echo '<p class="description">' . esc_html($description) . '</p>';
    echo '</div>';
    echo '</td></tr>';
};

$admin_detail_page_row = static function (?object $course) use ($admin_page_picker_row): void {
    $detail_page_id = $course ? flz_ags_course_detail_page_id($course) : 0;
    $admin_page_picker_row(
        'Detailseite',
        'detail_page_id',
        'flz_ags_detail_page_search',
        $detail_page_id,
        'Keine Detailseite ausgewählt.',
        'AG-Detailseite suchen',
        'Die Anmeldung wird automatisch auf dieser AG-Detailseite angezeigt. Neue Detailseiten werden als Unterseite der eingestellten AG-Hauptseite veröffentlicht.',
        true,
        'Neue Detailseite anlegen',
        '#title'
    );
};

$admin_textarea_row = static function (string $label, string $name, string $value, int $rows) use ($ui): void {
    echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
    echo $ui->field(array('type' => 'textarea', 'name' => $name, 'id' => $name, 'value' => $value, 'rows' => $rows, 'input_class' => 'large-text', 'attrs' => array('aria-label' => $label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    echo '</td></tr>';
};

$admin_checkbox = static function (string $name, string $label, bool $checked): void {
    echo flz_ui()->field(array('type' => 'checkbox', 'name' => $name, 'label' => $label, 'checked' => $checked, 'class' => 'flz-ags-admin-checkbox')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
};

$weekdays = flz_ags_weekdays();
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
?>

<?php echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'class' => 'flz-ags-admin-form', 'nonce' => 'flz_ags_save_course', 'hidden' => array('action' => 'flz_ags_save_course', 'course_id' => $course_id))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<h2><?php echo esc_html($course ? 'AG bearbeiten' : 'Neue AG'); ?></h2>
	<table class="form-table" role="presentation">
		<tbody>
			<?php
			$admin_text_row('Schuljahr', 'school_year', $course->school_year ?? flz_ags_current_school_year(), 'Format: 2026/2027');
			$admin_text_row('Titel', 'title', $course->title ?? '', 'Pflichtfeld');
			$admin_text_row('Bereich/Kategorie', 'category', $course->category ?? '', 'z. B. Sport, Musik, Naturwissenschaften');
			$admin_text_row('Leitung', 'leader_name', $course->leader_name ?? '', 'Name oder Funktionsbezeichnung');
			$admin_image_row('Vorschaubild', 'image_url', $course->image_url ?? '');
			$admin_detail_page_row($course);
			$admin_textarea_row('Kurzbeschreibung', 'short_description', $course->short_description ?? '', 3);
			$admin_textarea_row('Beschreibung', 'description', $course->description ?? '', 6);
			$admin_text_row('Erlaubte Jahrgänge', 'allowed_grades', $course->allowed_grades ?? '', 'Leer = alle; erlaubt: 7,8,9,10,11,12. Einzelklassen wie 7.1 werden automatisch auf den Jahrgang 7 reduziert.');
			?>
			<tr>
				<th scope="row">Regeln/Status</th>
				<td>
					<?php
					$admin_checkbox('only_grade_7', 'nur Klasse 7', !empty($course->only_grade_7));
					$admin_checkbox('is_active', 'aktiv', $course ? !empty($course->is_active) : true);
					$admin_checkbox('is_visible', 'im Frontend sichtbar', $course ? !empty($course->is_visible) : true);
					$admin_checkbox('registration_open', 'Anmeldung geöffnet', $course ? !empty($course->registration_open) : true);
					?>
				</td>
			</tr>
			<?php $admin_text_row('Sortierung', 'sort_order', isset($course->sort_order) ? (string) $course->sort_order : '0', 'kleinere Zahl = weiter oben'); ?>
		</tbody>
	</table>

	<h2>Wöchentliche Slots</h2>
	<p>Eine Anmeldung bezieht sich auf genau einen aktiven Slot. Weitere Leerzeilen können über den Plus-Button ergänzt werden.</p>
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped die Field-Matrix inklusive Feldwerte, Optionen und Attribute.
	echo $ui->field_matrix(array(
		'id' => 'flz-ags-slots',
		'class' => 'widefat striped flz-ags-slots-table',
		'rows' => $slot_rows,
		'min_rows' => 1,
		'add_label' => 'Slot-Zeile hinzufügen',
		'hidden_fields' => array(
			array('name' => 'slots[{index}][id]', 'value_key' => 'id', 'default' => 0),
		),
		'columns' => array(
			array('label' => 'Aktiv', 'field' => array('type' => 'checkbox', 'name' => 'slots[{index}][is_active]', 'label' => 'aktiv', 'checked_key' => 'is_active')),
			array('label' => 'Wochentag', 'field' => array('type' => 'select', 'name' => 'slots[{index}][weekday]', 'value_key' => 'weekday', 'default' => '0', 'options' => array('0' => '–') + $weekdays, 'aria_label' => 'Wochentag')),
			array('label' => 'Beginn', 'field' => array('type' => 'time', 'name' => 'slots[{index}][start_time]', 'value_key' => 'start_time', 'aria_label' => 'Beginn')),
			array('label' => 'Ende', 'field' => array('type' => 'time', 'name' => 'slots[{index}][end_time]', 'value_key' => 'end_time', 'aria_label' => 'Ende')),
			array('label' => 'Raum', 'field' => array('type' => 'text', 'name' => 'slots[{index}][room]', 'value_key' => 'room', 'aria_label' => 'Raum')),
			array('label' => 'Max. TN', 'field' => array('type' => 'number', 'name' => 'slots[{index}][max_participants]', 'value_key' => 'max_participants', 'default' => 0, 'min' => 0, 'aria_label' => 'Maximale Teilnehmerzahl')),
			array('label' => 'Sortierung', 'field' => array('type' => 'number', 'name' => 'slots[{index}][sort_order]', 'value_key' => 'sort_order', 'default' => 0, 'aria_label' => 'Sortierung')),
		),
	));
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<p><?php echo $ui->button_save(array('label' => 'AG speichern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?></p>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
