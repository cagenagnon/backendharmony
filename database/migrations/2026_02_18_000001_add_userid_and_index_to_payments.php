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
            if (! Schema::hasColumn('payments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('reservation_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }

            if (! Schema::hasColumn('payments', 'transaction_reference')) {
                $table->string('transaction_reference')->nullable()->after('paid_at');
            }

            if (! $this->indexExists('payments', 'transaction_reference')) {
                $table->index('transaction_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if ($this->indexExists('payments', 'transaction_reference')) {
                $table->dropIndex(['transaction_reference']);
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
