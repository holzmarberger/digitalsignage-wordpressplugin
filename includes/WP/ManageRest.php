<?php
namespace Marberger\Signage\WP;


/**
 * REST-Schnittstelle der Handy-Pflegemaske (mms/v1/manage/…).
 * Grüße: Display-Redaktion (WP-Login) oder gemeinsames Gruß-Passwort (Token im Kopf X-MMS-Token).
 * Hinweise und News: nur Display-Redaktion.
 */
final class ManageRest
{
    private const TYPES = [
        'greetings' => [PostTypes::GREETING, 'greet'],
        'notices' => [PostTypes::NOTICE, 'edit'],
        'news' => [PostTypes::NEWS, 'edit'],
    ];

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        $ns = Rest::NS;
        register_rest_route($ns, '/manage/login', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [self::class, 'login']]);
        register_rest_route($ns, '/manage/bootstrap', ['methods' => 'GET', 'permission_callback' => [self::class, 'canGreet'], 'callback' => [self::class, 'bootstrap']]);
        register_rest_route($ns, '/manage/upload', ['methods' => 'POST', 'permission_callback' => [self::class, 'canGreet'], 'callback' => [self::class, 'upload']]);
        foreach (self::TYPES as $route => [$type, $level]) {
            $perm = $level === 'greet' ? [self::class, 'canGreet'] : [self::class, 'canEdit'];
            register_rest_route($ns, '/manage/' . $route, [
                ['methods' => 'GET', 'permission_callback' => $perm, 'callback' => static fn ($r) => self::listItems($route)],
                ['methods' => 'POST', 'permission_callback' => $perm, 'callback' => static fn ($r) => self::saveItem($route, $r, 0)],
            ]);
            register_rest_route($ns, '/manage/' . $route . '/(?P<id>\d+)', [
                ['methods' => 'POST', 'permission_callback' => $perm, 'callback' => static fn ($r) => self::saveItem($route, $r, (int) $r['id'])],
                ['methods' => 'DELETE', 'permission_callback' => $perm, 'callback' => static fn ($r) => self::deleteItem($route, (int) $r['id'])],
            ]);
        }
    }

    public static function canEdit(): bool
    {
        return current_user_can(PostTypes::EDIT_CAP);
    }

    public static function canGreet(\WP_REST_Request $req): bool
    {
        return self::canEdit() || Access::checkToken((string) $req->get_header('x-mms-token'));
    }

    public static function login(\WP_REST_Request $req)
    {
        $ip = md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $key = 'mms_login_' . $ip;
        $tries = (int) get_transient($key);
        if ($tries >= 10) {
            return new \WP_Error('mms_locked', 'Zu viele Versuche. Bitte in 10 Minuten erneut versuchen.', ['status' => 429]);
        }
        if (!Access::verifyPassword((string) $req->get_param('password'))) {
            set_transient($key, $tries + 1, 10 * MINUTE_IN_SECONDS);
            return new \WP_Error('mms_wrong', Access::password() === '' ? 'Es ist noch kein Gruß-Passwort festgelegt.' : 'Das Passwort ist falsch.', ['status' => 403]);
        }
        delete_transient($key);
        return ['token' => Access::issueToken(), 'days' => Access::TOKEN_DAYS];
    }

    public static function bootstrap(): array
    {
        $sites = [];
        foreach (PostTypes::choices(PostTypes::LOCATION) as $id => $n) {
            $sites[] = ['id' => $id, 'name' => $n];
        }
        $list = static function (array $c): array {
            $o = [];
            foreach ($c as $id => $n) {
                $o[] = ['id' => $id, 'name' => $n];
            }
            return $o;
        };
        return [
            'editor' => self::canEdit(),
            'displays_reception' => $list(PostTypes::displays('reception')),
            'displays_all' => $list(PostTypes::displays()),
            'sites' => $sites,
            'templates' => Templates::all(),
            'notice_icons' => Editorial::NOTICE_ICONS,
            'name_max' => Editorial::NAME_MAX,
        ];
    }

    public static function upload(\WP_REST_Request $req)
    {
        $files = $req->get_file_params();
        if (empty($files['file'])) {
            return new \WP_Error('mms_upload', 'Keine Datei empfangen.', ['status' => 400]);
        }
        $id = Images::fromUpload($files['file']);
        if (is_wp_error($id)) {
            return $id;
        }
        return ['id' => $id, 'url' => (string) wp_get_attachment_image_url($id, 'medium')];
    }

    private static function listItems(string $route): array
    {
        [$type] = self::TYPES[$route];
        $out = [];
        $args = ['post_type' => $type, 'post_status' => ['publish', 'draft'], 'numberposts' => 100, 'orderby' => 'modified', 'order' => 'DESC'];
        foreach (get_posts($args) as $p) {
            $out[] = self::item($route, $p);
        }
        return $out;
    }

    private static function item(string $route, \WP_Post $p): array
    {
        $d = PostTypes::data($p->ID);
        $img = static fn ($id) => $id ? (string) wp_get_attachment_image_url((int) $id, 'medium') : '';
        $base = ['id' => $p->ID, 'valid' => !empty($d['valid']), 'status' => self::status($route, $d)];
        if ($route === 'greetings') {
            return $base + ['name' => $p->post_title, 'logo_url' => $img($d['logo'] ?? 0)] + $d;
        }
        if ($route === 'notices') {
            return $base + ['text_de' => $p->post_title] + $d;
        }
        return $base + ['title' => $p->post_title, 'image_url' => $img($d['image'] ?? 0)] + $d;
    }

    private static function status(string $route, array $d): string
    {
        return Status::of(self::TYPES[$route][0], $d);
    }

    private static function saveItem(string $route, \WP_REST_Request $req, int $id)
    {
        [$type] = self::TYPES[$route];
        if ($id && get_post_type($id) !== $type) {
            return new \WP_Error('mms_not_found', 'Eintrag nicht gefunden.', ['status' => 404]);
        }
        $in = (array) $req->get_json_params();
        if ($route === 'greetings') {
            $res = Editorial::greeting($in);
        } elseif ($route === 'notices') {
            $res = Editorial::notice($in);
        } else {
            $res = Editorial::news($in);
        }
        if ($res['errors']) {
            return new \WP_Error('mms_invalid', implode(' ', array_unique($res['errors'])), ['status' => 400, 'errors' => array_values(array_unique($res['errors']))]);
        }
        $postarr = ['post_type' => $type, 'post_title' => $res['title'], 'post_status' => 'publish'];
        if ($id) {
            $postarr['ID'] = $id;
            $result = wp_update_post($postarr, true);
        } else {
            $result = wp_insert_post($postarr, true);
        }
        if (is_wp_error($result)) {
            return new \WP_Error('mms_save', 'Speichern fehlgeschlagen: ' . $result->get_error_message(), ['status' => 500]);
        }
        PostTypes::save((int) $result, $res['data'] + ['valid' => true]);
        return self::item($route, get_post((int) $result));
    }

    private static function deleteItem(string $route, int $id)
    {
        [$type] = self::TYPES[$route];
        if (get_post_type($id) !== $type) {
            return new \WP_Error('mms_not_found', 'Eintrag nicht gefunden.', ['status' => 404]);
        }
        wp_trash_post($id);
        return ['deleted' => $id];
    }
}
