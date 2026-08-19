<?php

declare(strict_types=1);

/*
 * Tarayıcıdan gelen, PHP tarafından daha önce oluşturulmamış
 * session ID'lerini kabul etmiyoruz.
 */
ini_set(
    'session.use_strict_mode',
    '1'
);

/*
 * Session ID yalnızca cookie üzerinden taşınabilir.
 * URL / GET / POST üzerinden session ID kabul etmiyoruz.
 */
ini_set(
    'session.use_only_cookies',
    '1'
);

/*
 * Local development HTTP üzerinde çalışmaya devam edebilmek için
 * Secure flag'i mevcut bağlantının HTTPS olup olmadığına göre belirliyoruz.
 */
$httpsValue = $_SERVER['HTTPS'] ?? '';

$isHttps = is_string($httpsValue)
    && $httpsValue !== ''
    && strtolower($httpsValue) !== 'off';

session_set_cookie_params(
    [
        /*
         * Browser kapatıldığında session cookie'nin kalıcı
         * depoda tutulmamasını sağlıyoruz.
         */
        'lifetime' => 0,

        /*
         * Cookie tüm uygulama path'lerinde kullanılabilir.
         */
        'path' => '/',

        /*
         * HTTPS bağlantısında session cookie yalnızca
         * güvenli bağlantılar üzerinden gönderilir.
         */
        'secure' => $isHttps,

        /*
         * JavaScript'in session cookie'ye erişmesini engeller.
         */
        'httponly' => true,

        /*
         * Cross-site request'lerde session cookie gönderimini
         * sınırlandırarak CSRF riskini azaltan ek bir katmandır.
         */
        'samesite' => 'Lax',
    ]
);

session_start();

require_once 'vendor/autoload.php';
require_once 'src/classes/Main.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$main = new Main();
$main->run();
