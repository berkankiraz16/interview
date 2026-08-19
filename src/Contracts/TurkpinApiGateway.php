<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Contracts;

use Turkpin\InterviewTest\Services\TurkpinResponseParser;

/**
 * @phpstan-import-type GameData from TurkpinResponseParser
 * @phpstan-import-type ProductData from TurkpinResponseParser
 * @phpstan-import-type OrderResult from TurkpinResponseParser
 */
interface TurkpinApiGateway
{
    /**
     * @return list<GameData>
     */
    public function getGames(): array;

    /**
     * @return list<ProductData>
     */
    public function getProducts(string $gameCode): array;

    public function isOrderSubmissionEnabled(): bool;

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
    ): array;
}
