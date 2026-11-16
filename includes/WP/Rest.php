<?php
namespace Marberger\Signage\WP;

use Marberger\Signage\Core\StateResolver;

/**
 * GET mms/v1/state/<kennung> – aufgelöster Zustand eines Displays.
 * Im Intranet über ?rest_route=/mms/v1/state/<kennung> erreichbar (immer über rest_url() adressieren).
 */
final class Rest
{
    public const NS = 'mms/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NS, '/state/(?P<key>[a-z0-9_-]+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'state'],
            'args' => [
                'at' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public static function stateUrl(string $key): string
    {
        return rest_url(self::NS . '/state/' . $key);
    }

    public static function state(\WP_REST_Request $req)
    {
        nocache_headers();
        $key = sanitize_key((string) $req['key']);
        $ctx = Store::displayContext($key);
        if (!$ctx) {
            return new \WP_Error('mms_unknown_display', 'Unbekanntes Display.', ['status' => 404]);
        }

        $tz = Store::timezone();
        $now = new \DateTimeImmutable('now', $tz);
        $simulated = false;
        $at = (string) $req->get_param('at');
        if ($at !== '' && current_user_can(PostTypes::ADMIN_CAP)) {
            try {
                $now = new \DateTimeImmutable($at, $tz);
                $simulated = true;
            } catch (\Exception $e) {
                return new \WP_Error('mms_bad_time', 'Ungültiger Zeitpunkt.', ['status' => 400]);
            }
        }
        if (!$simulated) {
            Store::touch($ctx['post']->ID);
        }

        $resolver = new StateResolver($ctx['calendar'], $ctx['settings']);
        $state = $resolver->resolve($now, Store::content($ctx));

        $resp = rest_ensure_response(self::serialize([
            'version' => MMS_VERSION,
            'server_time' => $now,
            'simulated' => $simulated,
            'display' => ['key' => $ctx['key'], 'name' => $ctx['name'], 'format' => $ctx['format'], 'type' => $ctx['settings']['type']],
            'settings' => $ctx['settings'],
            'location' => $ctx['location'],
            'opening' => ['week' => $ctx['location'] ? $ctx['calendar']->weekTable() : []],
            'state' => $state,
        ]));
        $resp->header('Cache-Control', 'no-store, max-age=0');
        return $resp;
    }

    /** DateTimes rekursiv in ISO-8601-Zeichenketten umwandeln. */
    public static function serialize($v)
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('c');
        }
        if (is_array($v)) {
            foreach ($v as $k => $item) {
                $v[$k] = self::serialize($item);
            }
        }
        return $v;
    }
}
