<?php
namespace Marberger\Signage\WP;

/**
 * Berechtigung „Display-Redaktion“: Häkchen im Benutzerprofil, vergibt das Recht 'mms_edit'
 * (plus Medien-Upload) zusätzlich zur bestehenden Rolle. Administratoren haben es immer.
 */
final class Caps
{
    public const USER_META = 'mms_redaktion';

    public static function init(): void
    {
        add_filter('user_has_cap', [self::class, 'grant'], 10, 4);
        add_action('show_user_profile', [self::class, 'profileField']);
        add_action('edit_user_profile', [self::class, 'profileField']);
        add_action('personal_options_update', [self::class, 'saveProfile']);
        add_action('edit_user_profile_update', [self::class, 'saveProfile']);
    }

    public static function grant(array $allcaps, array $caps, array $args, \WP_User $user): array
    {
        if (!empty($allcaps['manage_options']) || get_user_meta($user->ID, self::USER_META, true) === '1') {
            $allcaps[PostTypes::EDIT_CAP] = true;
            $allcaps['upload_files'] = true;
            $allcaps['read'] = true;
        }
        return $allcaps;
    }

    public static function isEditor(int $userId): bool
    {
        return get_user_meta($userId, self::USER_META, true) === '1';
    }

    public static function profileField(\WP_User $user): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<h2>Displays</h2><table class="form-table"><tr><th>Display-Redaktion</th><td><label>'
            . '<input type="checkbox" name="mms_redaktion" value="1"' . checked(self::isEditor($user->ID), true, false) . '> '
            . 'darf Grüße, Hinweise, News, Wegweiser, Vollbild- und Display-Seiten pflegen und das Gruß-Passwort einsehen</label>'
            . wp_nonce_field('mms_profile_' . $user->ID, '_mms_profile_nonce', true, false)
            . '</td></tr></table>';
    }

    public static function saveProfile(int $userId): void
    {
        if (!current_user_can('manage_options') || !isset($_POST['_mms_profile_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mms_profile_nonce'])), 'mms_profile_' . $userId)) {
            return;
        }
        if (!empty($_POST['mms_redaktion'])) {
            update_user_meta($userId, self::USER_META, '1');
        } else {
            delete_user_meta($userId, self::USER_META);
        }
    }
}
