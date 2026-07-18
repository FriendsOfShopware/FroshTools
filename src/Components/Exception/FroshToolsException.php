<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Exception;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class FroshToolsException extends HttpException
{
    public static function elasticsearchDisabled(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            'FROSH_TOOLS__ELASTICSEARCH_DISABLED',
            'Elasticsearch is not enabled',
        );
    }

    public static function cannotClearCache(string $errorOutput): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FROSH_TOOLS__CANNOT_CLEAR_CACHE',
            'Cannot clear cache: {{ output }}',
            ['output' => $errorOutput],
        );
    }

    public static function composerLockMissing(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            'FROSH_TOOLS__COMPOSER_LOCK_MISSING',
            'composer.lock was not found or is not readable in the project root',
        );
    }

    public static function composerLockInvalid(string $reason): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'FROSH_TOOLS__COMPOSER_LOCK_INVALID',
            'composer.lock could not be parsed: {{ reason }}',
            ['reason' => $reason],
        );
    }
}
