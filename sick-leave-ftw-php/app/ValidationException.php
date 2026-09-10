<?php

declare(strict_types=1);

namespace App;

/**
 * Thrown when submitted data fails a business rule. Controllers catch it and
 * turn it into a flash message plus a redirect back to the form, so a bad
 * submission never produces a stack trace or a half-written record.
 */
final class ValidationException extends \RuntimeException
{
    /** @var array<string,string> */
    private array $fieldErrors;

    /** @param array<string,string> $fieldErrors */
    public function __construct(string $message, array $fieldErrors = [])
    {
        parent::__construct($message);
        $this->fieldErrors = $fieldErrors;
    }

    /** @return array<string,string> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
