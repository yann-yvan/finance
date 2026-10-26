<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('finance_transactions', static function (Blueprint $table) {
            $table->unsignedTinyInteger('signature_version')
                ->nullable(false)
                ->default(1)->after('checksum');
            $table->text('description')->change();
        });

        // SQLite rebuilds the table on change(), which drops its triggers: put them back
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS before_finance_transaction_update
                BEFORE UPDATE ON finance_transactions
                FOR EACH ROW WHEN OLD.is_locked = 1
                BEGIN
                    SELECT RAISE(ABORT, 'This transaction is locked and cannot be modified.');
                END
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS prevent_start_signature_update
                BEFORE UPDATE ON finance_transactions
                FOR EACH ROW WHEN NEW.start_signature != OLD.start_signature
                BEGIN
                    SELECT RAISE(ABORT, 'Updating the start_signature is not allowed');
                END
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('finance_transactions', static function (Blueprint $table) {
            $table->dropColumn('signature_version');
            $table->string('description')->change();
        });
    }
};
