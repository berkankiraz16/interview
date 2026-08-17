<?php

declare(strict_types=1);

use Turkpin\InterviewTest\Exceptions\OrderValidationException;
use Turkpin\InterviewTest\Security\OrderSubmissionTokenManager;
use Turkpin\InterviewTest\Services\TurkpinApiClient;
use Turkpin\InterviewTest\Validation\OrderValidator;

class Home
{
    public function index(): void
    {
        global $smarty;

        $games = [];
        $products = [];
        $error = null;

        /*
         * POST /order işleminden sonra bırakılan tek kullanımlık
         * sonucu session'dan alıyoruz.
         */
        $orderFlash = $_SESSION['order_flash'] ?? null;

        unset($_SESSION['order_flash']);

        /*
         * Her görüntülenen sipariş formu için tahmin edilemez,
         * tek kullanımlık bir token oluşturuyoruz.
         */
        $tokenManager = new OrderSubmissionTokenManager();
        $orderToken = $tokenManager->issue();

        /*
         * URL örneği:
         * /?game=1
         *
         * ?game[]=1 gibi array inputlarını kabul etmiyoruz.
         */
        $selectedGame = $_GET['game'] ?? '';

        if (!is_string($selectedGame)) {
            $selectedGame = '';
        }

        $selectedGame = trim($selectedGame);

        try {
            $apiClient = TurkpinApiClient::fromEnvironment();

            /*
             * Önce geçerli oyun listesini Turkpin'den alıyoruz.
             */
            $games = $apiClient->getGames();

            if ($selectedGame !== '') {
                /*
                 * Kullanıcının URL'de gönderdiği oyun koduna
                 * doğrudan güvenmiyoruz.
                 */
                $validGameCodes = array_column(
                    $games,
                    'id'
                );

                if (
                    !in_array(
                        $selectedGame,
                        $validGameCodes,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Invalid game selection.'
                    );
                }

                /*
                 * Oyun gerçekten mevcutsa yalnızca o oyunun
                 * ürünlerini getiriyoruz.
                 */
                $products = $apiClient->getProducts(
                    $selectedGame
                );
            }
        } catch (RuntimeException $exception) {
            /*
             * Geliştirme aşamasında teknik hata mesajını gösteriyoruz.
             *
             * Final aşamasında:
             * - teknik detay log'a yazılacak
             * - kullanıcıya anlaşılır genel mesaj gösterilecek
             */
            $error = $exception->getMessage();
        }

        $smarty->assign(
            'games',
            $games
        );

        $smarty->assign(
            'products',
            $products
        );

        $smarty->assign(
            'selectedGame',
            $selectedGame
        );

        $smarty->assign(
            'orderToken',
            $orderToken
        );

        $smarty->assign(
            'orderFlash',
            $orderFlash
        );

        $smarty->assign(
            'error',
            $error
        );

        $smarty->assign(
            'template',
            'home.html'
        );
    }

    public function order(): void
    {
        global $lang;

        /*
         * Bu method yalnızca POST route üzerinden çalışmalıdır.
         */
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            header('Location: /');

            exit;
        }

        $gameCode = $_POST['game_code'] ?? '';
        $productCode = $_POST['product_code'] ?? '';
        $quantityRaw = $_POST['quantity'] ?? '';
        $barem = $_POST['barem'] ?? null;
        $orderToken = $_POST['order_token'] ?? '';

        /*
         * HTTP inputları beklenen string yapısında değilse
         * uygulama mantığına sokmuyoruz.
         */
        if (!is_string($gameCode)) {
            $gameCode = '';
        }

        if (!is_string($productCode)) {
            $productCode = '';
        }

        if (!is_string($quantityRaw)) {
            $quantityRaw = '';
        }

        if (!is_string($orderToken)) {
            $orderToken = '';
        }

        if (
            $barem !== null
            && !is_string($barem)
        ) {
            $barem = null;
        }

        $gameCode = trim($gameCode);
        $productCode = trim($productCode);
        $quantityRaw = trim($quantityRaw);
        $orderToken = trim($orderToken);

        $barem = $barem !== null
            ? trim($barem)
            : null;

        /*
         * Aynı formun ikinci kez kullanılmasını engellemek için
         * token, API'de herhangi bir side-effect oluşmadan önce tüketiliyor.
         */
        $tokenManager =
            new OrderSubmissionTokenManager();

        if (!$tokenManager->consume($orderToken)) {
            $_SESSION['order_flash'] = [
                'success' => false,
                'message' => $lang['order_form_expired'],
            ];

            $this->redirectToGame(
                $gameCode
            );
        }

        /*
         * PHP'nin gevşek integer dönüşümlerine güvenmiyoruz.
         *
         * "3abc", "1.5", "-1" gibi değerler kabul edilmez.
         */
        $quantity = filter_var(
            $quantityRaw,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($quantity === false) {
            $_SESSION['order_flash'] = [
                'success' => false,
                'message' => $lang['order_quantity_invalid'],
            ];

            $this->redirectToGame(
                $gameCode
            );
        }

        try {
            $apiClient =
                TurkpinApiClient::fromEnvironment();

            /*
             * Kullanıcıdan gelen game_code değerine güvenmiyoruz.
             * Gerçek oyun listesini API'den tekrar alıyoruz.
             */
            $games = $apiClient->getGames();

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
             * product_code değerinin gerçekten seçilen
             * oyuna ait olduğunu API'den doğruluyoruz.
             */
            $products = $apiClient->getProducts(
                $gameCode
            );

            $selectedProduct = null;

            foreach ($products as $product) {
                if (
                    isset($product['id'])
                    && (string) $product['id']
                        === $productCode
                ) {
                    $selectedProduct = $product;

                    break;
                }
            }

            if ($selectedProduct === null) {
                throw new OrderValidationException(
                    'invalid_product_selection'
                );
            }

            /*
             * Minimum / maksimum sipariş,
             * stok, pre-order ve barem kuralları
             * ayrı validator içerisinde kontrol ediliyor.
             */
            $validator = new OrderValidator();

            $validator->validate(
                $selectedProduct,
                $quantity,
                $barem
            );

            /*
             * Tüm server-side kontroller başarılı.
             *
             * Development ortamında canlı sipariş kapalıysa
             * Turkpin'e write request göndermiyoruz.
             */
            if (!$apiClient->isOrderSubmissionEnabled()) {
                $_SESSION['order_flash'] = [
                    'success' => false,
                    'message' => $lang['order_submission_disabled'],
                ];

                $this->redirectToGame(
                    $gameCode
                );
            }

            /*
             * pre_order değerini kullanıcıdan almıyoruz.
             * Turkpin'den yeniden doğrulanmış ürün bilgisini kullanıyoruz.
             */
            $orderResult = $apiClient->createOrder(
                $gameCode,
                $productCode,
                $quantity,
                null,
                (bool) ($selectedProduct['pre_order'] ?? false),
                $barem
            );

            /*
             * External API response'unu doğrudan template'e taşımıyoruz.
             * API client'ın normalize ettiği sonucu kullanıyoruz.
             */
            $_SESSION['order_flash'] = [
                'success' => true,
                'message' => $lang['order_created'],

                'order_number' =>
                    $orderResult['order_number'],

                'amount' =>
                    $orderResult['amount'],

                'epins' =>
                    $orderResult['epins'],
            ];
        } catch (
            OrderValidationException $exception
        ) {
            /*
             * Kullanıcının düzeltebileceği validation hataları.
             */
            $_SESSION['order_flash'] = [
                'success' => false,
                'message' => $this->translate(
                    $lang,
                    $exception->getTranslationKey(),
                    $exception->getParameters()
                    ),
            ];
        } catch (RuntimeException $exception) {
            /*
             * API / network / configuration gibi teknik hatalar.
             *
             * Finalde gerçek hata log'a yazılacak,
             * kullanıcıya yalnızca genel mesaj gösterilecek.
             */
            $_SESSION['order_flash'] = [
                'success' => false,
                'message' => $lang['order_service_unavailable'],
            ];
        }

        $this->redirectToGame(
            $gameCode
        );
    }

    private function translate(
        array $translations,
        string $key,
        array $parameters = []
    ): string {
        $message = $translations[$key] ?? $key;
    
        foreach ($parameters as $name => $value) {
            $message = str_replace(
                '{' . $name . '}',
                (string) $value,
                $message
            );
        }
    
        return $message;
    }
    
    private function redirectToGame(
        string $gameCode
    ): never {
        $location = '/';

        if ($gameCode !== '') {
            $location .= '?game='
                . rawurlencode($gameCode);
        }

        /*
         * POST işleminden sonra 303 ile GET'e yönlendiriyoruz.
         * Böylece F5 sipariş POST'unu tekrar göndermez.
         */
        header(
            'Location: ' . $location,
            true,
            303
        );

        exit;
    }
}