<?php

defined('ABSPATH') || exit;

$filter_attrs = is_array($filter_attrs ?? null) ? $filter_attrs : array();
?>
<div
	class="flz-ags-course-entry"
	data-flz-ags-filter-item
	data-weekdays="<?php echo esc_attr((string) ($filter_attrs['data-weekdays'] ?? '')); ?>"
	data-only-grade-7="<?php echo esc_attr((string) ($filter_attrs['data-only-grade-7'] ?? '0')); ?>"
	data-allowed-grades="<?php echo esc_attr((string) ($filter_attrs['data-allowed-grades'] ?? '')); ?>"
	data-full="<?php echo esc_attr((string) ($filter_attrs['data-full'] ?? '0')); ?>"
>
	<?php echo flz_ui()->card($card_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die öffentliche AG-Karte. ?>
	<?php if ((string) $registration_panel_html !== '') : ?>
		<?php echo (string) $registration_panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Der UI-Renderer kapselt das bereits sicher gerenderte Anmeldeformular im zugänglichen Drawer. ?>
	<?php endif; ?>
</div>
