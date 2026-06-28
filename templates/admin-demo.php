<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<p>Legt aus den vorhandenen AG-Unterseiten Demo-Datensätze für das gewählte Schuljahr an. Vorhandene AGs mit gleichem Slug und Schuljahr werden nicht dupliziert.</p>

<?php echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-demo'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_view(array('label' => 'Demo-Setup anzeigen', 'type' => 'submit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<?php if (empty($demo)) : ?>
	<?php echo wp_kses_post(flz_ags_notice('Es wurden keine veröffentlichten AG-Unterseiten unter der eingestellten AG-Hauptseite gefunden.', 'error')); ?>
	<?php return; ?>
<?php endif; ?>

<?php echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'nonce' => 'flz_ags_install_demo', 'hidden' => array('action' => 'flz_ags_install_demo', 'school_year' => $school_year))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<p><?php echo $ui->button_new(array('label' => 'Demo-AGs für ' . $school_year . ' aus AG-Seiten anlegen', 'type' => 'submit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?></p>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<h2>Enthaltene Demo-AGs</h2>
<div class="flz-ags-grid flz-ags-demo-grid">
	<?php foreach ($demo as $course) : ?>
		<?php
		$demo_card_args = array(
			'class'     => 'flz-ags-card',
			'image_url' => $course['image_url'],
			'image_alt' => $course['title'],
			'title'     => $course['title'],
			'text'      => $course['short_description'],
			'meta'      => array(
				'Detailseite' => flz_ags_page_label((int) $course['detail_page_id']),
				'Jahrgänge'   => flz_ags_allowed_grades_label((string) $course['allowed_grades']),
				'Termine'     => count($course['slots']),
			),
		);
		?>
		<?php echo $ui->card($demo_card_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Demo-Karte. ?>
	<?php endforeach; ?>
</div>
