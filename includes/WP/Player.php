<?php
namespace Marberger\Signage\WP;

/**
 * Theme-freie Anzeigeseite unter ?mm_signage=<kennung>.
 * Paket 1: Diagnoseansicht des Zustands. Paket 2 ersetzt sie durch den echten Player.
 */
final class Player
{
    public const QUERY_VAR = 'mm_signage';

    public static function init(): void
    {
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = self::QUERY_VAR;
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybeRender'], 0);
    }

    public static function url(string $key): string
    {
        return add_query_arg(self::QUERY_VAR, $key, home_url('/'));
    }

    public static function maybeRender(): void
    {
        $key = get_query_var(self::QUERY_VAR);
        if ($key === '' || $key === null) {
            return;
        }
        $key = sanitize_key((string) $key);
        nocache_headers();
        $post = Store::displayByKey($key);
        if (!$post) {
            status_header(404);
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Unbekanntes Display</title><p style="font:24px sans-serif;padding:40px">Unbekanntes Display: ' . esc_html($key) . '</p>';
            exit;
        }
        $config = [
            'stateUrl' => Rest::stateUrl($key),
            'key' => $key,
        ];
        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($post->post_title); ?> – Marberger Signage</title>
<link rel="stylesheet" href="<?php echo esc_url(MMS_URL . 'assets/player/diag.css?v=' . MMS_VERSION); ?>">
</head>
<body>
<main id="mms-diag" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
  <p class="muted">Lade Zustand …</p>
</main>
<script src="<?php echo esc_url(MMS_URL . 'assets/player/diag.js?v=' . MMS_VERSION); ?>"></script>
</body>
</html><?php
        exit;
    }
}
