<?php

// Scalar türlerde beklenmeyen otomatik dönüşümleri azaltmak ve tip hatalarını erken yakalamak için strict mode kullanıyoruz.
declare(strict_types=1);

namespace Turkpin\InterviewTest\Services;

use RuntimeException;

// Bu servis için kalıtıma ihtiyaç olmadığı için sınıfın miras alınmasını engelliyoruz.
final class TurkpinApiClient
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 15;

    // API bağlantı bilgileri nesne oluşturulduktan sonra değişmemesi için readonly tutuluyor.
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

    public function getProducts(string $gameCode): array
    {
        $gameCode = trim($gameCode);

        if ($gameCode === '') {
            throw new RuntimeException(
                'Game code cannot be empty.'
            );
        }

        $response = $this->request(
            'epinUrunleri',
            [
                'oyunKodu' => $gameCode,
            ]
        );

        return $this->parseProductList($response);
    }

    // Turkpin API'ye yapılan HTTP isteklerini tek merkezden yönetiyoruz.
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

            // API'ye XML yanıt tercih ettiğimizi bildiriyoruz; gerçek yanıt yine sunucu tarafından belirlenir.
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

    // API'nin beklediği XML gövdesini tek bir standart noktada oluşturuyoruz.
    private function buildRequestXml(
        string $command,
        array $parameters = []
    ): string {
        // XML için özel karakterleri escape ederek bozuk XML oluşmasını önlüyoruz.
        $escape = static fn (string $value): string =>
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
            // Parametre adının güvenli ve geçerli bir XML etiket adı olmasını kontrol ediyoruz.
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

            // HTTP isteği başarılı olsa bile API işlem kodunun başarılı olduğunu ayrıca doğruluyoruz.
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

    private function parseProductList(string $response): array
    {
        // XML cevabını işleyebilmek için SimpleXML eklentisinin açık olması gerekir.
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException(
                'PHP SimpleXML extension is not enabled.'
            );
        }

        // XML parse hatalarını ekrana basmak yerine kontrollü şekilde yönetiyoruz.
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

            // HTTP 200 tek başına yeterli değildir; Turkpin işlem kodunun da 000 olması gerekir.
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

            $products = [];

            // Ürün listesi yoksa hata vermek yerine boş liste dönüyoruz.
            if (!isset($xml->params->epinUrunListesi->urun)) {
                return $products;
            }

            foreach (
                $xml->params->epinUrunListesi->urun
                as $product
            ) {
                $id = trim((string) $product->id);
                $name = trim((string) $product->name);

                // Kimliği veya adı olmayan eksik ürünleri listeye dahil etmiyoruz.
                if ($id === '' || $name === '') {
                    continue;
                }

                $maxOrderRaw = trim(
                    (string) $product->max_order
                );

                $preOrderRaw = strtolower(
                    trim((string) $product->pre_order)
                );

                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'stock' => (int) $product->stock,

                    // Minimum siparişi en az 1 olacak şekilde normalize ediyoruz.
                    'min_order' => max(
                        1,
                        (int) $product->min_order
                    ),

                    // API'de boş veya 0 max_order üst sınır olmadığı anlamına geliyor.
                    'max_order' =>
                        $maxOrderRaw === ''
                        || $maxOrderRaw === '0'
                            ? null
                            : (int) $maxOrderRaw,

                    // Para değerlerinde float hassasiyet sorunundan kaçınmak için fiyatı string tutuyoruz.
                    'price' => trim(
                        (string) $product->price
                    ),

                    'tax_type' => trim(
                        (string) $product->tax_type
                    ),

                    // "false" boş olmayan bir string olduğu için doğrudan bool cast yerine açık karşılaştırma yapıyoruz.
                    'pre_order' =>
                        $preOrderRaw === 'true'
                        || $preOrderRaw === '1',

                    // Barem alanları yalnızca baremli ürünlerde gelir değilse yoksa null bırakıyoruz.
                    'min_barem' =>
                        isset($product->min_barem)
                        && trim((string) $product->min_barem) !== ''
                            ? trim((string) $product->min_barem)
                            : null,

                    'max_barem' =>
                        isset($product->max_barem)
                        && trim((string) $product->max_barem) !== ''
                            ? trim((string) $product->max_barem)
                            : null,

                    'barem_step' =>
                        isset($product->barem_step)
                        && trim((string) $product->barem_step) !== ''
                            ? trim((string) $product->barem_step)
                            : null,
                ];
            }

            return $products;
        } finally {
            // Parse sırasında biriken libxml hatalarını temizleyip önceki global ayarı geri yüklüyoruz.
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousUseInternalErrors
            );
        }
    }

    // Eksik veya geçersiz ortam ayarlarını API çağrısından önce yakalıyoruz.
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