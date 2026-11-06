<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class UsernameGenerator {

    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'da', 'di'];
    private const MAX_INITIALS = 3;
    
    /**
     * Initials  + person id  + two-digit year the account is created (Chile time)
     * @param string $fullName
     * @param int $personId
     * @param DateTimeImmutable $now
     * @return string
     */
    public function generate(string $fullName, int $personId, DateTimeImmutable $now): string {
        $year = $now->setTimezone(new DateTimeZone('America/Santiago'))->format('y');
        return sprintf('%d-%d-%s', $this->initials($fullName), $personId, $year);
    }

    /**
     * Summary of initials
     * @param string $fullName
     * @return string
     */
    private function initials(string $fullName): string {
        $ascii = strtr(mb_strtolower(trim($fullName), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $words = preg_split('/[^a-z]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(
            $words,
            fn(string $word): bool => !in_array($word, self::PARTICLES, true)
        ));
        $initials = '';
        foreach (array_slice($words, 0, self::MAX_INITIALS) as $word) {
            $initials .= strtoupper($word[0]);
        }
        return $initials !== '' ? $initials : 'X';
    }

}