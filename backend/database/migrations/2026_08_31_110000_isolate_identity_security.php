<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'admins', 'super_admins'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->collation('utf8mb4_bin')->unique();
                $table->unsignedBigInteger('auth_version')->default(1);
            });
            DB::table($name)->orderBy('id')->chunkById(100, function ($rows) use ($name) {
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
                }
            });
            Schema::table($name, fn (Blueprint $table) => $table->uuid('public_id')->nullable(false)->collation('utf8mb4_bin')->change());
        }
        Schema::table('account_sessions', fn (Blueprint $table) => $table->dateTime('revoked_at', 6)->nullable()->index());
        foreach (['admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->dateTime('created_at', 6)->nullable();
            });
        }
        Schema::create('identity_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('realm', 40)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('action', 80)->collation('utf8mb4_bin');
            $table->string('reference', 191)->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['realm', 'account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_audit_events');
        foreach (['admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('account_sessions', fn (Blueprint $table) => $table->dropColumn('revoked_at'));
        foreach (['users', 'admins', 'super_admins'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['public_id', 'auth_version']));
        }
    }
};
