<?php
declare(strict_types=1);

namespace App\Security;

class PasswordHasher {
    
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    public function verify(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }
    public function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}