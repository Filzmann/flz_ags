<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
$class_options = $include_classes ? flz_ags_class_options() : flz_ags_grade_options();
?>
<div class="flz-ags-filters">
	<?php if ($include_classes) : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'class_name', 'label' => 'Klasse', 'required' => true, 'placeholder' => '– Bitte auswählen –', 'options' => $class_options, 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php else : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'class_filter', 'label' => 'Jahrgang', 'placeholder' => 'alle anzeigen', 'options' => $class_options, 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php endif; ?>

	<?php if (!$include_classes) : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'weekday_filter', 'label' => 'Wochentag', 'placeholder' => 'alle Tage', 'options' => flz_ags_weekdays(), 'attrs' => array('data-flz-ags-weekday-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php endif; ?>
</div>
