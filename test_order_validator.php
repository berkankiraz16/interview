<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Turkpin\InterviewTest\Exceptions\OrderValidationException;
use Turkpin\InterviewTest\Validation\OrderValidator;

$validator = new OrderValidator();

function runTest(
    string $name,
    callable $test
): void {
    try {
        $test();

        echo "[PASS] {$name}" . PHP_EOL;
    } catch (Throwable $exception) {
        echo "[FAIL] {$name}" . PHP_EOL;
        echo '       ' . $exception->getMessage() . PHP_EOL;
    }
}

function expectValidationError(
    callable $callback
): void {
    try {
        $callback();
    } catch (OrderValidationException) {
        return;
    }

    throw new RuntimeException(
        'Expected OrderValidationException was not thrown.'
    );
}

$normalProduct = [
    'stock' => 10,
    'min_order' => 1,
    'max_order' => 5,
    'pre_order' => false,
    'min_barem' => null,
    'max_barem' => null,
    'barem_step' => null,
];

$unlimitedProduct = [
    'stock' => 100,
    'min_order' => 1,
    'max_order' => null,
    'pre_order' => false,
    'min_barem' => null,
    'max_barem' => null,
    'barem_step' => null,
];

$outOfStockProduct = [
    'stock' => 0,
    'min_order' => 1,
    'max_order' => null,
    'pre_order' => false,
    'min_barem' => null,
    'max_barem' => null,
    'barem_step' => null,
];

$preOrderProduct = [
    'stock' => 0,
    'min_order' => 1,
    'max_order' => null,
    'pre_order' => true,
    'min_barem' => null,
    'max_barem' => null,
    'barem_step' => null,
];

$tieredProduct = [
    'stock' => 0,
    'min_order' => 1,
    'max_order' => null,
    'pre_order' => true,
    'min_barem' => '25',
    'max_barem' => '1250',
    'barem_step' => '0.01',
];


/*
 * 1 — Normal ve geçerli sipariş.
 */
runTest(
    'Normal product accepts valid quantity',
    function () use ($validator, $normalProduct): void {
        $validator->validate(
            $normalProduct,
            3
        );
    }
);


/*
 * 2 — min_order altındaki miktar reddedilmeli.
 */
runTest(
    'Quantity below minimum is rejected',
    function () use ($validator, $normalProduct): void {
        expectValidationError(
            fn () => $validator->validate(
                $normalProduct,
                0
            )
        );
    }
);


/*
 * 3 — max_order üzerindeki miktar reddedilmeli.
 */
runTest(
    'Quantity above maximum is rejected',
    function () use ($validator, $normalProduct): void {
        expectValidationError(
            fn () => $validator->validate(
                $normalProduct,
                6
            )
        );
    }
);


/*
 * 4 — max_order=null olduğunda üst sipariş sınırı olmamalı.
 */
runTest(
    'Null max order means unlimited order limit',
    function () use ($validator, $unlimitedProduct): void {
        $validator->validate(
            $unlimitedProduct,
            50
        );
    }
);


/*
 * 5 — Normal stokta olmayan ürün reddedilmeli.
 */
runTest(
    'Out-of-stock normal product is rejected',
    function () use ($validator, $outOfStockProduct): void {
        expectValidationError(
            fn () => $validator->validate(
                $outOfStockProduct,
                1
            )
        );
    }
);


/*
 * 6 — Pre-order ürün stock=0 olsa da otomatik reddedilmemeli.
 */
runTest(
    'Pre-order product may have zero stock',
    function () use ($validator, $preOrderProduct): void {
        $validator->validate(
            $preOrderProduct,
            1
        );
    }
);


/*
 * 7 — Baremli üründe barem zorunlu.
 */
runTest(
    'Tiered product requires barem',
    function () use ($validator, $tieredProduct): void {
        expectValidationError(
            fn () => $validator->validate(
                $tieredProduct,
                1
            )
        );
    }
);


/*
 * 8 — Minimum barem geçerli olmalı.
 */
runTest(
    'Minimum barem is accepted',
    function () use ($validator, $tieredProduct): void {
        $validator->validate(
            $tieredProduct,
            1,
            '25'
        );
    }
);


/*
 * 9 — Decimal step'e uyan barem geçerli.
 */
runTest(
    'Valid decimal barem step is accepted',
    function () use ($validator, $tieredProduct): void {
        $validator->validate(
            $tieredProduct,
            1,
            '25.03'
        );
    }
);


/*
 * 10 — Maksimum barem üzeri reddedilmeli.
 */
runTest(
    'Barem above maximum is rejected',
    function () use ($validator, $tieredProduct): void {
        expectValidationError(
            fn () => $validator->validate(
                $tieredProduct,
                1,
                '1250.01'
            )
        );
    }
);