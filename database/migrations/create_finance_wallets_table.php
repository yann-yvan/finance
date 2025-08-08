<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NYCorp\Finance\Models\FinanceTransaction;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finance_wallets', static function (Blueprint $table) {
            $table->string('id')->primary();  // Primary key for the wallet
            $table->string('owner_id');  // ID of the owner (user, company, etc.)
            $table->string('owner_type');  // Polymorphic type for the owner (e.g., 'User' or 'Company')
            $table->string('checksum');  // Checksum to verify the integrity of the row

            // Foreign key to the FinanceTransaction table (unique since one wallet can have only one transaction)
            $table->foreignIdFor(FinanceTransaction::class)->unique()->constrained()->onDelete('cascade');

            // Self-referencing foreign key to parent wallet (nullable for cases without a parent wallet)
            $table->string('transfer_from_wallet_id')->nullable();
            $table->foreign('transfer_from_wallet_id')->references('id')->on('finance_wallets')->nullOnDelete();

            $table->timestamps();
        });

        DB::unprepared("
            CREATE TRIGGER prevent_update_finance_wallets
            BEFORE UPDATE ON finance_wallets
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Updates are not allowed on this row';
            END;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop the trigger if the migration is rolled back
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_update_finance_wallets');

        Schema::dropIfExists('finance_wallets');
    }
};
