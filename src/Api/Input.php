<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class Input
{
    public static function body(Request $request, array $allowed): array
    {
        try {
            $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON.', $exception);
        }

        if (!$body instanceof \stdClass) {
            throw new BadRequestHttpException('Expected a JSON object.');
        }
        $data = get_object_vars($body);
        if (array_diff(array_keys($data), $allowed)) {
            throw new UnprocessableEntityHttpException('Unknown field.');
        }

        return $data;
    }

    public static function requiredString(array $data, string $field, int $maxLength): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new UnprocessableEntityHttpException(sprintf('%s must be a non-empty string of at most %d characters.', $field, $maxLength));
        }

        return trim($value);
    }

    public static function optionalString(array $data, string $field, int $maxLength): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }
        if (!is_string($data[$field]) || mb_strlen($data[$field]) > $maxLength) {
            throw new UnprocessableEntityHttpException(sprintf('%s must be a string of at most %d characters or null.', $field, $maxLength));
        }

        return $data[$field];
    }

    public static function email(array $data, string $field = 'email'): string
    {
        $email = strtolower(self::requiredString($data, $field, 180));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException('Invalid email.');
        }

        return $email;
    }

    public static function password(array $data): string
    {
        $password = $data['password'] ?? null;
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            throw new UnprocessableEntityHttpException('Password must contain between 12 and 72 bytes.');
        }

        return $password;
    }

    public static function id(array $data, string $field): int
    {
        $id = $data[$field] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new UnprocessableEntityHttpException(sprintf('%s must be a positive integer.', $field));
        }

        return $id;
    }

    public static function birthday(array $data): ?\DateTimeImmutable
    {
        $value = $data['birthday'] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new UnprocessableEntityHttpException('birthday must be a date in YYYY-MM-DD format or null.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
            throw new UnprocessableEntityHttpException('birthday must be a valid date.');
        }

        return $date;
    }
}
