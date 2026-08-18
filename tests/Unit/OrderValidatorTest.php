<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Exceptions\OrderValidationException;
use Turkpin\InterviewTest\Validation\OrderValidator;

/**
 * @phpstan-import-type OrderProductData from OrderValidator
 */
final class OrderValidatorTest extends TestCase
{
    private OrderValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OrderValidator();
    }

    public function testNormalProductAcceptsValidQuantity(): void
    {
        $product = $this->normalProduct();

        $this->validator->validate(
            $product,
            3
        );

        $this->addToAssertionCount(1);
    }

    public function testQuantityBelowMinimumIsRejected(): void
    {
        $product = $this->normalProduct();
        $product['min_order'] = 3;

        try {
            $this->validator->validate(
                $product,
                2
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_minimum_quantity',
                $exception->getTranslationKey()
            );

            self::assertSame(
                [
                    'min' => 3,
                ],
                $exception->getParameters()
            );
        }
    }

    public function testQuantityAboveMaximumIsRejected(): void
    {
        $product = $this->normalProduct();

        try {
            $this->validator->validate(
                $product,
                6
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_maximum_quantity',
                $exception->getTranslationKey()
            );

            self::assertSame(
                [
                    'max' => 5,
                ],
                $exception->getParameters()
            );
        }
    }

    public function testNullMaxOrderDoesNotApplyMaximumOrderLimit(): void
    {
        $product = $this->normalProduct();
        $product['stock'] = 100;
        $product['max_order'] = null;

        $this->validator->validate(
            $product,
            50
        );

        $this->addToAssertionCount(1);
    }

    public function testOutOfStockNormalProductIsRejected(): void
    {
        $product = $this->normalProduct();
        $product['stock'] = 0;
        $product['max_order'] = null;

        try {
            $this->validator->validate(
                $product,
                1
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_stock_exceeded',
                $exception->getTranslationKey()
            );
        }
    }

    public function testPreOrderProductMayHaveZeroStock(): void
    {
        $product = $this->normalProduct();
        $product['stock'] = 0;
        $product['max_order'] = null;
        $product['pre_order'] = true;

        $this->validator->validate(
            $product,
            1
        );

        $this->addToAssertionCount(1);
    }

    public function testTieredProductRequiresBarem(): void
    {
        $product = $this->tieredProduct();

        try {
            $this->validator->validate(
                $product,
                1
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_required',
                $exception->getTranslationKey()
            );
        }
    }

    public function testMinimumBaremIsAccepted(): void
    {
        $product = $this->tieredProduct();

        $this->validator->validate(
            $product,
            1,
            '25'
        );

        $this->addToAssertionCount(1);
    }

    public function testValidDecimalBaremStepIsAccepted(): void
    {
        $product = $this->tieredProduct();

        $this->validator->validate(
            $product,
            1,
            '25.03'
        );

        $this->addToAssertionCount(1);
    }

    public function testBaremAboveMaximumIsRejected(): void
    {
        $product = $this->tieredProduct();

        try {
            $this->validator->validate(
                $product,
                1,
                '1250.01'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_range',
                $exception->getTranslationKey()
            );
        }
    }

    public function testQuantityLessThanOneIsRejected(): void
    {
        $product = $this->normalProduct();

        try {
            $this->validator->validate(
                $product,
                0
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_quantity_invalid',
                $exception->getTranslationKey()
            );
        }
    }

    public function testQuantityAboveStockIsRejected(): void
    {
        $product = $this->normalProduct();

        $product['stock'] = 3;
        $product['max_order'] = null;

        try {
            $this->validator->validate(
                $product,
                4
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_stock_exceeded',
                $exception->getTranslationKey()
            );
        }
    }

    public function testNormalProductRejectsBarem(): void
    {
        $product = $this->normalProduct();

        try {
            $this->validator->validate(
                $product,
                1,
                '25'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_not_supported',
                $exception->getTranslationKey()
            );
        }
    }

    public function testIncompleteBaremDataIsRejected(): void
    {
        $product = $this->tieredProduct();

        $product['max_barem'] = null;

        try {
            $this->validator->validate(
                $product,
                1,
                '25'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_data_incomplete',
                $exception->getTranslationKey()
            );
        }
    }

    public function testInvalidBaremFormatIsRejected(): void
    {
        $product = $this->tieredProduct();

        try {
            $this->validator->validate(
                $product,
                1,
                '25,01'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_invalid',
                $exception->getTranslationKey()
            );
        }
    }

    public function testBaremBelowMinimumIsRejected(): void
    {
        $product = $this->tieredProduct();

        try {
            $this->validator->validate(
                $product,
                1,
                '24.99'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_range',
                $exception->getTranslationKey()
            );

            self::assertSame(
                [
                    'min' => '25',
                    'max' => '1250',
                ],
                $exception->getParameters()
            );
        }
    }

    public function testBaremStepMismatchIsRejected(): void
    {
        $product = $this->tieredProduct();

        try {
            $this->validator->validate(
                $product,
                1,
                '25.005'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_step',
                $exception->getTranslationKey()
            );

            self::assertSame(
                [
                    'step' => '0.01',
                ],
                $exception->getParameters()
            );
        }
    }

    public function testZeroBaremStepIsRejected(): void
    {
        $product = $this->tieredProduct();

        $product['barem_step'] = '0';

        try {
            $this->validator->validate(
                $product,
                1,
                '25'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_step_invalid',
                $exception->getTranslationKey()
            );
        }
    }

    public function testInvalidApiBaremValueIsRejected(): void
    {
        $product = $this->tieredProduct();

        $product['min_barem'] = 'invalid';

        try {
            $this->validator->validate(
                $product,
                1,
                '25'
            );

            self::fail(
                'Expected OrderValidationException was not thrown.'
            );
        } catch (OrderValidationException $exception) {
            self::assertSame(
                'order_barem_api_invalid',
                $exception->getTranslationKey()
            );
        }
    }

    public function testMaximumBaremIsAccepted(): void
    {
        $product = $this->tieredProduct();

        $this->validator->validate(
            $product,
            1,
            '1250'
        );

        $this->addToAssertionCount(1);
    }
    /**
     * @return OrderProductData
     */
    private function normalProduct(): array
    {
        return [
            'stock' => 10,
            'min_order' => 1,
            'max_order' => 5,
            'pre_order' => false,
            'min_barem' => null,
            'max_barem' => null,
            'barem_step' => null,
        ];
    }
    /**
     * @return OrderProductData
     */
    private function tieredProduct(): array
    {
        return [
            'stock' => 0,
            'min_order' => 1,
            'max_order' => null,
            'pre_order' => true,
            'min_barem' => '25',
            'max_barem' => '1250',
            'barem_step' => '0.01',
        ];
    }
}
