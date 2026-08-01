<?php

namespace App\Contracts;

interface OtpVerifiable
{
    public function getOtpIdentifier(): string;

    public function markOtpVerified(string $channel): void;

    public function isOtpVerified(string $channel): bool;
}
