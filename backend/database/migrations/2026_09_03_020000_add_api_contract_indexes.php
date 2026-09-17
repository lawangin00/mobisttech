<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $table->index(['is_online', 'id'], 'mt34_catalogue_page');
            $table->index(['is_online', 'name', 'id'], 'mt34_catalogue_search');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category', 'isDeleted', 'archived_at', 'id'], 'mt34_product_category');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'id'], 'mt34_customer_orders');
        });
        Schema::table('digital_services', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order', 'id'], 'mt34_public_services');
        });
        Schema::table('software_releases', function (Blueprint $table) {
            $table->index(['software_product_id', 'state', 'release_date', 'id'], 'mt34_public_releases');
        });
    }

    public function down(): void
    {
        Schema::table('software_releases', fn (Blueprint $table) => $table->dropIndex('mt34_public_releases'));
        Schema::table('digital_services', fn (Blueprint $table) => $table->dropIndex('mt34_public_services'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('mt34_customer_orders'));
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('mt34_product_category'));
        Schema::table('product_listings', function (Blueprint $table) {
            $table->dropIndex('mt34_catalogue_search');
            $table->dropIndex('mt34_catalogue_page');
        });
    }
};
