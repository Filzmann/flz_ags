<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObject;
use flz_wpdb_objects\FlzWpdbObjectsException;
use flz_wpdb_objects\FlzWpdbTransaction;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Gemeinsame Datenbankbasis der AG-Modelle.
 *
 * Einfache CRUD-Operationen erbt das Modell von FlzWpdbObject. Die hier
 * gekapselten Helfer sind nur für die wenigen fachlich notwendigen JOINs und
 * für atomare, aus mehreren Schreibvorgängen bestehende Abläufe vorgesehen.
 */
abstract class FLZ_AGS_Model extends FlzWpdbObject
{
    /**
     * Führt eine vorbereitete Leseabfrage aus und prüft jeden Fehlerpfad.
     *
     * @throws FlzWpdbObjectsException Bei Datenbank- oder Abfragefehlern.
     */
    protected static function query_rows(string $sql, array $values, string $operation, string $output = OBJECT): array
    {
        global $wpdb;

        try {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL und Platzhalter stammen ausschließlich aus dem jeweiligen Modell.
            $prepared_sql = empty($values) ? $sql : $wpdb->prepare($sql, $values);
        } catch (Throwable $error) {
            throw FlzWpdbObjectsException::operation(
                'Vorbereiten der Datenbankabfrage',
                $operation,
                $error
            );
        }

        if (!is_string($prepared_sql) || $prepared_sql === '') {
            throw FlzWpdbObjectsException::database(
                $operation,
                static::table_name(),
                (string) $wpdb->last_error
            );
        }

        try {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Die Abfrage wurde direkt zuvor kontrolliert vorbereitet.
            $rows = $wpdb->get_results($prepared_sql, $output);
        } catch (Throwable $error) {
            throw FlzWpdbObjectsException::operation($operation, static::table_name(), $error);
        }

        if (!is_array($rows) || $wpdb->last_error !== '') {
            throw FlzWpdbObjectsException::database(
                $operation,
                static::table_name(),
                (string) $wpdb->last_error
            );
        }

        return $rows;
    }

    /**
     * Hydriert kontrolliert alle Ergebniszeilen als konkretes AG-Modell.
     *
     * @throws FlzWpdbObjectsException Bei unerwarteten Ergebnisformaten.
     */
    protected static function query_models(string $sql, array $values, string $operation): array
    {
        $models = array();
        foreach (static::query_rows($sql, $values, $operation) as $row) {
            if (!is_object($row)) {
                throw FlzWpdbObjectsException::invalid_model_state(
                    static::class,
                    'Eine Datenbankzeile besitzt nicht das erwartete Objektformat.'
                );
            }
            $models[] = static::createObjectFromResult($row);
        }

        return $models;
    }

    /**
     * Führt einen mehrteiligen Schreibvorgang innerhalb einer Transaktion aus.
     *
     * Die Callback-Exception bleibt als vorherige Exception erhalten. Schlägt
     * zusätzlich das Rollback fehl, enthält die neue Meldung beide Ursachen.
     *
     * @throws FlzWpdbObjectsException Bei Start-, Callback-, Commit- oder Rollbackfehlern.
     */
    public static function transaction(callable $callback, string $operation)
    {
        return FlzWpdbTransaction::run($callback, $operation);
    }
}
