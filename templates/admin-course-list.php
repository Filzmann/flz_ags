<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<p>
	<?php echo $ui->button_new(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'new')), 'label' => 'Neue AG anlegen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_view(array('href' => flz_ags_admin_url(array('page' => 'flz-ags-demo')), 'label' => 'Demo-Setup anzeigen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
</p>

<?php echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year, 'placeholder' => '2026/2027', 'class' => 'flz-ui-field--inline')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_filter(array('label' => 'AG-Liste filtern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<table class="widefat striped">
	<thead>
		<tr>
			<th>Bild</th>
			<th>AG</th>
			<th>Bereich</th>
			<th>Zielgruppe</th>
			<th>Slots</th>
			<th>Status</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php if (empty($courses)) : ?>
		<tr><td colspan="7">Keine AGs für dieses Schuljahr angelegt.</td></tr>
	<?php endif; ?>
	<?php foreach ($courses as $course) : ?>
		<?php
		$slots = FLZ_AGS_Slot::find_for_course((int) $course->id, false);
		$slot_labels = array();
		foreach ($slots as $slot) {
			$slot_labels[] = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time) . ($slot->room ? ', ' . $slot->room : '');
		}
		$target = $course->only_grade_7 ? 'nur Klasse 7' : flz_ags_allowed_grades_label((string) $course->allowed_grades);
		$status = array(
			$course->is_active ? 'aktiv' : 'inaktiv',
			$course->is_visible ? 'sichtbar' : 'versteckt',
			$course->registration_open ? 'Anmeldung offen' : 'Anmeldung geschlossen',
		);
		?>
		<tr>
			<td><img class="flz-ags-admin-thumb" src="<?php echo esc_url(flz_ags_course_image_url($course->image_url ?? '')); ?>" alt=""></td>
			<td><strong><?php echo esc_html($course->title); ?></strong><br><small><?php echo esc_html($course->school_year); ?></small></td>
			<td><?php echo esc_html((string) $course->category); ?></td>
			<td><?php echo esc_html($target); ?></td>
			<td><?php echo esc_html(implode(' | ', $slot_labels)); ?></td>
			<td><?php echo esc_html(implode(', ', $status)); ?></td>
			<td>
				<?php echo $ui->button_edit(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => (int) $course->id)), 'label' => 'AG bearbeiten')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
