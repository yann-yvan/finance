<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Indexed tags to search transactions (merchant:eneo, campaign:CMP-12, ...). Not part of any signature.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('finance_transaction_tags', static function (Blueprint $table) {
            $table->id();
            $table->string('finance_transaction_id');
            $table->string('tag', 191);
            $table->timestamp('created_at')->nullable();

            $table->unique(['finance_transaction_id', 'tag']);
            $table->index(['tag', 'finance_transaction_id']);
            $table->foreign('finance_transaction_id')->references('id')->on('finance_transactions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_transaction_tags');
    }
};
