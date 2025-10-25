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
    public function up(): void
    {
        Schema::create('finance_ledgers', static function (Blueprint $table) {
            $table->string('id')->primary();  // Primary key for the wallet
            $table->string('name');  // NAME of the wallet (user, company, etc.)
            $table->string('owner_id');  // ID of the owner (user, company, etc.)
            $table->string('owner_type');  // Polymorphic type for the owner (e.g., 'User' or 'Company')
            $table->string('checksum');  // Checksum to verify the integrity of the row
            $table->softDeletes();
            $table->timestamps();
        });

        DB::unprepared("
            CREATE TRIGGER prevent_owner_update
            BEFORE UPDATE ON finance_ledgers
            FOR EACH ROW
            BEGIN
                IF NEW.owner_id != OLD.owner_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Updating the owner is not allowed';
                END IF;
                IF NEW.owner_type != OLD.owner_type THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Updating the owner is not allowed';
                END IF;
            END
        ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_owner_update');
        Schema::dropIfExists('finance_ledgers');
    }
};
