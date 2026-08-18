<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Validation;

use Turkpin\InterviewTest\Exceptions\OrderValidationException;

/**
 * @phpstan-type OrderProductData array{
 *     stock?: int,
 *     min_order?: int,
 *     max_order?: int|null,
 *     pre_order?: bool,
 *     min_barem?: string|null,
 *     max_barem?: string|null,
 *     barem_step?: string|null
 * }
 */
final class OrderValidator
{
    /**
    * @param OrderProductData $product
    */
    public function validate(
        array $product,
        int $quantity,
        ?string $barem = null
    ): void {
        if ($quantity < 1) {
            throw new OrderValidationException(
                'order_quantity_invalid'
            );
        }

        $minOrder = (int) ($product['min_order'] ?? 1);

        if ($quantity < $minOrder) {
            throw new OrderValidationException(
                'order_minimum_quantity',
                [
                    'min' => $minOrder,
                ]
            );
        }

        $maxOrder = $product['max_order'] ?? null;

        /*
         * null değeri ürünün üst sipariş limiti olmadığını ifade eder.
         */
        if (
            $maxOrder !== null
            && $quantity > (int) $maxOrder
        ) {
            throw new OrderValidationException(
                'order_maximum_quantity',
                [
                    'max' => $maxOrder,
                ]
            );
        }

        $stock = (int) ($product['stock'] ?? 0);
        $preOrder = (bool) ($product['pre_order'] ?? false);

        /*
         * Normal ürünlerde stok miktarı aşılmamalıdır.
         * Ön sipariş ürünlerinde stock=0 olması geçerli olabilir.
         */
        if (!$preOrder && $quantity > $stock) {
            throw new OrderValidationException(
                'order_stock_exceeded'
            );
        }

        $this->validateBarem(
            $product,
            $barem
        );
    }
    /**
     * @param OrderProductData $product
     */
    private function validateBarem(
        array $product,
        ?string $barem
    ): void {
        $minBarem = $product['min_barem'] ?? null;
        $maxBarem = $product['max_barem'] ?? null;
        $baremStep = $product['barem_step'] ?? null;

        $hasAnyBaremData =
            $minBarem !== null
            || $maxBarem !== null
            || $baremStep !== null;

        $isTieredProduct =
            $minBarem !== null
            && $maxBarem !== null
            && $baremStep !== null;

        /*
         * API barem alanlarının yalnız bir kısmını döndürürse
         * güvenilir validation yapamayız.
         */
        if ($hasAnyBaremData && !$isTieredProduct) {
            throw new OrderValidationException(
                'order_barem_data_incomplete'
            );
        }

        // Normal ürüne barem gönderilmesini kabul etmiyoruz.
        if (!$isTieredProduct) {
            if ($barem !== null && trim($barem) !== '') {
                throw new OrderValidationException(
                    'order_barem_not_supported'
                );
            }

            return;
        }

        if ($barem === null || trim($barem) === '') {
            throw new OrderValidationException(
                'order_barem_required'
            );
        }

        $barem = trim($barem);

        if (!$this->isValidDecimal($barem)) {
            throw new OrderValidationException(
                'order_barem_invalid'
            );
        }

        $this->validateBaremRangeAndStep(
            $barem,
            (string) $minBarem,
            (string) $maxBarem,
            (string) $baremStep
        );
    }

    private function validateBaremRangeAndStep(
        string $barem,
        string $minBarem,
        string $maxBarem,
        string $baremStep
    ): void {
        foreach (
            [$minBarem, $maxBarem, $baremStep] as $value
        ) {
            if (!$this->isValidDecimal($value)) {
                throw new OrderValidationException(
                    'order_barem_api_invalid'
                );
            }
        }

        /*
         * Float kullanmadan tüm decimal değerleri aynı ölçeğe
         * getirip integer olarak karşılaştırıyoruz.
         */
        $scale = max(
            $this->decimalScale($barem),
            $this->decimalScale($minBarem),
            $this->decimalScale($maxBarem),
            $this->decimalScale($baremStep)
        );

        $baremValue = $this->decimalToInteger(
            $barem,
            $scale
        );

        $minValue = $this->decimalToInteger(
            $minBarem,
            $scale
        );

        $maxValue = $this->decimalToInteger(
            $maxBarem,
            $scale
        );

        $stepValue = $this->decimalToInteger(
            $baremStep,
            $scale
        );

        if ($stepValue <= 0) {
            throw new OrderValidationException(
                'order_barem_step_invalid'
            );
        }

        if (
            $baremValue < $minValue
            || $baremValue > $maxValue
        ) {
            throw new OrderValidationException(
                'order_barem_range',
                [
                    'min' => $minBarem,
                    'max' => $maxBarem,
                ]
            );
        }

        /*
         * Geçerli barem değeri:
         * (barem - min_barem) / barem_step tam sayı olmalıdır.
         */
        if (
            ($baremValue - $minValue) % $stepValue !== 0
        ) {
            throw new OrderValidationException(
                'order_barem_step',
                [
                    'step' => $baremStep,
                ]
            );
        }
    }

    private function isValidDecimal(string $value): bool
    {
        return preg_match(
            '/^\d+(?:\.\d+)?$/',
            trim($value)
        ) === 1;
    }

    private function decimalScale(string $value): int
    {
        $value = trim($value);

        if (!str_contains($value, '.')) {
            return 0;
        }

        $fraction = explode('.', $value, 2)[1];

        /*
         * örn 25.0100 ile 25.01 aynı sayıdır.
         * Gereksiz sondaki sıfırları ölçeğe dahil etmiyoruz.
         */
        return strlen(
            rtrim($fraction, '0')
        );
    }

    private function decimalToInteger(
        string $value,
        int $scale
    ): int {
        $parts = explode(
            '.',
            trim($value),
            2
        );

        $integerPart = $parts[0];
        $fractionPart = $parts[1] ?? '';

        $fractionPart = str_pad(
            $fractionPart,
            $scale,
            '0'
        );

        if (strlen($fractionPart) > $scale) {
            $extraDigits = substr(
                $fractionPart,
                $scale
            );

            /*
             * İstenen hassasiyetin üzerinde yalnızca sıfır varsa
               sayı aynı değeri ifade etmeye devam eder.*/

            if (trim($extraDigits, '0') !== '') {
                throw new OrderValidationException(
                    'order_decimal_precision_invalid'
                );
            }

            $fractionPart = substr(
                $fractionPart,
                0,
                $scale
            );
        }

        return (int) (
            $integerPart
            . $fractionPart
        );
    }
}
