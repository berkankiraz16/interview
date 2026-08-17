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
        private readonly bool $orderSubmissionEnabled = false,
    ) {
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

    public function getGames(): array
    {
        $response = $this->request(
            'epinOyunListesi'
        );

        return $this->parseGameList($response);
    }

    public function isOrderSubmissionEnabled(): bool
    {
    return $this->orderSubmissionEnabled;
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

    return $this->parseOrderResponse($response);
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

    private function parseOrderResponse(string $response): array
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
                'Turkpin API returned invalid order XML.'
            );
        }

        /*
         * Sipariş endpoint'i liste endpointlerinden farklı olarak
         * error/error_desc yerine HATA_NO/HATA_ACIKLAMA kullanıyor.
         */
        $errorCode = trim(
            (string) ($xml->params->HATA_NO ?? '')
        );

        $errorDescription = trim(
            (string) ($xml->params->HATA_ACIKLAMA ?? '')
        );

        /*
        * API seviyesinde hata olmasa dahi (000), siparişin kesinleştiğini teyit etmek için
        * 'siparisSonuc' alanını büyük-küçük harf duyarsız olarak kontrol ediyoruz.
        * Sadece 'Success' dışındaki tüm durumlarda (Pending, Failed vb.) hata fırlatılır.
        */ 
       if ($errorCode !== '000') {
            throw new RuntimeException(
                'Turkpin order error'
                . ($errorCode !== ''
                    ? " ({$errorCode})"
                    : '')
                . ': '
                . ($errorDescription !== ''
                    ? $errorDescription
                    : 'Unknown error.')
            );
        }

        $status = trim(
            (string) ($xml->params->siparisSonuc ?? '')
        );

        /*
         * HATA_NO=000 olsa bile siparisSonuc alanını ayrıca kontrol ediyoruz.
         * Dokümandaki başarılı response değeri "Success".
         */
        if (strcasecmp($status, 'Success') !== 0) {
            throw new RuntimeException(
                'Turkpin order was not successful'
                . ($status !== ''
                    ? ": {$status}"
                    : '.')
            );
        }

        $epins = [];

        /*
         * adet > 1 olabileceği için tek bir e-pin varsaymıyoruz.
         * Response'taki epin_list içindeki tüm kodları topluyoruz.
         */
        if (isset($xml->params->epin_list->epin)) {
            foreach (
                $xml->params->epin_list->epin
                as $epin
            ) {
                $epins[] = [
                    'id' => trim(
                        (string) ($epin->id ?? '')
                    ),

                    'code' => trim(
                        (string) ($epin->code ?? '')
                    ),

                    'description' => trim(
                        (string) ($epin->desc ?? '')
                    ),
                ];
            }
        }

        return [
            // Sipariş numarası matematiksel sayı değil, identifier olduğu için string tutuluyor.
            'order_number' => trim(
                (string) ($xml->params->siparisNo ?? '')
            ),

            'status' => $status,

            // Para değerini float'a çevirmeyip decimal string olarak saklıyoruz.
            'amount' => trim(
                (string) ($xml->params->siparisTutari ?? '')
            ),

            'epins' => $epins,
        ];
    } finally {
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