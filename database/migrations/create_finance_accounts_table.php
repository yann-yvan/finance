<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finance_accounts', static function (Blueprint $table) {
            $table->id();
            $table->decimal('credibility',13,5)->index();
            $table->decimal('threshold', 13, 5)->nullable();
            $table->string('currency')->index();
            $table->dateTime('last_verification_at')->index();
            $table->boolean('is_account_active')->default(true)->index();
            $table->longText('account_logs');

            $table->string("owner_id")->index();
            $table->string("owner_type")->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('finance_accounts');
    }
};
