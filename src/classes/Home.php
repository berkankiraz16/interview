<?php

use RuntimeException;
use Turkpin\InterviewTest\Services\TurkpinApiClient;

class Home
{
    public function index()
    {
        global $smarty;
#veri setlerini tanımlıyoruz, bunları api bağlantısıntan önce tanımlıyoruz ki hataları, oyunları ve ürünleri gösterebilsin
        $games = [];
        $products = [];
        $error = null;

        try {
            $apiClient = TurkpinApiClient::fromEnvironment();

            $games = $apiClient->getGames();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        $smarty->assign('games', $games);
        $smarty->assign('products', $products);
        $smarty->assign('error', $error);

        $smarty->assign('template', 'home.html');
    }
}