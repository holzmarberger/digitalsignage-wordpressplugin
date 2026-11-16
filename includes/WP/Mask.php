<?php
namespace Marberger\Signage\WP;

/**
 * Handy-Pflegemaske unter ?mm_pflege=1 (theme-frei, nur im Intranet erreichbar).
 * Anmeldung mit gemeinsamem Gruß-Passwort (nur Grüße) oder WordPress-Konto mit Display-Redaktion (Grüße, Hinweise, News).
 */
final class Mask
{
    public const QUERY_VAR = 'mm_pflege';

    public static function init(): void
    {
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = self::QUERY_VAR;
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybeRender'], 0);
    }

    public static function url(): string
    {
        return add_query_arg(self::QUERY_VAR, '1', home_url('/'));
    }

    public static function maybeRender(): void
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }
        nocache_headers();
        $editor = current_user_can(PostTypes::EDIT_CAP);
        $config = [
            'rest' => rest_url(Rest::NS . '/manage/'),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'editor' => $editor,
            'user' => $editor ? wp_get_current_user()->display_name : '',
            'loginUrl' => wp_login_url(self::url()),
            'logoutUrl' => is_user_logged_in() ? wp_logout_url(self::url()) : '',
            'logo' => MMS_URL . 'assets/img/marberger-logo.png',
        ];
        header('Content-Type: text/html; charset=utf-8');
        $v = MMS_VERSION;
        ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#006729">
<title>Displays pflegen – Holz Marberger</title>
<link rel="stylesheet" href="<?php echo esc_url(MMS_URL . 'assets/manage/manage.css?v=' . $v); ?>">
</head>
<body>
<div id="app"><p class="pad">Lade …</p></div>
<script type="application/json" id="mms-config"><?php echo wp_json_encode($config); ?></script>
<script src="<?php echo esc_url(MMS_URL . 'assets/manage/manage.js?v=' . $v); ?>"></script>
</body>
</html><?php
        exit;
    }
}
