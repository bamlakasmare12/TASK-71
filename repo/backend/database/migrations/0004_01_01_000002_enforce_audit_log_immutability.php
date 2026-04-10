<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL trigger to block UPDATE on audit_logs at the DB level
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_audit_log_update()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs table is append-only. UPDATE operations are prohibited.';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_audit_log_no_update ON audit_logs;
            CREATE TRIGGER trg_audit_log_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_log_update();
        ");

        // PostgreSQL trigger to block DELETE on audit_logs at the DB level
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_audit_log_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs table is append-only. DELETE operations are prohibited.';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_audit_log_no_delete ON audit_logs;
            CREATE TRIGGER trg_audit_log_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_log_delete();
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_log_no_update ON audit_logs;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_log_no_delete ON audit_logs;');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_update();');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_delete();');
    }
};
