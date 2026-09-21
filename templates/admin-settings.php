<?php

defined('ABSPATH') || exit;

$ui = flz_ui();

$page_picker_row = static function (
    string $label,
    string $hidden_name,
    string $search_id,
    int $page_id,
    string $empty_label,
    string $search_label,
    string $description
) use ($ui): void {
    $selected_label = $page_id > 0 ? flz_ags_page_label($page_id) : $empty_label;
    $selected_permalink = $page_id > 0 ? get_permalink($page_id) : '';
    $selected_edit_url = $page_id > 0 ? get_edit_post_link($page_id, '') : '';
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($search_id); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <div class="flz-ags-page-field" data-flz-ags-page-field data-empty-label="<?php echo esc_attr($empty_label); ?>">
                <input type="hidden" name="<?php echo esc_attr($hidden_name); ?>" data-flz-ags-page-id value="<?php echo esc_attr((string) $page_id); ?>">
                <div class="flz-ags-page-search-row">
                    <?php echo $ui->input('text', array('name' => $hidden_name . '_search', 'id' => $search_id, 'value' => '', 'placeholder' => 'Seitentitel suchen …', 'input_class' => 'regular-text', 'attrs' => array('autocomplete' => 'off', 'data-flz-ags-page-search' => true, 'aria-label' => $search_label))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                    <?php echo $ui->button_clear(array('label' => 'Auswahl entfernen', 'type' => 'button', 'attrs' => array('data-flz-ags-clear-detail-page' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                </div>
                <div class="flz-ags-page-selected" data-flz-ags-page-selected>
                    <strong><?php echo esc_html($selected_label); ?></strong>
                    <?php if (is_string($selected_edit_url) && $selected_edit_url !== '') : ?>
                        · <a href="<?php echo esc_url($selected_edit_url); ?>">bearbeiten</a>
                    <?php endif; ?>
                    <?php if (is_string($selected_permalink) && $selected_permalink !== '') : ?>
                        · <a href="<?php echo esc_url($selected_permalink); ?>" target="_blank" rel="noopener noreferrer">ansehen</a>
                    <?php endif; ?>
                </div>
                <div class="flz-ags-page-results" data-flz-ags-page-results role="listbox" aria-live="polite"></div>
                <p class="description"><?php echo esc_html($description); ?></p>
            </div>
        </td>
    </tr>
    <?php
};
?>

<p class="description">Hier werden die Grunddaten gepflegt, die Lehrkräfte und Sekretariat im Alltag benötigen: aktuelles Schuljahr, öffentliche AG-Hauptseite und die Klassenliste für das Anmeldeformular.</p>

<?php echo $ui->form_start(array('method' => 'post', 'action' => admin_url('admin-post.php'), 'nonce' => 'flz_ags_save_settings', 'hidden' => array('action' => 'flz_ags_save_settings'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
    <div class="flz-ags-settings-grid">
        <section class="flz-ags-settings-card">
            <h2>Grunddaten</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="flz_ags_current_school_year">Aktuelles Schuljahr</label></th>
                        <td>
                            <?php echo $ui->input('text', array('name' => 'current_school_year', 'id' => 'flz_ags_current_school_year', 'value' => flz_ags_current_school_year(), 'description' => 'Format: 2026/2027. AGs und Anmeldungen werden schuljahrbezogen geführt.', 'input_class' => 'regular-text', 'attrs' => array('aria-label' => 'Aktuelles Schuljahr'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                    <?php
                    $page_picker_row(
                        'AG-Hauptseite',
                        'parent_page_id',
                        'flz_ags_parent_page_search',
                        $parent_page_id,
                        'Keine AG-Hauptseite ausgewählt.',
                        'AG-Hauptseite suchen',
                        'Unter dieser Seite werden neue AG-Detailseiten angelegt. Das Demo-Setup verwendet daraus nur Seitenbezug, Jahrgänge und Terminstruktur.'
                    );
                    ?>
                </tbody>
            </table>
            <p class="flz-ags-settings-summary"><strong>Aktuell verwendet:</strong> <?php echo esc_html($parent_hint); ?></p>
            <?php if ($configured_parent_page_id <= 0 && $parent_page_id > 0) : ?>
                <p class="description">Diese Seite wurde automatisch gefunden. Beim Speichern wird sie fest als AG-Hauptseite übernommen.</p>
            <?php endif; ?>
        </section>

        <section class="flz-ags-settings-card">
            <h2>Klassen im Anmeldeformular</h2>
            <p class="description">Schüler*innen wählen hier ihre echte Klasse, z. B. 7.1 oder 8.5. Für AG-Zielgruppen wird beim Absenden automatisch nur der Jahrgang geprüft.</p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="flz_ags_classes">Klassen im Anmeldeformular</label></th>
                        <td>
                            <?php echo $ui->field(array('type' => 'textarea', 'name' => 'classes_text', 'id' => 'flz_ags_classes', 'value' => implode("\n", flz_ags_get_classes()), 'rows' => 12, 'description' => 'Eine Klasse pro Zeile, z. B. 7.1 oder 8.5. Für AG-Zielgruppen und Slot-Prüfung wird daraus automatisch der Jahrgang abgeleitet.', 'input_class' => 'large-text code', 'attrs' => array('aria-label' => 'Klassen im Anmeldeformular'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Standardliste</th>
                        <td>
                            <?php echo $ui->field(array('type' => 'checkbox', 'name' => 'reset_classes', 'label' => 'Beim Speichern die Standard-Klassenliste wiederherstellen', 'description' => 'Hilfreich, wenn die Liste versehentlich gekürzt oder beschädigt wurde.')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="flz-ags-settings-card">
            <h2>Anmeldung</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Mehrfachanmeldung</th>
                        <td>
                            <?php echo $ui->field(array('type' => 'checkbox', 'name' => 'multiple_registrations_enabled', 'label' => 'Mehrere AG-Anmeldungen pro Schüler*in und Schuljahr erlauben', 'checked' => flz_ags_multiple_registrations_enabled(), 'description' => 'Ist diese Option aus, bleibt genau eine aktive AG-Anmeldung pro Schüler*in und Schuljahr erlaubt. Dieselbe AG kann nie doppelt aktiv gebucht werden.')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flz_ags_registration_form_notice">Hinweis im Anmeldeformular</label></th>
                        <td>
                            <?php echo $ui->field(array('type' => 'textarea', 'name' => 'registration_form_notice', 'id' => 'flz_ags_registration_form_notice', 'label' => 'Hinweis im Anmeldeformular', 'value' => flz_ags_registration_form_notice(), 'rows' => 3, 'description' => 'Dieser Hinweis wird oberhalb der Felder im öffentlichen Anmeldeformular angezeigt.')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="flz-ags-settings-card">
            <h2>Datenschutz und Aufbewahrung</h2>
            <p class="description">Die Frist zählt immer ab dem ursprünglichen Anmeldedatum. Ein Widerruf oder eine spätere Bearbeitung verlängert sie nicht.</p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Automatische Löschung</th>
                        <td>
                            <?php echo $ui->field(array('type' => 'checkbox', 'name' => 'registration_retention_enabled', 'label' => 'Alte Anmeldungen automatisch löschen', 'checked' => $registration_retention_enabled, 'description' => 'Standardmäßig ausgeschaltet. Nach Aktivierung prüft WordPress täglich, ob die Frist abgelaufen ist.')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flz_ags_registration_retention_months">Aufbewahrungsfrist</label></th>
                        <td>
                            <?php echo $ui->input('number', array('name' => 'registration_retention_months', 'id' => 'flz_ags_registration_retention_months', 'label' => 'Monate nach Anmeldedatum', 'value' => $registration_retention_months, 'min' => 1, 'max' => 120, 'description' => 'Zulässig sind 1 bis 120 Monate.')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="flz-ags-settings-card flz-ags-settings-help">
            <h2>Was bedeutet das?</h2>
            <ul>
                <li><strong>AG-Hauptseite:</strong> öffentliche Übersichtsseite, unter der die einzelnen AG-Detailseiten liegen.</li>
                <li><strong>Detailseiten:</strong> auf ihnen erscheint automatisch der Anmeldebutton mit Formular.</li>
                <li><strong>Klassen:</strong> nur für das Anmeldeformular. Die AG-Freigabe selbst arbeitet weiter mit Jahrgängen.</li>
            </ul>
        </section>
    </div>
    <p class="submit"><?php echo $ui->button_save(array('label' => 'Einstellungen speichern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?></p>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<section class="flz-ags-settings-card">
    <h2>Alte Anmeldungen jetzt löschen</h2>
    <p>Entfernt dauerhaft alle Anmeldungen, deren Anmeldedatum länger als <?php echo esc_html((string) $registration_retention_months); ?> Monate zurückliegt. Erstellen Sie bei Bedarf vorher auf der Seite „Anmeldungen“ ein CSV-Backup.</p>
    <?php
    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped Formular, Nonce und Felder vollständig.
    echo $ui->form_start(array(
        'method' => 'post',
        'action' => esc_url(admin_url('admin-post.php')),
        'nonce' => 'flz_ags_delete_old_registrations',
        'hidden' => array('action' => 'flz_ags_delete_old_registrations'),
    ));
    echo $ui->field(array(
        'type' => 'checkbox',
        'name' => 'confirm_delete_old_registrations',
        'label' => 'Ich bestätige die dauerhafte Löschung der abgelaufenen Anmeldungen.',
    ));
    echo $ui->button_delete(array(
        'label' => 'Alte Anmeldungen dauerhaft löschen',
        'confirm' => 'Alte Anmeldungen jetzt dauerhaft löschen? Dieser Schritt kann nur mit einem CSV-Backup rückgängig gemacht werden.',
    ));
    echo $ui->form_end();
    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
</section>

<section class="flz-ags-settings-card">
    <h2>Lokaler Mail-Fallback</h2>
    <p>Falls die lokale Zustellung scheitert, wird höchstens eine Stunde lang nur ein technischer Versandnachweis ohne Empfänger, Namen, Klasse, Betreff oder Nachrichtentext gespeichert.</p>
    <?php if ($mock_mail_capture_exists) : ?>
        <?php
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped Formular, Nonce und Komponenten.
        echo $ui->form_start(array(
            'method' => 'post',
            'action' => esc_url(admin_url('admin-post.php')),
            'nonce' => 'flz_ags_clear_mock_mail_capture',
            'hidden' => array('action' => 'flz_ags_clear_mock_mail_capture'),
        )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped Formular und Nonce.
        echo $ui->button_delete(array('label' => 'Lokalen Versandnachweis jetzt löschen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
        echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    <?php else : ?>
        <p>Aktuell ist kein lokaler Versandnachweis gespeichert.</p>
    <?php endif; ?>
</section>
