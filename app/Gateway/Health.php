<?php

namespace App\Gateway;

readonly class Health
{
    public function __construct(
        public bool $failing,
        public int  $minResponseTime
    ) {
    }

    public static function fromArray(array $data): Health
    {
        return new self($data["failing"], $data["minResponseTime"]);
    }
}