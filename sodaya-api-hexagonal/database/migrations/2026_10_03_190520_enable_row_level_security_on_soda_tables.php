<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables whose rows belong to one soda; users is left out because the login runs before any soda is known.
     *
     * @var list<string>
     */
    private const array TABLES = ['categories', 'dishes', 'schedules', 'closures'];

    /** Limit every role, the table owner included, to the rows of the soda set for the transaction. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY {$table}_soda_isolation ON {$table}
                    USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                    WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                SQL);
        }
    }

    /** Drop the policies and turn row-level security off again. */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY {$table}_soda_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
