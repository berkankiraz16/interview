<?php

// Scalar türlerde beklenmeyen otomatik dönüşümleri azaltmak ve tip hatalarını erken yakalamak için strict mode kullanıyoruz.
declare(strict_types=1);

namespace Turkpin\InterviewTest\Services;

use RuntimeException;
use Turkpin\InterviewTest\Contracts\TurkpinApiGateway;

// Bu servis için kalıtıma ihtiyaç olmadığı için sınıfın miras alınmasını engelliyoruz.
/**
 * @phpstan-import-type GameData from TurkpinResponseParser
 * @phpstan-import-type ProductData from TurkpinResponseParser
 * @phpstan-import-type OrderResult from TurkpinResponseParser
 */
final class TurkpinApiClient implements TurkpinApiGateway
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 15;

    private readonly TurkpinResponseParser $responseParser;
    // API bağlantı bilgileri nesne oluşturulduktan sonra değişmemesi için readonly tutuluyor.
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $orderSubmissionEnabled = false,
        ?TurkpinResponseParser $responseParser = null,
    ) {
        $this->responseParser = $responseParser ?? new TurkpinResponseParser();
        $this->validateConfiguration();
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::env('TURKPIN_API_URL'),
            self::env('TURKPIN_API_USERNAME'),
            self::env('TURKPIN_API_PASSWORD'),
            self::envBoolean(
                'TURKPIN_ORDER_ENABLED',
                false
            ),
        );
    }

    /**
     * @return list<GameData>
     */
    public function getGames(): array
    {
        $response = $this->request(
            'epinOyunListesi'
        );

        return $this->responseParser->parseGameList($response);
    }

    public function isOrderSubmissionEnabled(): bool
    {
        return $this->orderSubmissionEnabled;
    }

    /**
     * @return list<ProductData>
     */
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

        return $this->responseParser->parseProductList($response);
    }

    /**
     * @return OrderResult
     */
    public function createOrder(
        string $gameCode,
        string $productCode,
        int $quantity,
        ?string $character = null,
        bool $preOrder = false,
        ?string $barem = null
    ): array {
        if (!$this->orderSubmissionEnabled) {
            throw new RuntimeException(
                'Turkpin order submission is disabled by configuration.'
            );
        }
        $gameCode = trim($gameCode);
        $productCode = trim($productCode);
        $character = $character !== null
            ? trim($character)
            : null;
        $barem = $barem !== null
            ? trim($barem)
            : null;

        // API katmanında da temel doğrulama yapıyoruz.
        // Asıl iş kuralları daha sonra server-side validator tarafından kontrol edilecek.
        if ($gameCode === '') {
            throw new RuntimeException(
                'Game code cannot be empty.'
            );
        }

        if ($productCode === '') {
            throw new RuntimeException(
                'Product code cannot be empty.'
            );
        }

        if ($quantity < 1) {
            throw new RuntimeException(
                'Order quantity must be at least 1.'
            );
        }

        $parameters = [
            'oyunKodu' => $gameCode,
            'urunKodu' => $productCode,
            'adet' => $quantity,
        ];

        // character dokümana göre opsiyonel olduğu belirtildi.
        if ($character !== null && $character !== '') {
            $parameters['character'] = $character;
        }

        // Pre-order bilgisini kullanıcıdan değil
        // doğrulanmış ürün bilgisinden belirleyeceğiz.
        if ($preOrder) {
            $parameters['pre_order'] = 'true';
        }

        // Barem yalnızca baremli ürünlerde gönderilecek.
        if ($barem !== null && $barem !== '') {
            $parameters['barem'] = $barem;
        }

        $response = $this->request(
            'epinSiparisYarat',
            $parameters
        );

        return $this->responseParser->parseOrderResponse($response);
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private function request(string $command, array $parameters = []): string
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
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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

        if ($response === false) {
            throw new RuntimeException(
                'Turkpin API connection error: '
                . $curlError
            );
        }

        if (!is_string($response)) {
            throw new RuntimeException(
                'Turkpin API returned an unexpected response type.'
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

    /**
     * @param array<array-key, mixed> $parameters
     */
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

    // Eksik veya geçersiz ortam ayarlarını API çağrısından önce yakalıyoruz.
    private function validateConfiguration(): void
    {
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(
                'TURKPIN_API_URL is missing or invalid.'
            );
        }

        $scheme = strtolower(
            (string) parse_url(
                $this->baseUrl,
                PHP_URL_SCHEME
            )
        );

        if ($scheme !== 'https') {
            throw new RuntimeException(
                'TURKPIN_API_URL must use HTTPS.'
            );
        }

        if (trim($this->username) === '') {
            throw new RuntimeException(
                'TURKPIN_API_USERNAME is missing.'
            );
        }

        if (trim($this->password) === '') {
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

    private static function envBoolean(
        string $key,
        bool $default = false
    ): bool {
        $value = self::env($key);

        if ($value === '') {
            return $default;
        }

        $parsedValue = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $parsedValue ?? $default;
    }
}
