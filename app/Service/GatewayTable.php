<?php

namespace App\Service;

use Swoole\Table;

class GatewayTable
{
    const string COLUMN_ERRORS = 'errors';
    const string COLUMN_NAME = 'name';
    public static Table $table;

    public static function init(): void
    {
        self::$table = new Table(1);
        self::$table->column(self::COLUMN_NAME, Table::TYPE_STRING, 600);
        self::$table->column(self::COLUMN_ERRORS, Table::TYPE_INT, 1);

        self::$table->create();

        self::$table->set('provider', [self::COLUMN_NAME => 'default', self::COLUMN_ERRORS => 0]);
    }

    public static function get(): Table
    {
        return self::$table;
    }

    public static function getProvider(): string
    {
        return self::$table->get('provider')[self::COLUMN_NAME] ?? 'default';
    }

    public static function setProvider(string $gateway): void {
        self::$table->set('provider', [self::COLUMN_NAME => $gateway]);
    }

    public static function setError(string $name): int
    {
        $providers = self::$table->get('provider');

        if ($providers[self::COLUMN_NAME] != $name) {
            return 0;
        }

        $countErrors = $providers[self::COLUMN_ERRORS]++;
        self::$table->set('provider', [self::COLUMN_NAME => $providers[self::COLUMN_NAME], self::COLUMN_ERRORS => $countErrors]);

        return $countErrors;
    }
}
