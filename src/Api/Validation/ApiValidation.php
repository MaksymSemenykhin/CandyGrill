<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Api\ApiHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ApiValidation
{
    private static ?ValidatorInterface $validator = null;

    public static function validator(): ValidatorInterface
    {
        return self::$validator ??= Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @return array{code: string, message: string}
     */
    public static function errorPayloadFromViolation(ConstraintViolationInterface $violation): array
    {
        $payload = $violation->getConstraint()?->payload ?? null;
        $code = \is_array($payload) && isset($payload['api_error']) && \is_string($payload['api_error'])
            ? $payload['api_error']
            : null;
        if ($code === null) {
            $code = $violation->getCode();
        }
        if ($code === null || $code === '') {
            $code = 'unknown_command';
        }

        return ['code' => $code, 'message' => (string) $violation->getMessage()];
    }

    /**
     * @throws ApiHttpException
     */
    public static function throwUnlessValid(ConstraintViolationListInterface $violations): void
    {
        if ($violations->count() === 0) {
            return;
        }

        $payload = self::errorPayloadFromViolation($violations[0]);
        throw new ApiHttpException(400, $payload['code'], $payload['message']);
    }
}
