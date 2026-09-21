<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$frontend_filters = file_get_contents( dirname( __DIR__ ) . '/templates/frontend-filters.php' );
$frontend_card    = file_get_contents( dirname( __DIR__ ) . '/templates/frontend-course-card.php' );
$frontend_list    = file_get_contents( dirname( __DIR__ ) . '/templates/frontend-list.php' );
$admin_list       = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-list.php' );
$frontend_script  = file_get_contents( dirname( __DIR__ ) . '/assets/js/flz-ags.js' );
$admin_script     = file_get_contents( dirname( __DIR__ ) . '/assets/js/flz-ags-admin.js' );
$style_source     = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );

if ( in_array( false, array( $frontend_filters, $frontend_card, $frontend_list, $admin_list, $frontend_script, $admin_script, $style_source ), true ) ) {
	throw new RuntimeException( 'Eine Datei des AG-Listenvertrags konnte nicht gelesen werden.' );
}

$checks = array(
	array(
		str_contains( $frontend_filters, "'name' => 'search_filter'" )
		&& str_contains( $frontend_filters, "'name' => 'category_filter'" )
		&& str_contains( $frontend_filters, "'name' => 'leader_filter'" )
		&& str_contains( $frontend_filters, "'name' => 'weekday_filter'" )
		&& str_contains( $frontend_filters, "'name' => 'sort_filter'" ),
		'Das Frontend bietet nicht Suche, Dozenten-/Wochentagfilter und Sortierung gemeinsam an.',
	),
	array(
		str_contains( $frontend_filters, '<details class="flz-ags-filter-details">' )
		&& str_contains( $frontend_filters, '<summary>AG-Liste filtern und sortieren</summary>' )
		&& ! str_contains( $frontend_filters, '<details class="flz-ags-filter-details" open' ),
		'Die Frontend-Filter liegen nicht in einem standardmäßig geschlossenen, semantischen Collapse.',
	),
	array(
		str_contains( $frontend_card, 'data-leader=' )
		&& str_contains( $frontend_card, 'data-category=' )
		&& str_contains( $frontend_card, 'data-search=' )
		&& str_contains( $frontend_card, 'data-sort-category=' )
		&& str_contains( $frontend_card, 'data-sort-grade=' )
		&& str_contains( $frontend_card, 'data-sort-weekday=' ),
		'Die Frontend-Karten stellen die kanonischen Filter- und Sortierwerte nicht als sichere Datenattribute bereit.',
	),
	array(
		str_contains( $frontend_list, 'data-flz-ags-items' )
		&& str_contains( $frontend_list, 'data-flz-ags-results-status' )
		&& str_contains( $frontend_list, 'data-flz-ags-no-results' ),
		'Die Frontend-Liste besitzt keinen sortierbaren Container und keine zugängliche Ergebnismeldung.',
	),
	array(
		str_contains( $style_source, '[data-flz-ags-filter-item][hidden]' )
		&& str_contains( $style_source, 'display: none !important;' ),
		'Das hidden-Attribut gefilterter Frontend-Karten wird von der Flex-Darstellung überschrieben.',
	),
	array(
		str_contains( $admin_list, "'name' => 'course_search'" )
		&& str_contains( $admin_list, "'name' => 'category_filter'" )
		&& str_contains( $admin_list, "'name' => 'grade_filter'" )
		&& str_contains( $admin_list, "'name' => 'leader_filter'" )
		&& str_contains( $admin_list, "'name' => 'weekday_filter'" ),
		'Das Backend bietet nicht Suche sowie Klassenstufen-, Dozenten- und Wochentagfilter gemeinsam an.',
	),
	array(
		str_contains( $admin_list, 'data-flz-ags-admin-sort-button="title"' )
		&& str_contains( $admin_list, 'data-flz-ags-admin-sort-button="category"' )
		&& str_contains( $admin_list, 'data-flz-ags-admin-sort-button="grade"' )
		&& str_contains( $admin_list, 'data-flz-ags-admin-sort-button="leader"' )
		&& str_contains( $admin_list, 'data-flz-ags-admin-sort-button="weekday"' )
		&& str_contains( $admin_list, 'aria-sort="none"' ),
		'Die Admin-Sortierung ist nicht in den fachlichen Tabellenköpfen untergebracht oder für Hilfsmittel ausgezeichnet.',
	),
	array(
		str_contains( $admin_list, 'class="flz-ags-admin-column-filter"' )
		&& str_contains( $admin_list, '<th scope="col"' )
		&& str_contains( $admin_list, '>Dozent<' )
		&& ! str_contains( $admin_list, 'class="flz-ags-admin-list-controls"' ),
		'Die Admin-Filter liegen nicht jeweils in ihrem Tabellenkopf oder die separate Steuerleiste besteht weiter.',
	),
	array(
		str_contains( $admin_list, "'data-flz-ags-admin-item' => true" )
		&& str_contains( $admin_list, "'data-leader'" )
		&& str_contains( $admin_list, "'data-search'" )
		&& str_contains( $admin_list, "'data-sort-grade'" )
		&& str_contains( $admin_list, "'data-sort-weekday'" ),
		'Die Admin-Zeilen stellen die kanonischen Filter- und Sortierwerte nicht bereit.',
	),
	array(
		str_contains( $frontend_script, '[data-flz-ags-search-input]' )
		&& str_contains( $frontend_script, '[data-flz-ags-category-select]' )
		&& str_contains( $frontend_script, '[data-flz-ags-leader-select]' )
		&& str_contains( $frontend_script, '[data-flz-ags-sort-select]' )
		&& str_contains( $frontend_script, "['default', 'title', 'category', 'grade', 'leader', 'weekday']" )
		&& str_contains( $frontend_script, "document.readyState === 'loading'" ),
		'Das Frontend-Script verarbeitet die neuen Eingaben oder die feste Sortier-Whitelist nicht.',
	),
	array(
		str_contains( $admin_script, '[data-flz-ags-admin-search]' )
		&& str_contains( $admin_script, '[data-flz-ags-admin-category]' )
		&& str_contains( $admin_script, '[data-flz-ags-admin-grade]' )
		&& str_contains( $admin_script, '[data-flz-ags-admin-leader]' )
		&& str_contains( $admin_script, '[data-flz-ags-admin-weekday]' )
		&& str_contains( $admin_script, '[data-flz-ags-admin-sort-button]' )
		&& str_contains( $admin_script, "setAttribute('aria-sort'" ),
		'Das Admin-Script verarbeitet nicht alle Listensteuerungen.',
	),
);

foreach ( $checks as list( $passed, $message ) ) {
	if ( ! $passed ) {
		throw new RuntimeException( $message );
	}
}

echo "OK: flz_ags course list discovery smoke test\n";
