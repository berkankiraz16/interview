<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Security;

final class OrderSubmissionTokenManager
{
    private const SESSION_KEY = 'order_submission_tokens';

    // Kullanılmayan token'ları session içinde sonsuza kadar tutmuyoruz.
    private const TOKEN_TTL_SECONDS = 3600;

    // Çok fazla sayfa yenilenmesi durumunda session'ın gereksiz büyümesini önlüyoruz.
    private const MAX_ACTIVE_TOKENS = 20;

    public function issue(): string
    {
        $this->purgeExpiredTokens();

        /*
         * 32 random byte -> 64 karakterlik güvenli hex token.
         * Token tahmin edilemez olmalıdır.
         */
        $token = bin2hex(
            random_bytes(32)
        );

        $tokens = $this->readTokens();

        $tokens[$token] = time();

        $_SESSION[self::SESSION_KEY] = $tokens;

        $this->limitActiveTokens();

        return $token;
    }

    public function consume(string $token): bool
    {
        $token = trim($token);

        /*
         * Ürettiğimiz token her zaman 64 karakterlik hexadecimal değerdir.
         * Beklenmeyen inputları session lookup'a sokmuyoruz.
         */
        if (
            preg_match('/^[a-f0-9]{64}$/', $token) !== 1
        ) {
            return false;
        }

        $this->purgeExpiredTokens();

        $tokens = $this->readTokens();

        if (!isset($tokens[$token])) {
            return false;
        }

        /*
         * Token başarılı biçimde kullanıldığı anda siliyoruz.
         * Aynı form ikinci kez gönderilirse artık geçersiz olacaktır.
         */
        unset($tokens[$token]);

        $_SESSION[self::SESSION_KEY] = $tokens;

        return true;
    }

    private function purgeExpiredTokens(): void
    {
        $tokens = $this->readTokens();

        $cutoff = time() - self::TOKEN_TTL_SECONDS;

        foreach ($tokens as $token => $createdAt) {
            if ($createdAt < $cutoff) {
                unset($tokens[$token]);
            }
        }

        $_SESSION[self::SESSION_KEY] = $tokens;
    }

    private function limitActiveTokens(): void
    {
        $tokens = $this->readTokens();

        if (
            count($tokens) <= self::MAX_ACTIVE_TOKENS) {
            return;
        }

        /*
         * En eski token'ları önce sıralayıp limitin üzerindekileri siliyoruz.
         */
        asort(
            $tokens,
            SORT_NUMERIC
        );

        while (
            count($tokens) > self::MAX_ACTIVE_TOKENS
        ) {
            $oldestToken = array_key_first(
                $tokens
            );

            unset($tokens[$oldestToken]);
        }

        $_SESSION[self::SESSION_KEY] = $tokens;
    }
    /**
     * Session içindeki ham veriyi güvenli ve tiplenmiş
     * token store yapısına dönüştürür.
     *
     * @return array<string, int>
     */
    private function readTokens(): array
    {
        $storedTokens =
            $_SESSION[self::SESSION_KEY] ?? [];

        if (!is_array($storedTokens)) {
            return [];
        }

        $tokens = [];

        foreach ($storedTokens as $token => $createdAt) {
            if (
                !is_string($token)
                || !is_int($createdAt)
            ) {
                continue;
            }

            $tokens[$token] = $createdAt;
        }

        return $tokens;
    }
}
