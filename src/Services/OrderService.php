<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Services;

use Turkpin\InterviewTest\Contracts\TurkpinApiGateway;
use Turkpin\InterviewTest\Exceptions\OrderValidationException;
use Turkpin\InterviewTest\Validation\OrderValidator;

/**
 * @phpstan-import-type OrderResult from TurkpinResponseParser
 */
final class OrderService
{
    public function __construct(
        private readonly TurkpinApiGateway $apiClient,
        private readonly OrderValidator $validator
    ) {
    }

    /**
     * @return OrderResult
     */
    public function submit(
        string $gameCode,
        string $productCode,
        int $quantity,
        ?string $barem = null
    ): array {
        /*
         * Browser'dan gelen game code'u authority kabul etmiyoruz.
         * Siparişten önce gerçek Turkpin kataloğundan tekrar doğruluyoruz.
         */
        $games = $this->apiClient->getGames();

        $validGameCodes = array_column(
            $games,
            'id'
        );

        if (
            $gameCode === ''
            || !in_array(
                $gameCode,
                $validGameCodes,
                true
            )
        ) {
            throw new OrderValidationException(
                'invalid_game_selection'
            );
        }

        /*
         * Product code'un gerçekten seçilen oyuna ait olduğunu
         * Turkpin'den yeniden alınan ürün listesi üzerinden doğruluyoruz.
         */
        $products = $this->apiClient->getProducts(
            $gameCode
        );

        $selectedProduct = null;

        foreach ($products as $product) {
            if ($product['id'] === $productCode) {
                $selectedProduct = $product;

                break;
            }
        }

        if ($selectedProduct === null) {
            throw new OrderValidationException(
                'invalid_product_selection'
            );
        }

        $this->validator->validate(
            $selectedProduct,
            $quantity,
            $barem
        );

        /*
         * Controller kontrolüne güvenmeden write işleminin
         * application-service sınırında da kapatılabilmesini sağlıyoruz.
         */
        if (!$this->apiClient->isOrderSubmissionEnabled()) {
            throw new OrderValidationException(
                'order_submission_disabled'
            );
        }

        /*
         * pre_order değerini browser'dan değil,
         * Turkpin'den doğrulanmış ürün bilgisinden kullanıyoruz.
         */
        return $this->apiClient->createOrder(
            $gameCode,
            $productCode,
            $quantity,
            null,
            $selectedProduct['pre_order'],
            $barem
        );
    }
}
