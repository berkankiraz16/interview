<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Services;

use RuntimeException;

/*
 * Turkpin API'den dönen XML response'larını uygulamanın
 * kullanacağı normalize edilmiş array yapılarına dönüştürür.
 *
 * HTTP isteği yapmak bu sınıfın sorumluluğu değildir.
 */

/**
 * @phpstan-type GameData array{
 * id: string,
 * name: string
 * }
 *
 * @phpstan-type ProductData array{
 * id: string,
 * name: string,
 * stock: int,
 * min_order: int,
 * max_order: int|null,
 * price: string,
 * tax_type: string,
 * pre_order: bool,
 * min_barem: string|null,
 * max_barem: string|null,
 * barem_step: string|null
 * }
 *
 * @phpstan-type EpinData array{
 * id: string,
 * code: string,
 * description: string
 * }
 *
 * @phpstan-type OrderResult array{
 * order_number: string,
 * status: string,
 * amount: string,
 * epins: list<EpinData>
 * }
 */
final class TurkpinResponseParser
{
    /**
     * @return list<GameData>
     */
    public function parseGameList(string $response): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException(
                'PHP SimpleXML extension is not enabled.'
            );
        }

        /*
         * libxml parse hatalarının doğrudan ekrana basılmasını
         * engelliyoruz. İşlem sonunda önceki global ayarı geri
         * yükleyeceğiz.
         */
        $previousUseInternalErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $response,
                \SimpleXMLElement::class,
                LIBXML_NONET
            );

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

            /*
             * HTTP 2xx alınması işlemin başarılı olduğu anlamına
             * gelmez. Turkpin business-level success kodu da
             * ayrıca "000" olmalıdır.
             */
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
                    $xml->params->oyunListesi->oyun as $game
                ) {
                    $id = trim(
                        (string) $game->id
                    );

                    $name = trim(
                        (string) $game->name
                    );

                    /*
                     * Eksik kimlik veya isim taşıyan API kayıtlarını
                     * uygulama katmanına taşımıyoruz.
                     */
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
            /*
             * libxml global state'ini değiştirdiğimiz için cleanup
             * exception oluşsa bile finally içerisinde yapılmalıdır.
             */
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousUseInternalErrors
            );
        }
    }

    /**
     * @return list<ProductData>
     */
    public function parseProductList(string $response): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException(
                'PHP SimpleXML extension is not enabled.'
            );
        }

        $previousUseInternalErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $response,
                \SimpleXMLElement::class,
                LIBXML_NONET
            );

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

            /*
            * HTTP seviyesindeki başarı tek başına yeterli değildir.
            * Turkpin business response kodunun da "000" olması gerekir.
            */
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

            /*
            * Başarılı response'ta ürün olmaması bir API hatası değildir.
            * Uygulama katmanına boş liste döndürürüz.
            */
            if (!isset($xml->params->epinUrunListesi->urun)) {
                return $products;
            }

            foreach (
                $xml->params->epinUrunListesi->urun as $product
            ) {
                $id = trim(
                    (string) $product->id
                );

                $name = trim(
                    (string) $product->name
                );

                /*
                * id veya name bulunmayan eksik API kayıtlarını
                * uygulama katmanına taşımıyoruz.
                */
                if ($id === '' || $name === '') {
                    continue;
                }

                $stockRaw = trim(
                    (string) $product->stock
                );

                $stock = filter_var(
                    $stockRaw,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 0,
                        ],
                    ]
                );

                if ($stock === false) {
                    throw new RuntimeException(
                        'Turkpin product response contains invalid stock.'
                    );
                }

                /*
                 * min_order gerçekten integer mı kontrol ediyoruz.
                 * "abc" gibi bozuk API verilerini sessizce 1'e çevirmiyoruz.
                 */
                $minOrderRaw = trim(
                    (string) $product->min_order
                );

                $minOrder = filter_var(
                    $minOrderRaw,
                    FILTER_VALIDATE_INT
                );

                if ($minOrder === false) {
                    throw new RuntimeException(
                        'Turkpin product response contains invalid minimum order.'
                    );
                }

                /*
                 * API 0 veya negatif minimum döndürürse mevcut uygulama
                 * sözleşmesini koruyarak minimum değeri 1'e çekiyoruz.
                 */
                $minOrder = max(
                    1,
                    $minOrder
                );

                $maxOrderRaw = trim(
                    (string) $product->max_order
                );

                $maxOrder = null;

                if (
                    $maxOrderRaw !== ''
                    && $maxOrderRaw !== '0'
                ) {
                    $validatedMaxOrder = filter_var(
                        $maxOrderRaw,
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => 1,
                            ],
                        ]
                    );

                    if ($validatedMaxOrder === false) {
                        throw new RuntimeException(
                            'Turkpin product response contains invalid max order.'
                        );
                    }

                    $maxOrder = $validatedMaxOrder;
                }

                $preOrderRaw = strtolower(
                    trim((string) $product->pre_order)
                );

                $products[] = [
                    'id' => $id,

                    'name' => $name,

                    'stock' => $stock,

                    /*
                    * Sipariş miktarının sıfır veya negatif minimuma
                    * sahip olmaması için en az 1'e normalize ediyoruz.
                    */
                    'min_order' => $minOrder,

                    /*
                    * Turkpin response'unda boş veya "0" max_order,
                    * üst sipariş limiti bulunmadığı anlamında
                    * uygulamada null olarak temsil edilir.
                    */

                    'max_order' => $maxOrder,

                    /*
                    * Fiyat parasal/decimal bir değer olduğu için
                    * binary floating-point hassasiyet kaybını önlemek
                    * amacıyla string olarak tutulur.
                    */
                    'price' => trim(
                        (string) $product->price
                    ),

                    'tax_type' => trim(
                        (string) $product->tax_type
                    ),

                    /*
                    * PHP'de (bool) "false" değeri true olur.
                    * Bu nedenle API stringini doğrudan bool'a cast
                    * etmek yerine açık karşılaştırma yapıyoruz.
                    */
                    'pre_order' =>
                        $preOrderRaw === 'true'
                        || $preOrderRaw === '1',

                    /*
                    * Barem alanları normal ürünlerde bulunmayabilir.
                    * Eksik veya boş değerleri null'a normalize ediyoruz.
                    */
                    'min_barem' =>
                        isset($product->min_barem)
                        && trim((string) $product->min_barem) !== ''
                            ? trim(
                                (string) $product->min_barem
                            )
                            : null,

                    'max_barem' =>
                        isset($product->max_barem)
                        && trim((string) $product->max_barem) !== ''
                            ? trim(
                                (string) $product->max_barem
                            )
                            : null,

                    'barem_step' =>
                        isset($product->barem_step)
                        && trim((string) $product->barem_step) !== ''
                            ? trim(
                                (string) $product->barem_step
                            )
                            : null,
                ];
            }

            return $products;
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousUseInternalErrors
            );
        }
    }

    /**
     * @return OrderResult
     */
    public function parseOrderResponse(string $response): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException(
                'PHP SimpleXML extension is not enabled.'
            );
        }

        $previousUseInternalErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $response,
                \SimpleXMLElement::class,
                LIBXML_NONET
            );

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
            * HATA_NO=000 olsa bile siparisSonuc ayrıca kontrol edilir.
            * Dokümante edilen başarılı değer "Success"tir.
            */
            if (strcasecmp($status, 'Success') !== 0) {
                throw new RuntimeException(
                    'Turkpin order was not successful'
                    . ($status !== ''
                        ? ": {$status}"
                        : '.')
                );
            }

            $orderNumber = trim(
                (string) ($xml->params->siparisNo ?? '')
            );

            if ($orderNumber === '') {
                throw new RuntimeException(
                    'Turkpin order response is missing order number.'
                );
            }

            $amount = trim(
                (string) ($xml->params->siparisTutari ?? '')
            );

            if ($amount === '') {
                throw new RuntimeException(
                    'Turkpin order response is missing amount.'
                );
            }

            $epins = [];

            /*
            * Sipariş miktarı birden büyük olabileceği için
            * response içindeki bütün E-Pin kayıtlarını topluyoruz.
            */
            if (isset($xml->params->epin_list->epin)) {
                foreach (
                    $xml->params->epin_list->epin as $epin
                ) {
                    $epinCode = trim(
                        (string) ($epin->code ?? '')
                    );

                    if ($epinCode === '') {
                        throw new RuntimeException(
                            'Turkpin order response contains an E-Pin without code.'
                        );
                    }

                    $epins[] = [
                        'id' => trim(
                            (string) ($epin->id ?? '')
                        ),

                        'code' => $epinCode,

                        'description' => trim(
                            (string) ($epin->desc ?? '')
                        ),
                    ];
                }
            }

            return [
                /*
                * Sipariş numarası matematiksel değer değil,
                * identifier olduğu için string tutulur.
                */
                'order_number' => $orderNumber,

                'status' => $status,

                /*
                * Parasal/decimal değerlerde float hassasiyet
                * kaybından kaçınmak için amount string tutulur.
                */

                'amount' => $amount,

                'epins' => $epins,
            ];
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousUseInternalErrors
            );
        }
    }
}
