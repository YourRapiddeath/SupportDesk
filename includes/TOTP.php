<?php
/**
 * Simple TOTP (Time-based One-Time Password) implementation
 * Based on RFC 6238
 */
class TOTP {
    public static function generateCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        if ($secretKey === false || empty($secretKey)) {
            error_log("TOTP: Invalid secret key for decoding: " . $secret);
            return '000000';
        }

        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;

        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify TOTP code
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);

        // Check current time and adjacent time slices
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::generateCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate random secret
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Base32 decode
     */
    private static function base32Decode($secret) {
        if (empty($secret)) {
            return false;
        }

        $secret = strtoupper($secret);
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32Lookup = [];
        for ($i = 0; $i < 32; $i++) {
            $base32Lookup[$base32Chars[$i]] = $i;
        }

        // Remove padding
        $secret = rtrim($secret, '=');

        $binaryString = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];

            if (!isset($base32Lookup[$char])) {
                // Invalid character
                return false;
            }

            $value = $base32Lookup[$char];
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binaryString .= chr(($buffer >> $bitsLeft) & 0xFF);
                $buffer &= (1 << $bitsLeft) - 1;
            }
        }

        return $binaryString;
    }
}
