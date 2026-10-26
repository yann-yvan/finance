<?php

namespace NYCorp\Finance\Integrity;

use Illuminate\Support\Facades\Schema;

/**
 * Cached schema lookups, so the library keeps working on databases where the newest migrations are not published yet.
 * Only positive answers are cached: a long-running worker picks up a table created after it started.
 */
class FinanceSchema
{
    private static array $tables = [];
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        if (!isset(self::$tables[$table]) && Schema::hasTable($table)) {
            self::$tables[$table] = true;
        }
        return self::$tables[$table] ?? false;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = "$table.$column";
        if (!isset(self::$columns[$key]) && Schema::hasColumn($table, $column)) {
            self::$columns[$key] = true;
        }
        return self::$columns[$key] ?? false;
    }

    public static function hasChain(): bool
    {
        return self::hasTable('finance_chain_entries');
    }

    public static function hasAuditLog(): bool
    {
        return self::hasTable('finance_audit_logs');
    }

    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];
    }
}
