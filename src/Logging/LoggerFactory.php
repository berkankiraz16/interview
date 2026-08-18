<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Logging;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FallbackGroupHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        $logDirectory =
            dirname(__DIR__, 2) . '/var/log';

        $logger = new Logger('turkpin');

        /*
         * Dosya log dizini oluşturulamıyorsa logging yüzünden
         * asıl uygulama akışını bozmak yerine PHP error_log'a düşüyoruz.
         */
        if (
            !is_dir($logDirectory)
            && !mkdir($logDirectory, 0775, true)
            && !is_dir($logDirectory)
        ) {
            $logger->pushHandler(
                new ErrorLogHandler(
                    ErrorLogHandler::OPERATING_SYSTEM,
                    Level::Error
                )
            );

            return $logger;
        }

        /*
         * Normalde var/log/app.log kullanılır.
         * Dosyaya yazma daha sonra başarısız olursa
         * PHP error_log fallback olarak devreye girer.
         */
        $logger->pushHandler(
            new FallbackGroupHandler(
                [
                    new StreamHandler(
                        $logDirectory . '/app.log',
                        Level::Error
                    ),
                    new ErrorLogHandler(
                        ErrorLogHandler::OPERATING_SYSTEM,
                        Level::Error
                    ),
                ]
            )
        );

        return $logger;
    }
}
