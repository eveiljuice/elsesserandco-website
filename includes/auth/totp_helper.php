<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Config.php';

function totpAvailable(): bool
{
    return is_file(__DIR__ . '/../../vendor/autoload.php');
}

function totpInstance(): ?RobThree\Auth\TwoFactorAuth
{
    if (!totpAvailable()) {
        return null;
    }
    require_once __DIR__ . '/../../vendor/autoload.php';
    return new RobThree\Auth\TwoFactorAuth('Elsesser & Co.');
}

function totpGenerateSecret(): string
{
    $tfa = totpInstance();
    if ($tfa) {
        return $tfa->createSecret();
    }
    // Fallback без Composer (base32 16 bytes)
    return rtrim(strtr(base64_encode(random_bytes(20)), '+/', 'AB'), '=');
}

function totpVerify(string $secret, string $code): bool
{
    $tfa = totpInstance();
    if ($tfa) {
        return $tfa->verifyCode($secret, $code);
    }
    return false;
}

function totpQrDataUri(string $secret, string $email): string
{
    $tfa = totpInstance();
    if (!$tfa) {
        return '';
    }
    $label = rawurlencode($email);
    return $tfa->getQRCodeImageAsDataUri($label, $secret);
}

function userRequiresTotp(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'agent'], true)
        && !empty($user['totp_enabled_at'])
        && !empty($user['totp_secret']);
}
