<?php

defined('ABSPATH') || exit;

use flz_wpdb_objects\FlzWpdbObject;
use flz_wpdb_objects\FlzWpdbTransaction;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Gemeinsame Datenbankbasis der AG-Modelle.
 *
 * Einfache CRUD-Operationen und kontrollierte Custom-Queries erbt das Modell
 * von FlzWpdbObject. Diese Klasse bündelt nur noch den fachlichen Zugriff auf
 * Transaktionen, damit AG-Servicecode keine Infrastrukturdetails kennen muss.
 */
abstract class FLZ_AGS_Model extends FlzWpdbObject
{
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
