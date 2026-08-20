<?php

namespace App\Services;

use App\Models\User;

/**
 * TOTP (RFC 6238) implementation without external dependency.
 * HMAC-SHA1, 30-second step, 6 digits, base32 secret.
 */
class MfaService
{
    private const STEP = 30;

    private const DIGITS = 6;

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);

        return $this->base32Encode($bytes);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $label = rawurlencode($user->email);

        return "otpauth://totp/Kaeged:{$label}?secret={$secret}&issuer=Kaeged&digits=".self::DIGITS.'&period='.self::STEP;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if ($secret === '' || $code === '') {
            return false;
        }

        $counter = (int) floor(time() / self::STEP);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret): string
    {
        return $this->hotp($secret, (int) floor(time() / self::STEP));
    }

    private function hotp(string $secret, int $counter): string
    {
        $binary = $this->base32Decode($secret);
        $packed = pack('N*', 0).pack('N', $counter);
        $hash = hash_hmac('sha1', $packed, $binary, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(strtoupper($data)) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
