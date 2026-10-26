<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Public, translatable description: a translation key and its parameters (never the final text).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('finance_transactions', static function (Blueprint $table) {
            $table->longText('display')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('finance_transactions', static function (Blueprint $table) {
            $table->dropColumn('display');
        });
    }
};
