<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finance_transactions', static function (Blueprint $table) {
            $table->string('id')->primary(); // Primary key for the transaction
            $table->decimal('amount', 13, 5)->index(); // Amount of the transaction with high precision
            $table->string('currency')->index(); // Amount of the transaction with high precision
            $table->timestamp('verify_at')->nullable()->index(); // Timestamp for when the transaction was verified
            $table->longText('start_log'); // Log details of when the transaction started
            $table->longText('end_log')->nullable(); // Log details of when the transaction ended (nullable)
            $table->string('external_id')->nullable(); // External system reference ID
            $table->longText('start_signature'); // Signature/verification data for transaction start
            $table->string('description'); // Description of the transaction
            $table->boolean('is_locked')->default(false); // Boolean to lock the transaction
            $table->string('checksum')->nullable(); // Checksum to verify row integrity
            $table->enum('state', FinanceTransaction::getStates())->index(); // State of the transaction (enum values from model)
            $table->foreignIdFor(FinanceProvider::class)->index()->constrained(); // Foreign key to finance providers
            $table->timestamps();
        });

        DB::unprepared("
            CREATE TRIGGER before_finance_transaction_update
            BEFORE UPDATE ON finance_transactions
            FOR EACH ROW
            BEGIN
                -- Prevent updates if the transaction is locked
                IF OLD.is_locked = TRUE THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'This transaction is locked and cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER prevent_start_signature_update
            BEFORE UPDATE ON finance_transactions
            FOR EACH ROW
            BEGIN
                IF NEW.start_signature != OLD.start_signature THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Updating the start_signature is not allowed';
                END IF;
            END
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
        DB::unprepared('DROP TRIGGER IF EXISTS before_finance_transaction_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_start_signature_update');

        Schema::dropIfExists('finance_transactions');
    }
};
