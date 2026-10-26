<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Append-only audit log of state changes and balance recalculations, each row signed.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('finance_audit_logs', static function (Blueprint $table) {
            $table->id(); // A gap reveals a deleted row
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('context')->nullable(); // console command, http route, ...
            $table->string('hash');
            $table->timestamp('created_at')->index();

            $table->index(['subject_type', 'subject_id']);
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("
                CREATE TRIGGER prevent_update_finance_audit_logs
                BEFORE UPDATE ON finance_audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finance audit logs are append-only';
                END
            ");
            DB::unprepared("
                CREATE TRIGGER prevent_delete_finance_audit_logs
                BEFORE DELETE ON finance_audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finance audit logs are append-only';
                END
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER prevent_update_finance_audit_logs
                BEFORE UPDATE ON finance_audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Finance audit logs are append-only');
                END
            ");
            DB::unprepared("
                CREATE TRIGGER prevent_delete_finance_audit_logs
                BEFORE DELETE ON finance_audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Finance audit logs are append-only');
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
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_update_finance_audit_logs');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_delete_finance_audit_logs');
        Schema::dropIfExists('finance_audit_logs');
    }
};
