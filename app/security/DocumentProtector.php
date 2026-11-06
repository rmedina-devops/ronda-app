<?php
declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;
use RuntimeException;

class DocumentProtector {
    
    private string $encryptionkey;
    private string $hashkey;

    public function __construct(string $encryptionkeyB64, string $hashkeyB64) {
        $encryption = base64_decode($encryptionkeyB64, true);
        $hash = base64_decode($hashkeyB64, true);

        if ($encryption === false || strlen($encryption) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('APP_KEY must be 32 random bytes encoded in base64 ');
        }
        if ($hash === false || strlen($hash) < 32) {
            throw new InvalidArgumentException('HASHKEY_ must be at least 32 randon bytes encoded in base64');
        }
        $this->encryptionkey = $encryption;
        $this->hashkey = $hash;
    }

    /**
     * Removes dots, hyphens and spaces: "12.345.567-8" becomes "123456789"
     * @param string $number
     * @throws InvalidArgumentException
     * @return string
     */
    public function normalize(string $number): string {
        $clean = strtoupper(preg_replace('/[\s.\-]/', '', $number) ?? '');
        if ($clean === '') {
            throw new InvalidArgumentException('Document number is empty');
        }
        return $clean;
    }

    /**
     * Keyde hash used to detect duplicates without exposing the document
     * @param string $type
     * @param string $number
     * @return string
     */
    public function hash(string $type, string $number): string {
        return hash_hmac('sha256', $type. ':' . $this->normalize($number), $this->hashkey);
    }

    /**
     * This function is decrypt the hash
     * @param string $playload
     * @throws RuntimeException
     * @return bool|string
     */
    public function decrypt(string $playload): string {
        $raw = base64_decode($playload, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted playload');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->encryptionkey
        );
        if($plain === false) {
            throw new RuntimeException('Cloud not decrypt: wrong key or tampered data');
        }
        return $plain;
    }

    /**
     * Shows only the last two characters
     * @param string $number
     * @return string
     */
    public function mask(string $number): string {
        $clean = $this->normalize($number);
        return str_repeat('*', max(strlen($clean) - 2, 0)) . substr($clean, -2);
    }
}