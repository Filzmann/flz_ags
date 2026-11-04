<?php

defined( 'ABSPATH' ) || exit;
?>
<p class="description">Hier sehen Sie ausschließlich die Anmeldungen für Ihre zugewiesenen AGs. Änderungen, Exporte und Importe sind nicht möglich.</p>
<h2>Meine AGs</h2>
<?php if ( empty( $courses ) ) : ?>
	<p>Für dieses Schuljahr sind Ihnen keine AGs zugeordnet.</p>
<?php else : ?>
	<ul>
		<?php foreach ( $courses as $course ) : ?>
			<li><?php echo esc_html( (string) $course->title ); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<h2>Anmeldungen</h2>
<?php
$empty_message = 'Für dieses Schuljahr sind Ihnen keine AG-Anmeldungen zugeordnet.';
require __DIR__ . '/admin-readonly-registration-table.php';
