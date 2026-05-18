<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'paid' to the reservations.status enum
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending','confirmed','cancelled','completed','paid') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // First update any 'paid' rows back to 'confirmed' to avoid data loss
        DB::statement("UPDATE reservations SET status = 'confirmed' WHERE status = 'paid'");
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending'");
    }
};
