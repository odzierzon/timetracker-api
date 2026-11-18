<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InputErrorCollector
{
    private array $errors = [];

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function throwIfErrors(): void
    {
        if ($this->hasErrors()) {
            $messages = [];
            foreach ($this->errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $messages[] = sprintf('%s: %s', $field, $error);
                }
            }
            throw new UnprocessableEntityHttpException(implode(" | ", $messages));
        }
    }
}