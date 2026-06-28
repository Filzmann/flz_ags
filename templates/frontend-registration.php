<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<div class="flz-ags flz-ags-registration">
	<h2><?php echo esc_html($heading); ?></h2>

	<?php foreach ((array) $messages as $message) : ?>
		<?php echo $ui->notice((string) $message, !empty($success) ? 'success' : 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endforeach; ?>

	<?php echo (string) $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formular wurde über UI-Komponenten sicher gerendert. ?>
</div>
