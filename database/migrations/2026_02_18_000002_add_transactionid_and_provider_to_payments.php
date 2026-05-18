<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('transaction_reference');
            }

            if (! Schema::hasColumn('payments', 'provider')) {
                $table->string('provider')->nullable()->after('method');
            }

            if (! $this->indexExists('payments', 'transaction_id')) {
                $table->index('transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'transaction_id')) {
                if ($this->indexExists('payments', 'transaction_id')) {
                    $table->dropIndex(['transaction_id']);
                }
                $table->dropColumn('transaction_id');
            }

            if (Schema::hasColumn('payments', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }

    /**
     * Check if an index exists on the given table for the column (MySQL/SQLite).
     */
    private function indexExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = DB::select('SHOW INDEX FROM '.$table.' WHERE Column_name = ?', [$column]);

            return ! empty($result);
        }
        if ($driver === 'sqlite') {
            $indexName = $table.'_'.$column.'_index';
            $result = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?", [$table, $indexName]);

            return ! empty($result);
        }

        return false;
    }
};
