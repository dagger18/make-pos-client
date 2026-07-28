<?php

namespace App\Misc\Exception;

use App\Misc\Enum\CapacityType;
use Symfony\Component\HttpFoundation\Response;

class CapacityExceededException extends \RuntimeException implements ApiExceptionInterface
{
    private array $messages;

    public function __construct(CapacityType $type, int $limit, int $current)
    {
        $message = sprintf(
            'Plan limit reached for %s: %d / %d used.',
            $type->value,
            $current,
            $limit
        );
        parent::__construct($message, Response::HTTP_FORBIDDEN);
        $this->messages = [
            'error'         => $message,
            'capacity_type' => $type->value,
            'limit'         => $limit,
            'current'       => $current,
            'code'          => Response::HTTP_FORBIDDEN,
        ];
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
