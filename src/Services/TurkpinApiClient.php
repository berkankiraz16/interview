<?php
#hassas işlemlerde veri kaybına yol açmaması için katı modda kullanıldı - tür dönüşümünün önüne geçmek için strict types tanımladık
declare(strict_types=1);

namespace Turkpin\InterviewTest\Services;

use RuntimeException;

#sınıfımızı miras almaması ve override yapılmaması için final class olarak tanımladık
final class TurkpinApiClient
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 15;
# name-pass ve url,api gibi bilgilerin sabit kalması için constructor içinde tanımladık
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
        $this->validateConfiguration();
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::env('TURKPIN_API_URL'),
            self::env('TURKPIN_API_USERNAME'),
            self::env('TURKPIN_API_PASSWORD'),
        );
    }
#request fonksiyonu ile turkpin api ile gateway oluşturup ilerideki tüm işleri tek merkezden ele alabileceğiz
    public function request(string $command, array $parameters = []): string
    {
        if (trim($command) === '') {
            throw new RuntimeException('Turkpin API command cannot be empty.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }

        $handle = curl_init($this->baseUrl);

        if ($handle === false) {
            throw new RuntimeException('cURL could not be initialized.');
        }

        $payload = array_merge(
            $parameters,
            [
                'username' => $this->username,
                'password' => $this->password,
                'cmd' => $command,
            ]
        );
#tuttuğumuz verileri turkpin api nin kolay okuması için standart bir pakete dönüştürüyoruz linkleri
        curl_setopt_array($handle, [
            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => http_build_query(
                $payload,
                '',
                '&',
                PHP_QUERY_RFC3986
            ),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,

            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
#bağlanacağımız sunucunun kimliğini doğrulamak için ssl doğrulamasını etkinleştiriyoruz
            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/xml, text/xml, application/json',
            ],
        ]);

        $response = curl_exec($handle);

        $curlError = curl_error($handle);

        $httpStatus = curl_getinfo(
            $handle,
            CURLINFO_HTTP_CODE
        );

        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException(
                'Turkpin API connection error: ' . $curlError
            );
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException(
                "Turkpin API returned HTTP status {$httpStatus}."
            );
        }

        if (trim($response) === '') {
            throw new RuntimeException(
                'Turkpin API returned an empty response.'
            );
        }

        return $response;
    }
#ilgili hatanın nereden kaynaklı oldıuğunu belirlemek için uyarıcı mesajları veriyoruz
    private function validateConfiguration(): void
    {
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(
                'TURKPIN_API_URL is missing or invalid.'
            );
        }

        if ($this->username === '') {
            throw new RuntimeException(
                'TURKPIN_API_USERNAME is missing.'
            );
        }

        if ($this->password === '') {
            throw new RuntimeException(
                'TURKPIN_API_PASSWORD is missing.'
            );
        }
    }

    private static function env(string $key): string
    {
        $value = $_ENV[$key]
            ?? $_SERVER[$key]
            ?? getenv($key);

        return is_string($value)
            ? trim($value)
            : '';
    }
}