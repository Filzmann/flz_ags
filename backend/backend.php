<?php

defined('ABSPATH') || exit;

/**
 * Backend-Renderer für die AG-Verwaltung.
 *
 * Die Controllerklasse liefert Daten und Aktionen. Diese Funktionen halten die
 * Backend-Dateistruktur sichtbar getrennt und leiten größere HTML-Blöcke in
 * die Templates weiter.
 *
 * @param array<string,mixed> $vars Template-Variablen.
 */
function flz_ags_render_backend_template(string $template, array $vars = array()): void
{
    flz_ags_render_template('admin-' . $template, $vars);
}

