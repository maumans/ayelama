<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = DB::table('users')->select('id', 'role')->whereNotNull('role')->get();

        $inserts = $rows->map(fn ($u) => [
            'user_id'    => $u->id,
            'role'       => $u->role,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($inserts) {
            DB::table('role_user')->insertOrIgnore($inserts);
        }
    }

    public function down(): void
    {
        DB::table('role_user')->truncate();
    }
};
