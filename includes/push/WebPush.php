<?php
/**
 * Native Web Push sender — без сторонних библиотек.
 *
 * Поддерживает aes128gcm-шифрование (RFC 8291) и JWT VAPID.
 * Использует ECDSA P-256. Требует расширение OpenSSL (включено по умолчанию).
 *
 * Использование:
 *   WebPush::send(['endpoint' => '...', 'keys' => ['p256dh' => '...', 'auth' => '...']],
 *                 ['title' => '...', 'body' => '...', 'url' => '/property.php?id=42']);
 */

require_once __DIR__ . '/../config/Config.php';

final class WebPush
{
    public static function send(array $subscription, array $payload): array
    {
        $publicKey  = (string)Config::get('VAPID_PUBLIC_KEY');
        $privateKey = (string)Config::get('VAPID_PRIVATE_KEY');
        $subject    = (string)Config::get('VAPID_SUBJECT', 'mailto:admin@elsesserandco.com');

        if (!$publicKey || !$privateKey) {
            return ['ok' => false, 'status' => 0, 'error' => 'VAPID keys not configured'];
        }

        $endpoint = $subscription['endpoint'] ?? '';
        $p256dh   = $subscription['keys']['p256dh'] ?? ($subscription['p256dh_key'] ?? '');
        $auth     = $subscription['keys']['auth']   ?? ($subscription['auth_key']   ?? '');

        if (!$endpoint || !$p256dh || !$auth) {
            return ['ok' => false, 'status' => 0, 'error' => 'Invalid subscription'];
        }

        $parsedEndpoint = parse_url($endpoint);
        $audience = $parsedEndpoint['scheme'] . '://' . $parsedEndpoint['host'];
        $jwt = self::buildVapidJwt($audience, $subject, $privateKey);

        $body = self::encryptPayload(json_encode($payload, JSON_UNESCAPED_UNICODE), $p256dh, $auth);

        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: 86400',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'response' => $resp];
    }

    /** Build a signed VAPID JWT (ES256). */
    private static function buildVapidJwt(string $audience, string $subject, string $privateKey): string
    {
        $header  = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::b64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => $subject,
        ]));
        $signingInput = $header . '.' . $payload;

        $privDer = self::b64urlDecode($privateKey);
        // Раскрываем raw 32-байтный private key в PEM с помощью openssl
        $pem = self::rawToPem($privDer);
        $pkey = openssl_pkey_get_private($pem);
        if (!$pkey) {
            throw new RuntimeException('VAPID: invalid private key');
        }
        openssl_sign($signingInput, $derSig, $pkey, OPENSSL_ALGO_SHA256);
        $rawSig = self::derToRawSignature($derSig);

        return $signingInput . '.' . self::b64url($rawSig);
    }

    /** AES128GCM encryption per RFC 8291. */
    private static function encryptPayload(string $payload, string $userPublicKeyB64, string $userAuthB64): string
    {
        $userPublicKey = self::b64urlDecode($userPublicKeyB64);
        $userAuth      = self::b64urlDecode($userAuthB64);

        // Сгенерировать пару ключей сервера (ad-hoc)
        $serverKeyConfig = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $serverKey = openssl_pkey_new($serverKeyConfig);
        openssl_pkey_export($serverKey, $serverPrivPem);
        $serverDetails = openssl_pkey_get_details($serverKey);
        $serverPublicKey = "\x04" . $serverDetails['ec']['x'] . $serverDetails['ec']['y'];

        // ECDH-shared secret
        $userPubKeyOpenSsl = self::rawPublicKeyToPem($userPublicKey);
        $sharedSecret = '';
        if (function_exists('openssl_pkey_derive')) {
            $sharedSecret = openssl_pkey_derive($userPubKeyOpenSsl, $serverKey, 32);
        }
        if (!$sharedSecret) {
            throw new RuntimeException('VAPID: ECDH not supported. Используйте PHP 7.3+ с OpenSSL.');
        }

        // HKDF (см. RFC 8291)
        $salt = random_bytes(16);
        $prk  = hash_hmac('sha256', $sharedSecret, $userAuth, true);
        $keyInfo = "WebPush: info\x00" . $userPublicKey . $serverPublicKey;
        $ikm  = self::hkdfExpand($prk, $keyInfo . "\x01", 32);

        $cek   = self::hkdfExpand(hash_hmac('sha256', $ikm, $salt, true), "Content-Encoding: aes128gcm\x00\x01", 16);
        $nonce = self::hkdfExpand(hash_hmac('sha256', $ikm, $salt, true), "Content-Encoding: nonce\x00\x01", 12);

        $padded = $payload . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        // Header: salt(16) | rs(4) | idlen(1) | keyid(idlen)
        $header  = $salt . pack('N', 4096) . chr(strlen($serverPublicKey)) . $serverPublicKey;
        return $header . $ciphertext . $tag;
    }

    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        return substr(hash_hmac('sha256', $info, $prk, true), 0, $length);
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad  = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode($data);
    }

    private static function rawToPem(string $rawPrivate): string
    {
        // ASN.1 oid header for P-256
        $oid = hex2bin('3041020100301306072a8648ce3d020106082a8648ce3d030107042730250201010420');
        $der = $oid . $rawPrivate;
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PRIVATE KEY-----\n";
    }

    private static function rawPublicKeyToPem(string $rawPublic): string
    {
        // SubjectPublicKeyInfo ASN.1 prefix for prime256v1
        $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420000');
        $der = $prefix . substr($rawPublic, 1); // strip leading 0x04
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $rawPublic;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derToRawSignature(string $der): string
    {
        // 0x30 len 0x02 rlen R 0x02 slen S
        $offset = 4;
        $rLen = ord($der[3]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen + 2;
        $sLen = ord($der[$offset - 1]);
        $s = substr($der, $offset, $sLen);
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }
}
