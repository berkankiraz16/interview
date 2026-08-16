<?php

use Turkpin\InterviewTest\Services\TurkpinApiClient;

class Home
{
    public function index()
    {
        global $smarty;

        $games = [];
        $products = [];
        $error = null;

        // URL'den gelen oyun kodunu alıyoruz.
        // ?game[]=1 veya /?game=999999 gibi beklenmeyen array girişlerine karşı string kontrolü yapıyoruz.
        $selectedGame = $_GET['game'] ?? '';

        if (!is_string($selectedGame)) {
            $selectedGame = '';
        }

        $selectedGame = trim($selectedGame);

        try {
            $apiClient = TurkpinApiClient::fromEnvironment();

            // Geçerli oyun listemizi API'den alıyoruz.
            $games = $apiClient->getGames();

            if ($selectedGame !== '') {
                // URL'den gelen oyun koduna doğrudan güvenmiyoruz.
                // API'nin gerçekten döndürdüğü oyun kodlarıyla karşılaştırıyoruz.
                $validGameCodes = array_column(
                    $games,
                    'id'
                );

                if (!in_array(
                    $selectedGame,
                    $validGameCodes,
                    true
                )) {
                    throw new RuntimeException(
                        'Invalid game selection.'
                    );
                }

                // Seçim geçerliyse yalnızca o oyunun ürünlerini getiriyoruz.
                $products = $apiClient->getProducts(
                    $selectedGame
                );
            }
        } catch (RuntimeException $exception) {
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
            'error',
            $error
        );

        $smarty->assign(
            'template',
            'home.html'
        );
    }
}