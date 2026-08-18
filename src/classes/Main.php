<?php

declare(strict_types=1);

require_once __DIR__ . '/Home.php';

class Main
{
    public \Bramus\Router\Router $router;

    public function __construct()
    {
        global $lang, $smarty;

        /*
         * Yalnızca uygulamanın gerçekten desteklediği
         * dil kodlarının kullanılmasına izin veriyoruz.
         */
        $supportedLanguages = ['tr', 'en'];

        /*
         * Session henüz lang içermiyorsa "tr" kullanıyoruz.
         * ?? kullanımı undefined array key warning'ini önler.
         */
        $selectedLanguage = $_SESSION['lang'] ?? 'tr';

        if (
            !is_string($selectedLanguage)
            || !in_array(
                $selectedLanguage,
                $supportedLanguages,
                true
            )
        ) {
            $selectedLanguage = 'tr';
        }

        /*
         * Query string üzerinden gelen dil değerine
         * doğrudan güvenmiyoruz.
         */
        if (
            isset($_GET['lang'])
            && is_string($_GET['lang'])
            && in_array(
                $_GET['lang'],
                $supportedLanguages,
                true
            )
        ) {
            $selectedLanguage = $_GET['lang'];

            $_SESSION['lang'] = $selectedLanguage;
        }

        /*
         * tr.php / en.php içerisinde $lang çeviri dizisi oluşturuluyor.
         */
        require_once __DIR__
            . "/../languages/{$selectedLanguage}.php";

        $smarty = new Smarty\Smarty();

        $this->router =
            new \Bramus\Router\Router();

        $smarty->setTemplateDir(
            __DIR__ . '/../templates'
        );

        $smartyCompileDir =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'turkpin-smarty';

        if (
            !is_dir($smartyCompileDir)
            && !mkdir(
                $smartyCompileDir,
                0775,
                true
            )
            && !is_dir($smartyCompileDir)
        ) {
            throw new RuntimeException(
                'Unable to create Smarty compile directory.'
            );
        }

        $smarty->setCompileDir(
            $smartyCompileDir
        );

        $smarty->assign(
            'LANG',
            $lang
        );

        $smarty->assign(
            'langs',
            [
                'tr' => 'Türkçe',
                'en' => 'English',
            ]
        );
    }

    public function run(): void
    {
        /** @var \Smarty\Smarty $smarty */
        global $smarty;

        /*
         * Sayfayı ve oyun/ürün listesini göstermek için GET.
         */
        $this->router->get(
            '/',
            function () {
                $home = new Home();

                $home->index();
            }
        );

        /*
         * Sipariş oluşturmak state-changing bir işlem olduğu için
         * ayrı bir POST route üzerinden çalıştırıyoruz.
         */
        $this->router->post(
            '/order',
            function () {
                $home = new Home();

                $home->order();
            }
        );

        $this->router->run();

        $smarty->display('index.html');
    }
}
