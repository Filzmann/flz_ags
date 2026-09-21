<?php

defined('ABSPATH') || exit;

add_filter('wp_privacy_personal_data_exporters', 'flz_ags_register_privacy_exporter');
add_filter('wp_privacy_personal_data_erasers', 'flz_ags_register_privacy_eraser');

function flz_ags_register_privacy_exporter(array $exporters): array
{
    $exporters['flz-ags'] = array(
        'exporter_friendly_name' => __('FLZ AG-Anmeldungen', 'flz-ags'),
        'callback' => 'flz_ags_privacy_exporter',
    );

    return $exporters;
}

function flz_ags_register_privacy_eraser(array $erasers): array
{
    $erasers['flz-ags'] = array(
        'eraser_friendly_name' => __('FLZ AG-Anmeldungen', 'flz-ags'),
        'callback' => 'flz_ags_privacy_eraser',
    );

    return $erasers;
}

function flz_ags_privacy_exporter(string $email_address, int $page = 1): array
{
    if ($page > 1 || !is_email($email_address)) {
        return array('data' => array(), 'done' => true);
    }

    $data = array();
    foreach (FLZ_AGS_Registration::find_by_email($email_address) as $registration) {
        if (!$registration instanceof FLZ_AGS_Registration) {
            continue;
        }
        $data[] = array(
            'group_id' => 'flz-ags',
            'group_label' => __('AG-Anmeldungen', 'flz-ags'),
            'item_id' => 'flz-ags-registration-' . (int) $registration->id,
            'data' => array(
                array('name' => __('Schuljahr', 'flz-ags'), 'value' => (string) $registration->school_year),
                array('name' => __('Klasse', 'flz-ags'), 'value' => (string) $registration->class_name),
                array('name' => __('Vorname', 'flz-ags'), 'value' => (string) $registration->student_first_name),
                array('name' => __('Nachname', 'flz-ags'), 'value' => (string) $registration->student_last_name),
                array('name' => __('E-Mail', 'flz-ags'), 'value' => (string) $registration->student_email),
                array('name' => __('Status', 'flz-ags'), 'value' => (string) $registration->status),
                array('name' => __('Anmeldedatum', 'flz-ags'), 'value' => (string) $registration->created_at),
                array('name' => __('Widerruf', 'flz-ags'), 'value' => (string) $registration->withdrawn_at),
            ),
        );
    }

    return array('data' => $data, 'done' => true);
}

function flz_ags_privacy_eraser(string $email_address, int $page = 1): array
{
    $result = array(
        'items_removed' => false,
        'items_retained' => false,
        'messages' => array(),
        'done' => true,
    );
    if ($page > 1 || !is_email($email_address)) {
        return $result;
    }

    try {
        $removed = FLZ_AGS_Model::transaction(
            static function () use ($email_address): int {
                $count = 0;
                foreach (FLZ_AGS_Registration::find_by_email($email_address) as $registration) {
                    if ($registration instanceof FLZ_AGS_Registration) {
                        $count += $registration->delete();
                    }
                }
                return $count;
            },
            'Löschen von AG-Anmeldungen über den WordPress-Privacy-Eraser'
        );
        $result['items_removed'] = $removed > 0;
    } catch (Throwable $error) {
        flz_ags_log_error($error, 'Löschen von AG-Anmeldungen über den WordPress-Privacy-Eraser');
        $result['items_retained'] = true;
        $result['messages'][] = __('Eine AG-Anmeldung konnte nicht sicher gelöscht werden.', 'flz-ags');
    }

    return $result;
}

