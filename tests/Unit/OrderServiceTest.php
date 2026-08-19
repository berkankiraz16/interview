<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Turkpin\InterviewTest\Contracts\TurkpinApiGateway;
use Turkpin\InterviewTest\Exceptions\OrderValidationException;
use Turkpin\InterviewTest\Services\OrderService;
use Turkpin\InterviewTest\Validation\OrderValidator;

final class OrderServiceTest extends TestCase
{
    public function testSuccessfulOrderUsesVerifiedProductData(): void
    {
        $gateway = $this->gateway(
            orderEnabled: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        $result = $service->submit(
            '1',
            '100',
            2
        );

        self::assertSame(
            'ORDER-1',
            $result['order_number']
        );

        self::assertSame(
            '20.00',
            $result['amount']
        );
    }

    public function testInvalidGameIsRejectedBeforeProductLookup(): void
    {
        $gateway = $this->gateway(
            productLookupThrows: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        try {
            $service->submit(
                '999',
                '100',
                1
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'invalid_game_selection',
                $exception->getTranslationKey()
            );
        }
    }

    public function testInvalidProductIsRejected(): void
    {
        $gateway = $this->gateway(
            orderThrows: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        try {
            $service->submit(
                '1',
                '999',
                1
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'invalid_product_selection',
                $exception->getTranslationKey()
            );
        }
    }
    public function testValidationFailurePreventsOrderCreation(): void
    {
        $gateway = $this->gateway(
            orderThrows: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        try {
            $service->submit(
                '1',
                '100',
                50
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_maximum_quantity',
                $exception->getTranslationKey()
            );
        }
    }

    public function testDisabledOrderSubmissionIsRejected(): void
    {
        $gateway = $this->gateway(
            orderEnabled: false,
            orderThrows: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        try {
            $service->submit(
                '1',
                '100',
                1
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_submission_disabled',
                $exception->getTranslationKey()
            );
        }
    }

    public function testApiExceptionIsPropagated(): void
    {
        $gateway = $this->gateway(
            orderEnabled: true,
            orderThrows: true
        );

        $service = new OrderService(
            $gateway,
            new OrderValidator()
        );

        $this->expectException(
            RuntimeException::class
        );

        $service->submit(
            '1',
            '100',
            1
        );
    }

    private function gateway(
        bool $orderEnabled = true,
        bool $orderThrows = false,
        bool $productLookupThrows = false
    ): TurkpinApiGateway {
        return new class (
            $orderEnabled,
            $orderThrows,
            $productLookupThrows
        ) implements TurkpinApiGateway {
            public function __construct(
                private readonly bool $orderEnabled,
                private readonly bool $orderThrows,
                private readonly bool $productLookupThrows
            ) {
            }

            public function getGames(): array
            {
                return [
                    [
                        'id' => '1',
                        'name' => 'Game 1',
                    ],
                ];
            }

            public function getProducts(
                string $gameCode
            ): array {
                if ($this->productLookupThrows) {
                    throw new RuntimeException(
                        'Product lookup should not have been called.'
                    );
                }

                return [
                    [
                        'id' => '100',
                        'name' => 'Product 1',
                        'stock' => 10,
                        'min_order' => 1,
                        'max_order' => 5,
                        'price' => '10.00',
                        'tax_type' => 'included',
                        'pre_order' => false,
                        'min_barem' => null,
                        'max_barem' => null,
                        'barem_step' => null,
                    ],
                ];
            }

            public function isOrderSubmissionEnabled(): bool
            {
                return $this->orderEnabled;
            }

            public function createOrder(
                string $gameCode,
                string $productCode,
                int $quantity,
                ?string $character = null,
                bool $preOrder = false,
                ?string $barem = null
            ): array {
                if ($this->orderThrows) {
                    throw new RuntimeException(
                        'Simulated API failure.'
                    );
                }

                return [
                    'order_number' => 'ORDER-1',
                    'status' => 'Success',
                    'amount' => '20.00',
                    'epins' => [
                        [
                            'id' => '1',
                            'code' => 'TEST-CODE',
                            'description' => 'Test E-Pin',
                        ],
                    ],
                ];
            }
        };
    }
}
