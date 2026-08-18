<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Turkpin\InterviewTest\Services\TurkpinApiClient;

final class TurkpinApiClientTest extends TestCase
{
    /*
     * Client yanlış bir endpoint ile oluşturulursa hata mümkün olduğunca
     * erken, herhangi bir HTTP isteği yapılmadan yakalanmalıdır.
     */
    public function testConstructorRejectsInvalidApiUrl(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_URL is missing or invalid.'
        );

        new TurkpinApiClient(
            'not-a-valid-url',
            'test-user',
            'test-password'
        );
    }

    public function testConstructorRejectsEmptyUsername(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_USERNAME is missing.'
        );

        new TurkpinApiClient(
            'https://example.com',
            '',
            'test-password'
        );
    }

    public function testConstructorRejectsEmptyPassword(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_PASSWORD is missing.'
        );

        new TurkpinApiClient(
            'https://example.com',
            'test-user',
            ''
        );
    }

    /*
     * Güvenli varsayılan davranış:
     * Order flag açıkça true verilmediği sürece gerçek sipariş yazma
     * işlemleri kapalı kalmalıdır.
     */
    public function testOrderSubmissionIsDisabledByDefault(): void
    {
        $client = $this->createClient();

        self::assertFalse(
            $client->isOrderSubmissionEnabled()
        );
    }

    public function testOrderSubmissionCanBeExplicitlyEnabled(): void
    {
        $client = $this->createClient(true);

        self::assertTrue(
            $client->isOrderSubmissionEnabled()
        );
    }

    /*
     * En kritik güvenlik testlerinden biri:
     * feature flag kapalıyken createOrder(), request()/cURL katmanına
     * ulaşmadan hemen reddedilmelidir. Böylece unit test sırasında da
     * gerçek Turkpin siparişi oluşturulması mümkün değildir.
     */
    public function testCreateOrderIsRejectedWhenSubmissionIsDisabled(): void
    {
        $client = $this->createClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order submission is disabled by configuration.'
        );

        $client->createOrder(
            '1',
            '1',
            1
        );
    }

    /*
     * getProducts() boş oyun kodunu HTTP katmanına göndermemelidir.
     * trim() uygulandığı için yalnız whitespace içeren değer de boş sayılır.
     */
    public function testGetProductsRejectsBlankGameCodeBeforeHttpRequest(): void
    {
        $client = $this->createClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Game code cannot be empty.'
        );

        $client->getProducts('   ');
    }

    public function testConstructorRejectsWhitespaceOnlyUsername(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_USERNAME is missing.'
        );

        new TurkpinApiClient(
            'https://example.com',
            '   ',
            'test-password'
        );
    }

    public function testConstructorRejectsWhitespaceOnlyPassword(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_PASSWORD is missing.'
        );

        new TurkpinApiClient(
            'https://example.com',
            'test-user',
            '   '
        );
    }

    /*
    * Sipariş özelliği açık olsa bile boş oyun kodu
    * HTTP/request katmanına ulaşmadan reddedilmelidir.
    */
    public function testCreateOrderRejectsBlankGameCodeBeforeHttpRequest(): void
    {
        $client = $this->createClient(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Game code cannot be empty.'
        );

        $client->createOrder(
            '   ',
            '1',
            1
        );
    }

    /*
    * Kullanıcıdan veya controller'dan boş ürün kodu gelirse
    * gerçek sipariş isteği oluşturulmamalıdır.
    */
    public function testCreateOrderRejectsBlankProductCodeBeforeHttpRequest(): void
    {
        $client = $this->createClient(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Product code cannot be empty.'
        );

        $client->createOrder(
            '1',
            '   ',
            1
        );
    }

    /*
    * API katmanında da defense-in-depth olarak quantity >= 1
    * koşulu korunur. Asıl business validation OrderValidator'da
    * olsa da geçersiz miktar dış servise gönderilmemelidir.
    */
    public function testCreateOrderRejectsQuantityLessThanOneBeforeHttpRequest(): void
    {
        $client = $this->createClient(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Order quantity must be at least 1.'
        );

        $client->createOrder(
            '1',
            '1',
            0
        );
    }

    /*
    * API credentials request body içinde gönderildiği için
    * Turkpin endpoint'i HTTPS olmak zorunda.
    */
    public function testConstructorRejectsNonHttpsApiUrl(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TURKPIN_API_URL must use HTTPS.'
        );

        new TurkpinApiClient(
            'http://example.com',
            'test-user',
            'test-password'
        );
    }

    private function createClient(
        bool $orderSubmissionEnabled = false
    ): TurkpinApiClient {
        /*
         * example.com yalnızca geçerli URL formatı sağlamak için kullanılır.
         * Bu testlerde hiçbir metot gerçek HTTP isteğine ulaşmaz.
         */
        return new TurkpinApiClient(
            'https://example.com',
            'test-user',
            'test-password',
            $orderSubmissionEnabled
        );
    }
}
