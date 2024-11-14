<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NYCorp\Finance\Models\FinanceProvider;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finance_providers', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("assigned_id")->unique();
            $table->string("logo")->nullable();
            $table->string("color")->nullable();
            $table->boolean("is_public")->default(true);
            $table->boolean("is_available")->default(true);
            $table->boolean("is_deposit_available")->default(true);
            $table->boolean("is_withdrawal_available")->default(false);
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
        Schema::dropIfExists('finance_providers');
    }
};
