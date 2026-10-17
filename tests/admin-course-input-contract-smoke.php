<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Declarative UI contract for the persisted integer course ordering.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$template = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-form.php' );
if ( false === $template ) {
	throw new RuntimeException( 'Admin course form could not be read.' );
}

if ( ! str_contains( $template, "'kleinere Zahl = weiter oben', 'number'" ) ) {
	throw new RuntimeException( 'Course sorting must use a number input.' );
}

echo "OK: flz_ags admin course input contract\n";
