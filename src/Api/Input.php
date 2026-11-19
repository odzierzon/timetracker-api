<?php

namespace App\Api;

use App\Service\InputErrorCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class Input
{
        
    public static function body(
        Request $request, 
        array $allowed, 
        InputErrorCollector $errorCollector
    ): array {
        try {
            $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON.', $exception);
        }

        if (!$body instanceof \stdClass) {
            throw new BadRequestHttpException('Expected a JSON object.');
        }
        $data = get_object_vars($body);
        $unknownFields = array_diff(array_keys($data), $allowed);
        if ($unknownFields) {
            $errorCollector->addError('unknownFields', implode(', ', $unknownFields)
                . '. Allowed fields are: ' . implode(', ', $allowed)
                . '. Please pay attention to upper and lower case letters.');
        }

        return $data;
    }

    public static function requiredString(array $data, string $field, int $maxLength, InputErrorCollector $errorCollector): ?string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            $errorCollector->addError($field, sprintf('must be a non-empty string of at most %d characters.', $maxLength));
            return null;
        }

        return trim($value);
    }

    public static function optionalString(array $data, string $field, int $maxLength, InputErrorCollector $errorCollector): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }
        if (!is_string($data[$field]) || mb_strlen($data[$field]) > $maxLength) {
            $errorCollector->addError($field, sprintf('must be a string of at most %d characters or null.', $maxLength));
            return null;
        }

        return $data[$field];
    }

    public static function email(array $data, string $field = 'email', ?InputErrorCollector $errorCollector = null): ?string
    {
        if ($errorCollector === null) {
            throw new \LogicException('InputErrorCollector is required for email validation.');
        }
        
        $email = strtolower(self::requiredString($data, $field, 180, $errorCollector));
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorCollector->addError($field, 'must be a valid email address.');
            return null;
        }

        return $email;
    }

    public static function password(array $data, InputErrorCollector $errorCollector): ?string
    {
        $password = $data['password'] ?? null;
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            $errorCollector->addError('password', 'must contain between 12 and 72 bytes.');
            return null;
        }

        return $password;
    }

    public static function id(array $data, string $field, InputErrorCollector $errorCollector): ?int
    {
        $id = $data[$field] ?? null;
        if (!is_int($id) || $id < 1) {
            $errorCollector->addError($field, 'must be a positive integer.');
            return null;
        }

        return $id;
    }

    public static function birthday(array $data, InputErrorCollector $errorCollector): ?\DateTimeImmutable
    {
        $value = $data['birthday'] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            $errorCollector->addError('birthday', 'must be a date in YYYY-MM-DD format or null.');
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
            $errorCollector->addError('birthday', 'must be a valid date.');
            return null;
        }

        return $date;
    }
}
