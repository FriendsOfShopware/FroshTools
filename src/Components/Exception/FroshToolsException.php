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
}
