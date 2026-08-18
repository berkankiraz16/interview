<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest\Exceptions;

use RuntimeException;

/*
 * Kullanıcıdan gelen sipariş verilerinin iş kurallarına
 * uymadığı durumları API/bağlantı hatalarından ayırmak için kullanılır.
 */
final class OrderValidationException extends RuntimeException
{
    /**
     * @param array<string, int|string> $parameters
     */
    public function __construct(
        private readonly string $translationKey,
        private readonly array $parameters = []
    ) {
        parent::__construct($translationKey);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, int|string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
