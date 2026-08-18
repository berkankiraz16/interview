<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Security\OrderSubmissionTokenManager;

final class OrderSubmissionTokenManagerTest extends TestCase
{
    private const SESSION_KEY = 'order_submission_tokens';

    private OrderSubmissionTokenManager $tokenManager;

    protected function setUp(): void
    {
        /*
         * TokenManager doğrudan $_SESSION kullandığı için her test
         * temiz ve birbirinden bağımsız bir session state ile başlar.
         */
        $_SESSION = [];

        $this->tokenManager = new OrderSubmissionTokenManager();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /*
     * random_bytes(32) + bin2hex() sonucunun 64 karakterlik,
     * lowercase hexadecimal bir token formatı ürettiğini doğruluyoruz.
     */
    public function testIssueReturnsValidTokenFormat(): void
    {
        $token = $this->tokenManager->issue();

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );
    }

    public function testIssuedTokenCanBeConsumed(): void
    {
        $token = $this->tokenManager->issue();

        self::assertTrue(
            $this->tokenManager->consume($token)
        );
    }

    /*
     * Token ilk başarılı kullanımda session'dan silinir.
     * Bu davranış aynı formun çift tıklama, refresh veya replay ile
     * ikinci kez işleme alınmasını engelleyen ana backend korumasıdır.
     */
    public function testConsumedTokenCannotBeReused(): void
    {
        $token = $this->tokenManager->issue();

        self::assertTrue(
            $this->tokenManager->consume($token)
        );

        self::assertFalse(
            $this->tokenManager->consume($token)
        );
    }

    public function testConsumeAcceptsSurroundingWhitespace(): void
    {
        $token = $this->tokenManager->issue();

        self::assertTrue(
            $this->tokenManager->consume(
                "  {$token}  "
            )
        );
    }

    /*
     * Session lookup yapmadan önce token formatı kontrol edilir.
     * Beklenmeyen veya manipüle edilmiş input doğrudan reddedilmelidir.
     */
    public function testConsumeRejectsInvalidTokenFormat(): void
    {
        self::assertFalse(
            $this->tokenManager->consume('not-a-valid-token')
        );

        self::assertFalse(
            $this->tokenManager->consume(
                str_repeat('g', 64)
            )
        );

        self::assertFalse(
            $this->tokenManager->consume(
                str_repeat('a', 63)
            )
        );
    }

    /*
     * Production sınıfı zamanı doğrudan time() ile aldığı için expiry
     * testinde session'a kontrollü eski bir timestamp yerleştiriyoruz.
     * 3601 saniye eski değer, 1 saatlik TTL'in kesin olarak dışındadır.
     */
    public function testExpiredTokenCannotBeConsumed(): void
    {
        $token = str_repeat('a', 64);

        $_SESSION[self::SESSION_KEY] = [
            $token => time() - 3601,
        ];

        self::assertFalse(
            $this->tokenManager->consume($token)
        );

        $storedTokens =
            $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($storedTokens)) {
            self::fail(
                'Expected token storage to be an array.'
            );
        }

        self::assertArrayNotHasKey(
            $token,
            $storedTokens
        );
    }

    /*
     * Session verisi beklenmedik şekilde bozulmuşsa manager fail-safe
     * davranıp geçersiz timestamp kayıtlarını temizlemelidir.
     */
    public function testInvalidTimestampIsPurged(): void
    {
        $invalidToken = str_repeat('b', 64);
        $validToken = str_repeat('c', 64);

        $_SESSION[self::SESSION_KEY] = [
            $invalidToken => 'invalid-timestamp',
            $validToken => time(),
        ];

        self::assertFalse(
            $this->tokenManager->consume($invalidToken)
        );

        self::assertTrue(
            $this->tokenManager->consume($validToken)
        );
    }

    /*
     * MAX_ACTIVE_TOKENS=20 sınırını private metoda erişmeden,
     * yalnızca public API davranışı üzerinden test ediyoruz.
     * 21 token üretildiğinde en fazla 20 tanesi aktif kalmalıdır.
     */
    public function testAtMostTwentyIssuedTokensRemainConsumable(): void
    {
        $tokens = [];

        for ($i = 0; $i < 21; $i++) {
            $tokens[] = $this->tokenManager->issue();
        }

        $consumedCount = 0;

        foreach ($tokens as $token) {
            if ($this->tokenManager->consume($token)) {
                $consumedCount++;
            }
        }

        self::assertSame(
            20,
            $consumedCount
        );
    }

    /*
     * Session key'i yanlışlıkla array dışında bir değere dönüşürse
     * component bunu temiz bir token store'a dönüştürebilmelidir.
     */
    public function testIssueRecoversFromCorruptedSessionStorage(): void
    {
        $_SESSION[self::SESSION_KEY] = 'corrupted';

        $token = $this->tokenManager->issue();

        self::assertIsArray(
            $_SESSION[self::SESSION_KEY]
        );

        self::assertTrue(
            $this->tokenManager->consume($token)
        );
    }

    public function testTwoDifferentTokensCanBeConsumedIndependently(): void
    {
        $firstToken = $this->tokenManager->issue();
        $secondToken = $this->tokenManager->issue();

        self::assertNotSame(
            $firstToken,
            $secondToken
        );

        self::assertTrue(
            $this->tokenManager->consume($firstToken)
        );

        self::assertTrue(
            $this->tokenManager->consume($secondToken)
        );
    }
}
