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

    public function getGames(): array
    {
        $response = $this->request(
            'epinOyunListesi'
        );
    
        return $this->parseGameList($response);
    }

#request fonksiyonu ile turkpin api ile gateway oluşturup ilerideki tüm işleri tek merkezden ele alabileceğiz
    public function request(string $command, array $parameters = []): string
    {
        if (trim($command) === '') {
            throw new RuntimeException(
                'Turkpin API command cannot be empty.'
            );
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHP cURL extension is not enabled.'
            );
        }

        $xml = $this->buildRequestXml(
            $command,
            $parameters
        );

        $handle = curl_init($this->baseUrl);

        if ($handle === false) {
            throw new RuntimeException(
                'cURL could not be initialized.'
            );
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => [
                'DATA' => $xml,
            ],

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CONNECTTIMEOUT =>
                self::CONNECT_TIMEOUT_SECONDS,

            CURLOPT_TIMEOUT =>
                self::REQUEST_TIMEOUT_SECONDS,

            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
#dönecek yanıtın xml formatında olması için header ekliyoruz
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml, text/xml',
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
                'Turkpin API connection error: '
                . $curlError
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
#apinin beklediği etiket yapısını eksiksiz ve güvenili bir şekilde standart hale getiriyoruz
    private function buildRequestXml(
    string $command,
    array $parameters = []
): string {
    $escape = static fn (string $value): string =>
    #htmlspecialchars fonksiyonu ile verileri html etiketlerinden korumak için kullanıyoruz
        htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );

    $xml = '<APIRequest><params>';

    $xml .= '<cmd>'
        . $escape($command)
        . '</cmd>';

    $xml .= '<username>'
        . $escape($this->username)
        . '</username>';

    $xml .= '<password>'
        . $escape($this->password)
        . '</password>';

    foreach ($parameters as $name => $value) {
        if (
            !is_string($name)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name)
        ) {
            throw new RuntimeException(
                'Invalid Turkpin API parameter name.'
            );
        }

        if (!is_scalar($value)) {
            throw new RuntimeException(
                "Turkpin API parameter '{$name}' must be scalar."
            );
        }

        $xml .= '<' . $name . '>'
            . $escape((string) $value)
            . '</' . $name . '>';
    }

    $xml .= '</params></APIRequest>';

    return $xml;
}

    private function parseGameList(string $response): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException(
                'PHP SimpleXML extension is not enabled.'
            );
        }

        $previousUseInternalErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($response);

            if ($xml === false) {
                throw new RuntimeException(
                    'Turkpin API returned invalid XML.'
                );
            }

            $errorCode = trim(
                (string) ($xml->params->error ?? '')
            );

            $errorDescription = trim(
                (string) ($xml->params->error_desc ?? '')
            );
#yalnızca bağlantının kurulmasını değil, istenilen işlemin başarılı bir şekilde tamamlandığını doğrulamak için kontrol ediyoruz
            if ($errorCode !== '000') {
                throw new RuntimeException(
                    'Turkpin API error'
                    . ($errorCode !== ''
                        ? " ({$errorCode})"
                        : '')
                    . ': '
                    . ($errorDescription !== ''
                        ? $errorDescription
                        : 'Unknown error.')
                );
            }

            $games = [];

            if (isset($xml->params->oyunListesi->oyun)) {
                foreach (
                    $xml->params->oyunListesi->oyun
                    as $game
                ) {
                    $id = trim((string) $game->id);
                    $name = trim((string) $game->name);

                    if ($id === '' || $name === '') {
                        continue;
                    }

                    $games[] = [
                        'id' => $id,
                        'name' => $name,
                    ];
                }
            }

            return $games;
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousUseInternalErrors
            );
        }
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