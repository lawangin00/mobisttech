<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION = 'shop.documents.send';

    private const TEMPLATES = [
        'invoice_whatsapp' => ['invoice', 'whatsapp', 'message', 'Invoice {{invoice_number}} from {{business_name}}. Total PKR {{total_amount}}. Please attach the generated PDF before sending.'],
        'warranty_whatsapp' => ['warranty', 'whatsapp', 'message', 'Warranty Claim Receipt {{claim_number}} from {{business_name}}. Please attach the generated PDF before sending.'],
        'invoice_email_subject' => ['invoice', 'email', 'subject', 'Sales Invoice {{invoice_number}} - {{business_name}}'],
        'invoice_email_body' => ['invoice', 'email', 'body', "Dear {{customer_name}},\n\nThank you for your purchase from {{business_name}}.\nPlease find your Sales Invoice {{invoice_number}} attached.\nTotal Amount: PKR {{total_amount}}\n\nRegards,\n{{business_name}}"],
        'warranty_email_subject' => ['warranty', 'email', 'subject', 'Warranty Claim Receipt {{claim_number}} - {{business_name}}'],
        'warranty_email_body' => ['warranty', 'email', 'body', "Dear {{customer_name}},\n\nPlease find your Warranty Claim Receipt {{claim_number}} attached.\n\nRegards,\n{{business_name}}"],
    ];

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_email', 255)->nullable()->after('customer_phone');
        });

        Schema::create('document_template_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('template_key', 80)->collation('utf8mb4_bin');
            $table->enum('document_type', ['invoice', 'warranty']);
            $table->enum('channel', ['email', 'whatsapp']);
            $table->enum('template_part', ['subject', 'body', 'message']);
            $table->unsignedInteger('version');
            $table->longText('template_text');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->unique(['template_key', 'version'], 'mt31_template_key_version');
            $table->index(['document_type', 'channel', 'template_key'], 'mt31_template_lookup');
        });

        Schema::create('document_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('document_type', ['invoice', 'warranty']);
            $table->uuid('document_public_id')->collation('utf8mb4_bin');
            $table->enum('channel', ['email', 'whatsapp']);
            $table->enum('state', ['prepared', 'opened', 'sent', 'failed']);
            $table->foreignId('actor_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('idempotency_key', 100)->collation('utf8mb4_bin');
            $table->char('request_sha256', 64)->collation('utf8mb4_bin');
            $table->boolean('intentional_resend')->default(false);
            $table->string('recipient', 255)->nullable();
            $table->string('subject', 500)->nullable();
            $table->unsignedInteger('document_version')->default(1);
            $table->char('document_sha256', 64)->collation('utf8mb4_bin');
            $table->json('template_revisions')->nullable();
            $table->string('provider_reference', 255)->nullable();
            $table->string('failure_summary', 500)->nullable();
            $table->dateTime('prepared_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['actor_admin_id', 'channel', 'document_type', 'document_public_id', 'idempotency_key'], 'mt31_delivery_replay');
            $table->index(['outlet_id', 'document_type', 'document_public_id', 'prepared_at'], 'mt31_delivery_document');
            $table->index(['state', 'prepared_at'], 'mt31_delivery_state');
        });

        $now = now();
        foreach (self::TEMPLATES as $key => [$document, $channel, $part, $text]) {
            DB::table('document_template_revisions')->insert([
                'public_id' => (string) Str::uuid(), 'template_key' => $key, 'document_type' => $document,
                'channel' => $channel, 'template_part' => $part, 'version' => 1, 'template_text' => $text, 'created_at' => $now,
            ]);
        }
        DB::table('permission_definitions')->insertOrIgnore([
            'code' => self::PERMISSION, 'label' => 'Send customer documents', 'introduced_at' => $now,
        ]);
        foreach (['Full Access', 'Manager', 'Store Manager', 'Sales Associate', 'Cashier', 'Service & Warranty', 'Customer Support'] as $role) {
            $this->grant($role, self::PERMISSION);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_delivery_attempts') && DB::table('document_delivery_attempts')->exists()) {
            throw new RuntimeException('Document delivery audit must be preserved before rollback.');
        }
        if (Schema::hasTable('document_template_revisions')
            && DB::table('document_template_revisions')->where(fn ($query) => $query->where('version', '>', 1)->orWhereNotNull('created_by_admin_id'))->exists()) {
            throw new RuntimeException('Managed document template history must be preserved before rollback.');
        }
        if (Schema::hasColumn('invoices', 'customer_email') && DB::table('invoices')->whereNotNull('customer_email')->exists()) {
            throw new RuntimeException('Historical invoice email snapshots must be preserved before rollback.');
        }
        DB::table('role_permissions')->where('permission_code', self::PERMISSION)->delete();
        DB::table('permission_definitions')->where('code', self::PERMISSION)->delete();
        Schema::dropIfExists('document_delivery_attempts');
        Schema::dropIfExists('document_template_revisions');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('customer_email'));
    }

    private function grant(string $roleName, string $permission): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        if ($roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_code' => $permission]);
        }
    }
};
