<?php
namespace Marberger\Signage\WP;

/**
 * Gemeinsames Gruß-Passwort: verschlüsselt gespeichert (abrufbar für Administratoren und Display-Redaktion)
 * und signierte Sitzungs-Token für die Handy-Pflegemaske (30 Tage, ungültig nach Passwortänderung).
 */
final class Access
{
    public const OPT_PW = 'mms_greet_pw';
    public const OPT_VER = 'mms_greet_ver';
    public const TOKEN_DAYS = 30;

    private static function key(): string
    {
        return hash('sha256', wp_salt('auth') . '|mms-greeting', true);
    }

    public static function setPassword(string $plain): void
    {
        $plain = trim($plain);
        update_option(self::OPT_PW, $plain === '' ? '' : self::encrypt($plain), false);
        update_option(self::OPT_VER, (int) get_option(self::OPT_VER, 0) + 1, false);
    }

    public static function password(): string
    {
        $stored = (string) get_option(self::OPT_PW, '');
        return $stored === '' ? '' : self::decrypt($stored);
    }

    public static function verifyPassword(string $input): bool
    {
        $pw = self::password();
        return $pw !== '' && hash_equals($pw, trim($input));
    }

    public static function issueToken(): string
    {
        $payload = self::b64(wp_json_encode(['exp' => time() + self::TOKEN_DAYS * DAY_IN_SECONDS, 'v' => (int) get_option(self::OPT_VER, 0)]));
        return $payload . '.' . self::b64(hash_hmac('sha256', $payload, self::key(), true));
    }

    public static function checkToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        $expected = self::b64(hash_hmac('sha256', $parts[0], self::key(), true));
        if (!hash_equals($expected, $parts[1])) {
            return false;
        }
        $data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
        return is_array($data) && ($data['exp'] ?? 0) > time() && (int) ($data['v'] ?? -1) === (int) get_option(self::OPT_VER, 0) && self::password() !== '';
    }

    private static function encrypt(string $plain): string
    {
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(16);
            $c = openssl_encrypt($plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
            return 'o:' . base64_encode($iv . $c);
        }
        if (function_exists('sodium_crypto_secretbox')) {
            $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, self::key()));
        }
        return 'p:' . base64_encode($plain);
    }

    private static function decrypt(string $stored): string
    {
        $raw = base64_decode(substr($stored, 2));
        switch (substr($stored, 0, 2)) {
            case 'o:':
                return function_exists('openssl_decrypt') ? (string) openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 16)) : '';
            case 's:':
                $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
                $r = function_exists('sodium_crypto_secretbox_open') ? sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), self::key()) : false;
                return $r === false ? '' : $r;
            case 'p:':
                return (string) $raw;
        }
        return '';
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
