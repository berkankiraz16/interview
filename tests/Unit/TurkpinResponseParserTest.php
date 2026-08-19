<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Turkpin\InterviewTest\Services\TurkpinResponseParser;

final class TurkpinResponseParserTest extends TestCase
{
    private TurkpinResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TurkpinResponseParser();
    }

    /*
     * Happy-path:
     * Turkpin'in başarılı oyun listesi response'u normalize edilmiş
     * id/name array yapısına dönüştürülmelidir.
     */
    public function testParseGameListReturnsNormalizedGames(): void
    {
        $response = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <oyunListesi>
            <oyun>
                <id>1</id>
                <name>Game 1</name>
            </oyun>
            <oyun>
                <id>2</id>
                <name>Game 2</name>
            </oyun>
            <oyun>
                <id>3</id>
                <name>Game 3</name>
            </oyun>
        </oyunListesi>
    </params>
</APIResponse>
XML;

        self::assertSame(
            [
                [
                    'id' => '1',
                    'name' => 'Game 1',
                ],
                [
                    'id' => '2',
                    'name' => 'Game 2',
                ],
                [
                    'id' => '3',
                    'name' => 'Game 3',
                ],
            ],
            $this->parser->parseGameList($response)
        );
    }

    public function testParseGameListSkipsIncompleteGames(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <oyunListesi>
            <oyun>
                <id>1</id>
                <name>Game 1</name>
            </oyun>
            <oyun>
                <id></id>
                <name>Missing Id</name>
            </oyun>
            <oyun>
                <id>3</id>
                <name></name>
            </oyun>
        </oyunListesi>
    </params>
</APIResponse>
XML;

        self::assertSame(
            [
                [
                    'id' => '1',
                    'name' => 'Game 1',
                ],
            ],
            $this->parser->parseGameList($response)
        );
    }

    /*
     * HTTP 2xx tek başına business success anlamına gelmez.
     * Turkpin'in XML içindeki error kodu da "000" olmalıdır.
     */
    public function testParseGameListRejectsBusinessError(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>101</error>
        <error_desc>Invalid credentials</error_desc>
    </params>
</APIResponse>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin API error (101): Invalid credentials'
        );

        $this->parser->parseGameList($response);
    }

    public function testParseGameListRejectsInvalidXml(): void
    {
        $response = '<APIResponse><params><error>000</error>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin API returned invalid XML.'
        );

        $this->parser->parseGameList($response);
    }

    public function testParseGameListReturnsEmptyArrayWhenNoGamesExist(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <oyunListesi />
    </params>
</APIResponse>
XML;

        self::assertSame(
            [],
            $this->parser->parseGameList($response)
        );
    }

    /*
     * Normal, pre-order ve baremli ürünlerin Turkpin XML'inden
     * uygulamanın kullandığı veri modeline doğru normalize edildiğini
     * tek bir gerçekçi response üzerinden doğrular.
     */
    public function testParseProductListReturnsNormalizedProducts(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi>
            <urun>
                <id>1</id>
                <name>Product 1</name>
                <stock>13765</stock>
                <min_order>1</min_order>
                <max_order></max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>false</pre_order>
            </urun>
            <urun>
                <id>3</id>
                <name>Product Pre-Order</name>
                <stock>0</stock>
                <min_order>1</min_order>
                <max_order>0</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>true</pre_order>
            </urun>
            <urun>
                <id>4</id>
                <name>Product Barem</name>
                <stock>0</stock>
                <min_order>1</min_order>
                <max_order>0</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>true</pre_order>
                <min_barem>25</min_barem>
                <max_barem>1250</max_barem>
                <barem_step>0.01</barem_step>
            </urun>
        </epinUrunListesi>
    </params>
</APIResponse>
XML;

        self::assertSame(
            [
                [
                    'id' => '1',
                    'name' => 'Product 1',
                    'stock' => 13765,
                    'min_order' => 1,
                    'max_order' => null,
                    'price' => '0.001',
                    'tax_type' => '1',
                    'pre_order' => false,
                    'min_barem' => null,
                    'max_barem' => null,
                    'barem_step' => null,
                ],
                [
                    'id' => '3',
                    'name' => 'Product Pre-Order',
                    'stock' => 0,
                    'min_order' => 1,
                    'max_order' => null,
                    'price' => '0.001',
                    'tax_type' => '1',
                    'pre_order' => true,
                    'min_barem' => null,
                    'max_barem' => null,
                    'barem_step' => null,
                ],
                [
                    'id' => '4',
                    'name' => 'Product Barem',
                    'stock' => 0,
                    'min_order' => 1,
                    'max_order' => null,
                    'price' => '0.001',
                    'tax_type' => '1',
                    'pre_order' => true,
                    'min_barem' => '25',
                    'max_barem' => '1250',
                    'barem_step' => '0.01',
                ],
            ],
            $this->parser->parseProductList($response)
        );
    }

    /*
     * PHP'de (bool) "false" true sonucunu verir.
     * Bu test, API'nin "false" stringinin açık karşılaştırmayla
     * gerçekten false'a dönüştürüldüğünü özellikle güvence altına alır.
     */
    public function testParseProductListTreatsFalseStringAsFalse(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi>
            <urun>
                <id>1</id>
                <name>Normal Product</name>
                <stock>10</stock>
                <min_order>1</min_order>
                <max_order>5</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>false</pre_order>
            </urun>
        </epinUrunListesi>
    </params>
</APIResponse>
XML;

        $products = $this->parser->parseProductList($response);

        self::assertFalse(
            $products[0]['pre_order']
        );
    }

    public function testParseProductListConvertsPositiveMaxOrderToInteger(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi>
            <urun>
                <id>1</id>
                <name>Limited Product</name>
                <stock>100</stock>
                <min_order>1</min_order>
                <max_order>25</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>false</pre_order>
            </urun>
        </epinUrunListesi>
    </params>
</APIResponse>
XML;

        $products = $this->parser->parseProductList($response);

        self::assertSame(
            25,
            $products[0]['max_order']
        );
    }

    public function testParseProductListRejectsInvalidMaxOrder(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <error>000</error>
            <error_desc>Islem Basarili</error_desc>
            <epinUrunListesi>
                <urun>
                    <id>1</id>
                    <name>Product</name>
                    <stock>10</stock>
                    <min_order>1</min_order>
                    <max_order>abc</max_order>
                    <price>0.001</price>
                    <tax_type>1</tax_type>
                    <pre_order>false</pre_order>
                </urun>
            </epinUrunListesi>
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin product response contains invalid max order.'
        );

        $this->parser->parseProductList($response);
    }

    public function testParseProductListRejectsInvalidStock(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <error>000</error>
            <error_desc>Islem Basarili</error_desc>
            <epinUrunListesi>
                <urun>
                    <id>1</id>
                    <name>Product</name>
                    <stock>abc</stock>
                    <min_order>1</min_order>
                    <max_order>5</max_order>
                    <price>0.001</price>
                    <tax_type>1</tax_type>
                    <pre_order>false</pre_order>
                </urun>
            </epinUrunListesi>
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin product response contains invalid stock.'
        );

        $this->parser->parseProductList($response);
    }

    public function testParseProductListRejectsInvalidMinimumOrder(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <error>000</error>
            <error_desc>Islem Basarili</error_desc>
            <epinUrunListesi>
                <urun>
                    <id>1</id>
                    <name>Product</name>
                    <stock>10</stock>
                    <min_order>abc</min_order>
                    <max_order>5</max_order>
                    <price>0.001</price>
                    <tax_type>1</tax_type>
                    <pre_order>false</pre_order>
                </urun>
            </epinUrunListesi>
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin product response contains invalid minimum order.'
        );

        $this->parser->parseProductList($response);
    }

    /*
     * Parser'ın mevcut sözleşmesinde API min_order=0 veya negatif bir
     * değer döndürürse uygulama tarafında minimum 1'e normalize edilir.
     */
    public function testParseProductListNormalizesMinimumOrderToOne(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi>
            <urun>
                <id>1</id>
                <name>Product</name>
                <stock>10</stock>
                <min_order>0</min_order>
                <max_order>5</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>false</pre_order>
            </urun>
        </epinUrunListesi>
    </params>
</APIResponse>
XML;

        $products = $this->parser->parseProductList($response);

        self::assertSame(
            1,
            $products[0]['min_order']
        );
    }

    public function testParseProductListSkipsIncompleteProducts(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi>
            <urun>
                <id>1</id>
                <name>Valid Product</name>
                <stock>10</stock>
                <min_order>1</min_order>
                <max_order>5</max_order>
                <price>0.001</price>
                <tax_type>1</tax_type>
                <pre_order>false</pre_order>
            </urun>
            <urun>
                <id></id>
                <name>Missing Id</name>
            </urun>
            <urun>
                <id>3</id>
                <name></name>
            </urun>
        </epinUrunListesi>
    </params>
</APIResponse>
XML;

        $products = $this->parser->parseProductList($response);

        self::assertCount(
            1,
            $products
        );

        self::assertSame(
            '1',
            $products[0]['id']
        );
    }

    public function testParseProductListReturnsEmptyArrayWhenNoProductsExist(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>000</error>
        <error_desc>Islem Basarili</error_desc>
        <epinUrunListesi />
    </params>
</APIResponse>
XML;

        self::assertSame(
            [],
            $this->parser->parseProductList($response)
        );
    }

    public function testParseProductListRejectsBusinessError(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <error>201</error>
        <error_desc>Product list failed</error_desc>
    </params>
</APIResponse>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin API error (201): Product list failed'
        );

        $this->parser->parseProductList($response);
    }

    public function testParseProductListRejectsInvalidXml(): void
    {
        $response = '<APIResponse><params><error>000</error>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin API returned invalid XML.'
        );

        $this->parser->parseProductList($response);
    }

    public function testParseOrderResponseReturnsNormalizedOrder(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <HATA_NO>000</HATA_NO>
        <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
        <siparisSonuc>Success</siparisSonuc>
        <siparisNo>ORD-12345</siparisNo>
        <siparisTutari>0.002</siparisTutari>
        <epin_list>
            <epin>
                <id>1</id>
                <code>EPIN-CODE-1</code>
                <desc>First E-Pin</desc>
            </epin>
            <epin>
                <id>2</id>
                <code>EPIN-CODE-2</code>
                <desc>Second E-Pin</desc>
            </epin>
        </epin_list>
    </params>
</APIResponse>
XML;

        self::assertSame(
            [
                'order_number' => 'ORD-12345',
                'status' => 'Success',
                'amount' => '0.002',
                'epins' => [
                    [
                        'id' => '1',
                        'code' => 'EPIN-CODE-1',
                        'description' => 'First E-Pin',
                    ],
                    [
                        'id' => '2',
                        'code' => 'EPIN-CODE-2',
                        'description' => 'Second E-Pin',
                    ],
                ],
            ],
            $this->parser->parseOrderResponse($response)
        );
    }

    public function testParseOrderResponseRejectsBusinessError(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <HATA_NO>105</HATA_NO>
        <HATA_ACIKLAMA>Insufficient balance</HATA_ACIKLAMA>
    </params>
</APIResponse>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order error (105): Insufficient balance'
        );

        $this->parser->parseOrderResponse($response);
    }

    public function testParseOrderResponseRejectsUnsuccessfulStatus(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <HATA_NO>000</HATA_NO>
        <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
        <siparisSonuc>Failed</siparisSonuc>
    </params>
</APIResponse>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order was not successful: Failed'
        );

        $this->parser->parseOrderResponse($response);
    }

    public function testParseOrderResponseRejectsMissingOrderNumber(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <HATA_NO>000</HATA_NO>
            <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
            <siparisSonuc>Success</siparisSonuc>
            <siparisTutari>0.001</siparisTutari>
            <epin_list />
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order response is missing order number.'
        );

        $this->parser->parseOrderResponse($response);
    }

    public function testParseOrderResponseRejectsMissingAmount(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <HATA_NO>000</HATA_NO>
            <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
            <siparisSonuc>Success</siparisSonuc>
            <siparisNo>ORD-12345</siparisNo>
            <epin_list />
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order response is missing amount.'
        );

        $this->parser->parseOrderResponse($response);
    }

    public function testParseOrderResponseRejectsEpinWithoutCode(): void
    {
        $response = <<<'XML'
    <APIResponse>
        <params>
            <HATA_NO>000</HATA_NO>
            <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
            <siparisSonuc>Success</siparisSonuc>
            <siparisNo>ORD-12345</siparisNo>
            <siparisTutari>0.001</siparisTutari>
            <epin_list>
                <epin>
                    <id>1</id>
                    <code></code>
                    <desc>Test E-Pin</desc>
                </epin>
            </epin_list>
        </params>
    </APIResponse>
    XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin order response contains an E-Pin without code.'
        );

        $this->parser->parseOrderResponse($response);
    }

    public function testParseOrderResponseAcceptsCaseInsensitiveSuccessStatus(): void
    {
        $response = <<<'XML'
<APIResponse>
    <params>
        <HATA_NO>000</HATA_NO>
        <HATA_ACIKLAMA>Islem Basarili</HATA_ACIKLAMA>
        <siparisSonuc>success</siparisSonuc>
        <siparisNo>ORD-1</siparisNo>
        <siparisTutari>0.001</siparisTutari>
        <epin_list />
    </params>
</APIResponse>
XML;

        $result = $this->parser->parseOrderResponse($response);

        self::assertSame(
            'success',
            $result['status']
        );

        self::assertSame(
            [],
            $result['epins']
        );
    }

    public function testParseOrderResponseRejectsInvalidXml(): void
    {
        $response = '<APIResponse><params><HATA_NO>000</HATA_NO>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Turkpin API returned invalid order XML.'
        );

        $this->parser->parseOrderResponse($response);
    }
}
