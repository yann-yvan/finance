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
    public function up(): void
    {
        Schema::table('finance_transactions', static function (Blueprint $table) {
            $table->unsignedTinyInteger('signature_version')
                ->nullable(false)
                ->default(1)->after('checksum');
            $table->text('description')->change();
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
            $table->dropColumn('signature_version');
            $table->string('description')->change();
        });
    }
};
