<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Append-only hash chain: one entry per finalized transaction, linked per owner.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('finance_chain_entries', static function (Blueprint $table) {
            $table->id(); // Global sequence: a gap reveals a deleted entry
            $table->string('owner_type');
            $table->string('owner_id');
            $table->unsignedBigInteger('owner_seq'); // Position in the owner's chain
            $table->string('finance_transaction_id')->unique();
            $table->decimal('amount', 13, 5);
            $table->string('currency');
            $table->string('state');
            $table->string('transaction_checksum')->nullable();
            $table->string('previous_hash')->nullable(); // Null only for the first entry of an owner
            $table->string('hash');
            $table->timestamp('created_at');

            $table->unique(['owner_type', 'owner_id', 'owner_seq']);
            // No cascade: a transaction sealed in the chain cannot be deleted
            $table->foreign('finance_transaction_id')->references('id')->on('finance_transactions')->restrictOnDelete();
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("
                CREATE TRIGGER prevent_update_finance_chain_entries
                BEFORE UPDATE ON finance_chain_entries
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finance chain entries are append-only';
                END
            ");
            DB::unprepared("
                CREATE TRIGGER prevent_delete_finance_chain_entries
                BEFORE DELETE ON finance_chain_entries
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finance chain entries are append-only';
                END
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER prevent_update_finance_chain_entries
                BEFORE UPDATE ON finance_chain_entries
                BEGIN
                    SELECT RAISE(ABORT, 'Finance chain entries are append-only');
                END
            ");
            DB::unprepared("
                CREATE TRIGGER prevent_delete_finance_chain_entries
                BEFORE DELETE ON finance_chain_entries
                BEGIN
                    SELECT RAISE(ABORT, 'Finance chain entries are append-only');
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
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_update_finance_chain_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_delete_finance_chain_entries');
        Schema::dropIfExists('finance_chain_entries');
    }
};
