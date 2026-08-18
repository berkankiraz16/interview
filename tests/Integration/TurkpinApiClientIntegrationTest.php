<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Integration;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Services\TurkpinApiClient;

final class TurkpinApiClientIntegrationTest extends TestCase
{
    private TurkpinApiClient $client;

    protected function setUp(): void
    {
        /*
         * Integration testleri gerçek Turkpin servisine bağlandığı için
         * varsayılan PHPUnit çalıştırmasında devre dışıdır.
         *
         * Böylece CI, reviewer makinesi veya whitelist dışındaki bir
         * ortam yanlışlıkla dış servise istek göndermez.
         */
        if (
            filter_var(
                getenv('TURKPIN_RUN_INTEGRATION_TESTS'),
                FILTER_VALIDATE_BOOLEAN
            ) !== true
        ) {
            self::markTestSkipped(
                'Turkpin integration tests are disabled.'
            );
        }

        $projectRoot = dirname(__DIR__, 2);

        if (is_file($projectRoot . '/.env')) {
            Dotenv::createImmutable(
                $projectRoot
            )->safeLoad();
        }

        $this->client =
            TurkpinApiClient::fromEnvironment();
    }

    /*
     * Gerçek endpoint, authentication, SSL, IPv4 whitelist,
     * HTTP transport ve game response parsing zincirini birlikte test eder.
     */
    public function testCanFetchGamesFromRealApi(): void
    {
        $games = $this->client->getGames();

        self::assertNotEmpty($games);

        foreach ($games as $game) {
            self::assertArrayHasKey(
                'id',
                $game
            );

            self::assertArrayHasKey(
                'name',
                $game
            );

            self::assertNotSame(
                '',
                $game['id']
            );

            self::assertNotSame(
                '',
                $game['name']
            );
        }
    }

    /*
     * Önce gerçek oyun listesini alıp ardından gerçek bir oyun koduyla
     * ürün endpoint'ini çağırıyoruz. Böylece hard-coded game id
     * bağımlılığını mümkün olduğunca azaltıyoruz.
     */
    public function testCanFetchProductsFromRealApi(): void
    {
        $games = $this->client->getGames();

        self::assertNotEmpty($games);

        /*
         * Test ortamında ürün bulunduğunu bildiğimiz Game 1 varsa
         * onu kullanıyoruz; yoksa ilk API oyununa fallback ediyoruz.
         */
        $selectedGame = null;

        foreach ($games as $game) {
            if ($game['id'] === '1') {
                $selectedGame = $game;

                break;
            }
        }

        $selectedGame ??= $games[0];

        $products = $this->client->getProducts(
            $selectedGame['id']
        );

        self::assertNotEmpty($products);

        foreach ($products as $product) {
            self::assertArrayHasKey(
                'id',
                $product
            );

            self::assertArrayHasKey(
                'name',
                $product
            );

            self::assertArrayHasKey(
                'stock',
                $product
            );

            self::assertArrayHasKey(
                'pre_order',
                $product
            );
        }
    }
}
