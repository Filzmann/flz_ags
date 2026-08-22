<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );

// Test-Doubles werten die manipulierten Requests aus und transportieren Redirects als Exceptions.
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.EscapeOutput.ExceptionNotEscaped

final class FlzAgsQuickEditRedirect extends RuntimeException {}

final class FLZ_AGS_Course {
	public static ?self $stored = null;
	public static int $save_count = 0;

	public int $id = 17;
	public string $school_year = '2026/2027';
	public string $title = 'Vorher';
	public string $slug = 'vorher';
	public string $short_description = 'Alt';
	public string $category = 'Alt';
	public string $leader_name = 'Alt';
	public string $allowed_grades = '7';
	public int $detail_page_id = 0;
	public int $only_grade_7 = 1;
	public int $is_active = 1;
	public int $is_visible = 1;
	public int $registration_open = 1;
	public int $sort_order = 0;
	public string $updated_at = '';

	public static function get_by_id( int $course_id ): ?self {
		return 17 === $course_id ? self::$stored : null;
	}

	public function save(): void {
		++self::$save_count;
	}
}

$flz_ags_quick_edit_capability = true;
$flz_ags_quick_edit_nonce      = true;
$flz_ags_quick_edit_redirect   = '';
$flz_ags_quick_edit_page_status = 'publish';

function flz_ags_manage_capability(): string {
	return 'manage_options';
}

function current_user_can( $capability ): bool {
	global $flz_ags_quick_edit_capability;
	return $flz_ags_quick_edit_capability && 'manage_options' === $capability;
}

function check_admin_referer( $action ): void {
	global $flz_ags_quick_edit_nonce;
	$expected = 'flz_ags_quick_edit_course_' . absint( $_POST['course_id'] ?? 0 );
	if ( ! $flz_ags_quick_edit_nonce || $expected !== $action ) {
		throw new RuntimeException( 'invalid-nonce' );
	}
}

function wp_die( $message ): void {
	throw new RuntimeException( (string) $message );
}

function esc_html__( $message ): string {
	return (string) $message;
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_text_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_title( $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $value ) ) );
}

function flz_ags_sanitize_allowed_grades( $value ): string {
	preg_match_all( '/(?:^|[^0-9])(7|8|9|10|11|12)(?=[^0-9]|$)/', (string) $value, $matches );
	return implode( ',', array_values( array_unique( $matches[1] ) ) );
}

function flz_ags_sanitize_school_year( $value ): string {
	return preg_match( '/^\d{4}\/\d{4}$/', (string) $value ) ? (string) $value : '2026/2027';
}

function current_time(): string {
	return '2026-08-22 12:00:00';
}

function get_post_status(): string {
	global $flz_ags_quick_edit_page_status;
	return $flz_ags_quick_edit_page_status;
}

function flz_ags_admin_url( array $args ): string {
	return '/wp-admin/admin.php?' . http_build_query( $args );
}

function flz_ags_safe_redirect( string $url ): void {
	global $flz_ags_quick_edit_redirect;
	$flz_ags_quick_edit_redirect = $url;
	throw new FlzAgsQuickEditRedirect( $url );
}

function flz_ags_log_error(): void {}

require_once dirname( __DIR__ ) . '/includes/class-flz-ags.php';

$plugin  = ( new ReflectionClass( FLZ_AGS_Plugin::class ) )->newInstanceWithoutConstructor();
if ( ! method_exists( $plugin, 'handle_quick_edit_course' ) ) {
	throw new RuntimeException( 'Der geschützte Quick-Edit-Endpunkt fehlt.' );
}
$request = array(
	'course_id'          => '17',
	'school_year_return' => '2026/2027',
	'title'              => '  Neue <b>AG</b>  ',
	'short_description'  => " Kurz <script>weg</script> ",
	'category'           => ' Musik ',
	'leader_name'        => ' Leitung ',
	'allowed_grades'     => '7.2, 9, 13',
	'is_active'          => '1',
	'is_visible'         => '1',
	'sort_order'         => '-3',
);

$run = static function ( bool $allowed, bool $nonce_valid, array $post ) use ( $plugin ): ?Throwable {
	global $flz_ags_quick_edit_capability, $flz_ags_quick_edit_nonce;
	$flz_ags_quick_edit_capability = $allowed;
	$flz_ags_quick_edit_nonce      = $nonce_valid;
	$_POST                         = $post;

	try {
		$plugin->handle_quick_edit_course();
	} catch ( Throwable $error ) {
		return $error;
	}

	return null;
};

FLZ_AGS_Course::$stored     = new FLZ_AGS_Course();
FLZ_AGS_Course::$save_count = 0;
$denied = $run( false, true, $request );
if ( ! $denied instanceof RuntimeException || 0 !== FLZ_AGS_Course::$save_count ) {
	throw new RuntimeException( 'Quick-Edit verändert Daten ohne Capability.' );
}

$invalid_nonce = $run( true, false, $request );
if ( ! $invalid_nonce instanceof RuntimeException || 0 !== FLZ_AGS_Course::$save_count ) {
	throw new RuntimeException( 'Quick-Edit verändert Daten mit ungültigem Nonce.' );
}

$missing = $request;
$missing['course_id'] = '99';
$missing_course = $run( true, true, $missing );
if ( ! $missing_course instanceof Throwable || 0 !== FLZ_AGS_Course::$save_count ) {
	throw new RuntimeException( 'Quick-Edit verändert Daten für eine fremde oder fehlende AG-ID.' );
}

FLZ_AGS_Course::$stored->detail_page_id = 33;
$closed_page = $request;
$closed_page['registration_open'] = '1';
global $flz_ags_quick_edit_page_status;
$flz_ags_quick_edit_page_status = 'draft';
$invalid_registration = $run( true, true, $closed_page );
if ( ! $invalid_registration instanceof Throwable || 0 !== FLZ_AGS_Course::$save_count ) {
	throw new RuntimeException( 'Quick-Edit öffnet eine Anmeldung mit unveröffentlichter Detailseite.' );
}
FLZ_AGS_Course::$stored->detail_page_id = 0;
$flz_ags_quick_edit_page_status = 'publish';

$saved = $run( true, true, $request );
$course = FLZ_AGS_Course::$stored;
if ( ! $saved instanceof FlzAgsQuickEditRedirect || 1 !== FLZ_AGS_Course::$save_count ) {
	throw new RuntimeException( 'Berechtigtes Quick-Edit speichert die AG nicht genau einmal.' );
}
if (
	'Neue AG' !== $course->title
	|| 'neue-ag' !== $course->slug
	|| 'Kurz weg' !== $course->short_description
	|| 'Musik' !== $course->category
	|| 'Leitung' !== $course->leader_name
	|| '7,9' !== $course->allowed_grades
	|| 0 !== $course->only_grade_7
	|| 1 !== $course->is_active
	|| 1 !== $course->is_visible
	|| 0 !== $course->registration_open
	|| -3 !== $course->sort_order
	|| '2026-08-22 12:00:00' !== $course->updated_at
) {
	throw new RuntimeException( 'Quick-Edit sanitizt oder begrenzt die erlaubten AG-Felder nicht korrekt.' );
}

global $flz_ags_quick_edit_redirect;
if ( ! str_contains( $flz_ags_quick_edit_redirect, 'page=flz-ags' ) || ! str_contains( $flz_ags_quick_edit_redirect, 'school_year=2026%2F2027' ) ) {
	throw new RuntimeException( 'Quick-Edit kehrt nicht zur gefilterten AG-Liste zurück.' );
}

echo "OK: flz_ags quick edit security smoke test\n";
