<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<?php echo $ui->form_start(array('method' => 'post', 'class' => 'flz-ags-registration-form', 'nonce' => 'flz_ags_frontend_registration', 'nonce_name' => 'flz_ags_nonce', 'hidden' => array('flz_ags_registration_submit' => '1', 'flz_ags_course_id' => (int) $course->id), 'attrs' => array('data-flz-ags-registration-form' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<div class="flz-ags-form-grid">
		<?php echo $ui->field(array('type' => 'select', 'name' => 'class_name', 'label' => 'Klasse', 'value' => $posted['class_name'], 'required' => true, 'placeholder' => '– Bitte auswählen –', 'options' => flz_ags_class_options(), 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->input('text', array('name' => 'student_first_name', 'label' => 'Vorname Schüler*in', 'value' => $posted['student_first_name'], 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->input('text', array('name' => 'student_last_name', 'label' => 'Nachname Schüler*in', 'value' => $posted['student_last_name'], 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->input('email', array('name' => 'student_email', 'label' => 'E-Mail Schüler*in', 'value' => $posted['student_email'], 'required' => true, 'autocomplete' => 'email')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	</div>

	<fieldset class="flz-ags-slot-fieldset">
		<legend>AG-Slot auswählen</legend>
		<?php if (empty($slot_choices)) : ?>
			<p>Für diese AG sind derzeit keine Anmeldungen möglich.</p>
		<?php else : ?>
			<?php foreach ($slot_choices as $slot_choice_args) : ?>
				<?php echo $ui->choice_card($slot_choice_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die auswählbare Slot-Karte. ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</fieldset>

	<?php echo $ui->field(array('type' => 'checkbox', 'name' => 'consent_privacy', 'label' => 'Ich habe die Datenschutzhinweise zur AG-Anmeldung zur Kenntnis genommen und stimme der Verarbeitung der Angaben für die AG-Anmeldung zu.', 'required' => true, 'class' => 'flz-ags-consent')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<p><?php echo $ui->button(array('label' => 'AG verbindlich anmelden', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'check', 'icon_alt' => 'AG verbindlich anmelden')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?></p>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
