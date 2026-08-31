<?php

// MT-2.1: adapted from pinned source schemas; no source data or backfills execute here.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('backup_records', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_backup_records_0')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['handled_by_admin_id'], 'mt21_fk_claims_0')->references(['id'])->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_claims_1')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_claims_2')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_claims_3')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['sale_id'], 'mt21_fk_claims_4')->references(['id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_claims_5')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['stock_unit_id', 'product_id'], 'mt21_fk_claims_6')->references(['id', 'product_id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['invoice_id', 'outlet_id'], 'mt21_fk_claims_7')->references(['id', 'outlet_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_claims_8')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `claims` ADD CONSTRAINT `mt21_check_claims_0` CHECK (quantity > 0)');
        Schema::table('legacy_integration_requests', function (Blueprint $table) {
            $table->foreign(['reservation_id'], 'mt21_fk_legacy_integration_requests_0')->references(['id'])->on('reservations')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign(['salesperson_admin_id'], 'mt21_fk_invoices_0')->references(['id'])->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_invoices_1')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'mt21_fk_invoices_2')->references(['id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_invoices_3')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `invoices` ADD CONSTRAINT `mt21_check_invoices_0` CHECK (currency = \'PKR\')');
        Schema::table('pos_audit_logs', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_pos_audit_logs_0')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('pos_configuration_revisions', function (Blueprint $table) {
            $table->foreign(['restored_from_revision_id'], 'mt21_fk_pos_configuration_revisions_0')->references(['id'])->on('pos_configuration_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('pos_master_data_usages', function (Blueprint $table) {
            $table->foreign(['master_data_option_id'], 'mt21_fk_pos_master_data_usages_0')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('pos_media_usages', function (Blueprint $table) {
            $table->foreign(['media_asset_id'], 'mt21_fk_pos_media_usages_0')->references(['id'])->on('pos_media_assets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_imeis', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_product_imeis_0')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_imeis', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_product_imeis_1')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_imeis', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_product_imeis_2')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_imeis', function (Blueprint $table) {
            $table->foreign(['sale_id'], 'mt21_fk_product_imeis_3')->references(['id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_imeis', function (Blueprint $table) {
            $table->foreign(['stock_unit_id', 'product_id'], 'mt21_fk_product_imeis_4')->references(['id', 'product_id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['subcategory_master_data_id'], 'mt21_fk_products_0')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['category_master_data_id'], 'mt21_fk_products_1')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_products_2')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['brand_master_data_id'], 'mt21_fk_products_3')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['ram_master_data_id'], 'mt21_fk_products_4')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['storage_master_data_id'], 'mt21_fk_products_5')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['sim_master_data_id'], 'mt21_fk_products_6')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `products` ADD CONSTRAINT `mt21_check_products_0` CHECK (qty >= 0 AND sold_qty >= 0 AND price >= 0 AND purchase_price >= 0 AND sale_price >= 0)');
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_sales_0')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_sales_1')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_sales_2')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign(['invoice_id', 'outlet_id'], 'mt21_fk_sales_3')->references(['id', 'outlet_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_sales_4')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `sales` ADD CONSTRAINT `mt21_check_sales_0` CHECK (quantity > 0)');
        Schema::table('outlet_admins', function (Blueprint $table) {
            $table->foreign(['admin_id'], 'mt21_fk_outlet_admins_0')->references(['id'])->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('outlet_admins', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_outlet_admins_1')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_acquisitions', function (Blueprint $table) {
            $table->foreign(['source_type_master_data_id'], 'mt21_fk_stock_acquisitions_0')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_acquisitions', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_stock_acquisitions_1')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_acquisitions', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_stock_acquisitions_2')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_acquisitions', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_stock_acquisitions_3')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `stock_acquisitions` ADD CONSTRAINT `mt21_check_stock_acquisitions_0` CHECK (quantity > 0)');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_stock_movements_0')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_stock_movements_1')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_stock_movements_2')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['stock_unit_id', 'product_id'], 'mt21_fk_stock_movements_3')->references(['id', 'product_id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_stock_movements_4')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['mdm_status_master_data_id'], 'mt21_fk_stock_units_0')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['carrier_lock_master_data_id'], 'mt21_fk_stock_units_1')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['pta_status_master_data_id'], 'mt21_fk_stock_units_2')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['condition_master_data_id'], 'mt21_fk_stock_units_3')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['sale_id'], 'mt21_fk_stock_units_4')->references(['id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_stock_units_5')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_stock_units_6')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['stock_acquisition_id'], 'mt21_fk_stock_units_7')->references(['id'])->on('stock_acquisitions')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('stock_units', function (Blueprint $table) {
            $table->foreign(['color_master_data_id'], 'mt21_fk_stock_units_8')->references(['id'])->on('pos_master_data_options')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_allocations', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_reservation_allocations_0')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_allocations', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_reservation_allocations_1')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_allocations', function (Blueprint $table) {
            $table->foreign(['reservation_line_id'], 'mt21_fk_reservation_allocations_2')->references(['id'])->on('reservation_lines')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_allocations', function (Blueprint $table) {
            $table->foreign(['stock_unit_id', 'product_id'], 'mt21_fk_reservation_allocations_3')->references(['id', 'product_id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_allocations', function (Blueprint $table) {
            $table->foreign(['reservation_line_id', 'product_id'], 'mt21_fk_reservation_allocations_4')->references(['id', 'product_id'])->on('reservation_lines')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `reservation_allocations` ADD CONSTRAINT `mt21_check_reservation_allocations_0` CHECK (quantity > 0)');
        Schema::table('reservation_lines', function (Blueprint $table) {
            $table->foreign(['reservation_id'], 'mt21_fk_reservation_lines_0')->references(['id'])->on('reservations')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_lines', function (Blueprint $table) {
            $table->foreign(['order_item_id'], 'mt21_fk_reservation_lines_1')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_lines', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_reservation_lines_2')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_lines', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_reservation_lines_3')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservation_lines', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_reservation_lines_4')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `reservation_lines` ADD CONSTRAINT `mt21_check_reservation_lines_0` CHECK (quantity > 0)');
        DB::statement('ALTER TABLE `reservation_lines` ADD CONSTRAINT `mt21_check_reservation_lines_1` CHECK (allocated_quantity >= 0 AND allocated_quantity <= quantity)');
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_reservations_0')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_reservations_1')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_reservations_2')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `reservations` ADD CONSTRAINT `mt21_check_reservations_0` CHECK (attempt > 0)');
        DB::statement('ALTER TABLE `reservations` ADD CONSTRAINT `mt21_check_reservations_1` CHECK (currency = \'PKR\')');
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign(['user_id'], 'mt21_fk_admin_audit_logs_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['digital_service_id'], 'mt21_fk_order_items_0')->references(['id'])->on('digital_services')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['product_listing_id'], 'mt21_fk_order_items_1')->references(['id'])->on('product_listings')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_order_items_2')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_order_items_3')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_order_items_4')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['project_quote_id'], 'mt21_fk_order_items_5')->references(['id'])->on('project_quotes')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['product_id', 'outlet_id'], 'mt21_fk_order_items_6')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `mt21_check_order_items_0` CHECK ((product_id IS NULL AND outlet_id IS NULL) OR (product_id IS NOT NULL AND outlet_id IS NOT NULL))');
        DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `mt21_check_order_items_1` CHECK (quantity > 0)');
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['user_id'], 'mt21_fk_orders_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'mt21_fk_orders_1')->references(['id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['stock_return_confirmed_by'], 'mt21_fk_orders_2')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `mt21_check_orders_0` CHECK (currency = \'PKR\')');
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_payments_0')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `mt21_check_payments_0` CHECK (currency = \'PKR\')');
        DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `mt21_check_payments_1` CHECK (amount >= 0)');
        Schema::table('legacy_integration_events', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_legacy_integration_events_0')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign(['order_item_id'], 'mt21_fk_product_reviews_0')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign(['user_id'], 'mt21_fk_product_reviews_1')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign(['product_listing_id'], 'mt21_fk_product_reviews_2')->references(['id'])->on('product_listings')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('product_listings', function (Blueprint $table) {
            $table->foreign(['product_id'], 'mt21_fk_product_listings_0')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('project_quotes', function (Blueprint $table) {
            $table->foreign(['service_request_id'], 'mt21_fk_project_quotes_0')->references(['id'])->on('service_requests')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('project_quotes', function (Blueprint $table) {
            $table->foreign(['digital_service_id'], 'mt21_fk_project_quotes_1')->references(['id'])->on('digital_services')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `project_quotes` ADD CONSTRAINT `mt21_check_project_quotes_0` CHECK (currency = \'PKR\')');
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign(['digital_service_id'], 'mt21_fk_service_requests_0')->references(['id'])->on('digital_services')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_configuration_revisions', function (Blueprint $table) {
            $table->foreign(['restored_from_revision_id'], 'mt21_fk_site_configuration_revisions_0')->references(['id'])->on('site_configuration_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_configuration_revisions', function (Blueprint $table) {
            $table->foreign(['published_by_user_id'], 'mt21_fk_site_configuration_revisions_1')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_configuration_revisions', function (Blueprint $table) {
            $table->foreign(['created_by_user_id'], 'mt21_fk_site_configuration_revisions_2')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->foreign(['updated_by_user_id'], 'mt21_fk_site_managed_pages_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->foreign(['created_by_user_id'], 'mt21_fk_site_managed_pages_1')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->foreign(['social_image_media_id'], 'mt21_fk_site_managed_pages_2')->references(['id'])->on('site_media_assets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_media_assets', function (Blueprint $table) {
            $table->foreign(['uploaded_by_user_id'], 'mt21_fk_site_media_assets_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_media_usages', function (Blueprint $table) {
            $table->foreign(['media_asset_id'], 'mt21_fk_site_media_usages_0')->references(['id'])->on('site_media_assets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_navigation_items', function (Blueprint $table) {
            $table->foreign(['updated_by_user_id'], 'mt21_fk_site_navigation_items_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_navigation_items', function (Blueprint $table) {
            $table->foreign(['created_by_user_id'], 'mt21_fk_site_navigation_items_1')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_navigation_items', function (Blueprint $table) {
            $table->foreign(['parent_id'], 'mt21_fk_site_navigation_items_2')->references(['id'])->on('site_navigation_items')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('site_secret_settings', function (Blueprint $table) {
            $table->foreign(['updated_by_user_id'], 'mt21_fk_site_secret_settings_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign(['website_user_id'], 'mt21_fk_customers_0')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('customer_source_links', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'mt21_fk_customer_source_links_0')->references(['id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('active_imeis', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_active_imeis_0')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `active_imeis` ADD CONSTRAINT `mt21_check_active_imeis_0` CHECK (slot_no > 0)');
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->foreign(['payment_id'], 'mt21_fk_payment_receipts_0')->references(['id'])->on('payments')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `payment_receipts` ADD CONSTRAINT `mt21_check_payment_receipts_0` CHECK (currency = \'PKR\')');
        DB::statement('ALTER TABLE `payment_receipts` ADD CONSTRAINT `mt21_check_payment_receipts_1` CHECK (amount >= 0)');
        Schema::table('returns', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'mt21_fk_returns_0')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('returns', function (Blueprint $table) {
            $table->foreign(['order_id'], 'mt21_fk_returns_1')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('return_lines', function (Blueprint $table) {
            $table->foreign(['return_id'], 'mt21_fk_return_lines_0')->references(['id'])->on('returns')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('return_lines', function (Blueprint $table) {
            $table->foreign(['sale_id'], 'mt21_fk_return_lines_1')->references(['id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('return_lines', function (Blueprint $table) {
            $table->foreign(['stock_unit_id'], 'mt21_fk_return_lines_2')->references(['id'])->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `return_lines` ADD CONSTRAINT `mt21_check_return_lines_0` CHECK (quantity > 0)');
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign(['payment_id'], 'mt21_fk_refunds_0')->references(['id'])->on('payments')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign(['return_id'], 'mt21_fk_refunds_1')->references(['id'])->on('returns')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `refunds` ADD CONSTRAINT `mt21_check_refunds_0` CHECK (amount > 0)');
        DB::statement('ALTER TABLE `refunds` ADD CONSTRAINT `mt21_check_refunds_1` CHECK (currency = \'PKR\')');
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->foreign(['outlet_id'], 'mt21_fk_document_sequences_0')->references(['id'])->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `document_sequences` ADD CONSTRAINT `mt21_check_document_sequences_0` CHECK (next_sequence > 0)');
        Schema::table('migration_identity_map', function (Blueprint $table) {
            $table->foreign(['run_id'], 'mt21_fk_migration_identity_map_0')->references(['id'])->on('migration_runs')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `migration_identity_map` ADD CONSTRAINT `mt21_check_migration_identity_map_0` CHECK (outcome <> \'merged\' OR reviewed_merge_reference IS NOT NULL)');
        Schema::table('migration_quarantine', function (Blueprint $table) {
            $table->foreign(['run_id'], 'mt21_fk_migration_quarantine_0')->references(['id'])->on('migration_runs')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('migration_reconciliation', function (Blueprint $table) {
            $table->foreign(['run_id'], 'mt21_fk_migration_reconciliation_0')->references(['id'])->on('migration_runs')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('migration_source_history', function (Blueprint $table) {
            $table->foreign(['run_id'], 'mt21_fk_migration_source_history_0')->references(['id'])->on('migration_runs')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('migration_source_history', fn (Blueprint $table) => $table->dropForeign('mt21_fk_migration_source_history_0'));
        Schema::table('migration_reconciliation', fn (Blueprint $table) => $table->dropForeign('mt21_fk_migration_reconciliation_0'));
        Schema::table('migration_quarantine', fn (Blueprint $table) => $table->dropForeign('mt21_fk_migration_quarantine_0'));
        DB::statement('ALTER TABLE `migration_identity_map` DROP CHECK `mt21_check_migration_identity_map_0`');
        Schema::table('migration_identity_map', fn (Blueprint $table) => $table->dropForeign('mt21_fk_migration_identity_map_0'));
        DB::statement('ALTER TABLE `document_sequences` DROP CHECK `mt21_check_document_sequences_0`');
        Schema::table('document_sequences', fn (Blueprint $table) => $table->dropForeign('mt21_fk_document_sequences_0'));
        DB::statement('ALTER TABLE `refunds` DROP CHECK `mt21_check_refunds_1`');
        DB::statement('ALTER TABLE `refunds` DROP CHECK `mt21_check_refunds_0`');
        Schema::table('refunds', fn (Blueprint $table) => $table->dropForeign('mt21_fk_refunds_1'));
        Schema::table('refunds', fn (Blueprint $table) => $table->dropForeign('mt21_fk_refunds_0'));
        DB::statement('ALTER TABLE `return_lines` DROP CHECK `mt21_check_return_lines_0`');
        Schema::table('return_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_return_lines_2'));
        Schema::table('return_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_return_lines_1'));
        Schema::table('return_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_return_lines_0'));
        Schema::table('returns', fn (Blueprint $table) => $table->dropForeign('mt21_fk_returns_1'));
        Schema::table('returns', fn (Blueprint $table) => $table->dropForeign('mt21_fk_returns_0'));
        DB::statement('ALTER TABLE `payment_receipts` DROP CHECK `mt21_check_payment_receipts_1`');
        DB::statement('ALTER TABLE `payment_receipts` DROP CHECK `mt21_check_payment_receipts_0`');
        Schema::table('payment_receipts', fn (Blueprint $table) => $table->dropForeign('mt21_fk_payment_receipts_0'));
        DB::statement('ALTER TABLE `active_imeis` DROP CHECK `mt21_check_active_imeis_0`');
        Schema::table('active_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_active_imeis_0'));
        Schema::table('customer_source_links', fn (Blueprint $table) => $table->dropForeign('mt21_fk_customer_source_links_0'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropForeign('mt21_fk_customers_0'));
        Schema::table('site_secret_settings', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_secret_settings_0'));
        Schema::table('site_navigation_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_navigation_items_2'));
        Schema::table('site_navigation_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_navigation_items_1'));
        Schema::table('site_navigation_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_navigation_items_0'));
        Schema::table('site_media_usages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_media_usages_0'));
        Schema::table('site_media_assets', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_media_assets_0'));
        Schema::table('site_managed_pages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_managed_pages_2'));
        Schema::table('site_managed_pages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_managed_pages_1'));
        Schema::table('site_managed_pages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_managed_pages_0'));
        Schema::table('site_configuration_revisions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_configuration_revisions_2'));
        Schema::table('site_configuration_revisions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_configuration_revisions_1'));
        Schema::table('site_configuration_revisions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_site_configuration_revisions_0'));
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropForeign('mt21_fk_service_requests_0'));
        DB::statement('ALTER TABLE `project_quotes` DROP CHECK `mt21_check_project_quotes_0`');
        Schema::table('project_quotes', fn (Blueprint $table) => $table->dropForeign('mt21_fk_project_quotes_1'));
        Schema::table('project_quotes', fn (Blueprint $table) => $table->dropForeign('mt21_fk_project_quotes_0'));
        Schema::table('product_listings', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_listings_0'));
        Schema::table('product_reviews', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_reviews_2'));
        Schema::table('product_reviews', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_reviews_1'));
        Schema::table('product_reviews', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_reviews_0'));
        Schema::table('legacy_integration_events', fn (Blueprint $table) => $table->dropForeign('mt21_fk_legacy_integration_events_0'));
        DB::statement('ALTER TABLE `payments` DROP CHECK `mt21_check_payments_1`');
        DB::statement('ALTER TABLE `payments` DROP CHECK `mt21_check_payments_0`');
        Schema::table('payments', fn (Blueprint $table) => $table->dropForeign('mt21_fk_payments_0'));
        DB::statement('ALTER TABLE `orders` DROP CHECK `mt21_check_orders_0`');
        Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('mt21_fk_orders_2'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('mt21_fk_orders_1'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('mt21_fk_orders_0'));
        DB::statement('ALTER TABLE `order_items` DROP CHECK `mt21_check_order_items_1`');
        DB::statement('ALTER TABLE `order_items` DROP CHECK `mt21_check_order_items_0`');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_6'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_5'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_4'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_3'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_2'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_1'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('mt21_fk_order_items_0'));
        Schema::table('admin_audit_logs', fn (Blueprint $table) => $table->dropForeign('mt21_fk_admin_audit_logs_0'));
        DB::statement('ALTER TABLE `reservations` DROP CHECK `mt21_check_reservations_1`');
        DB::statement('ALTER TABLE `reservations` DROP CHECK `mt21_check_reservations_0`');
        Schema::table('reservations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservations_2'));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservations_1'));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservations_0'));
        DB::statement('ALTER TABLE `reservation_lines` DROP CHECK `mt21_check_reservation_lines_1`');
        DB::statement('ALTER TABLE `reservation_lines` DROP CHECK `mt21_check_reservation_lines_0`');
        Schema::table('reservation_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_lines_4'));
        Schema::table('reservation_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_lines_3'));
        Schema::table('reservation_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_lines_2'));
        Schema::table('reservation_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_lines_1'));
        Schema::table('reservation_lines', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_lines_0'));
        DB::statement('ALTER TABLE `reservation_allocations` DROP CHECK `mt21_check_reservation_allocations_0`');
        Schema::table('reservation_allocations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_allocations_4'));
        Schema::table('reservation_allocations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_allocations_3'));
        Schema::table('reservation_allocations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_allocations_2'));
        Schema::table('reservation_allocations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_allocations_1'));
        Schema::table('reservation_allocations', fn (Blueprint $table) => $table->dropForeign('mt21_fk_reservation_allocations_0'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_8'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_7'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_6'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_5'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_4'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_3'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_2'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_1'));
        Schema::table('stock_units', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_units_0'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_movements_4'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_movements_3'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_movements_2'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_movements_1'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_movements_0'));
        DB::statement('ALTER TABLE `stock_acquisitions` DROP CHECK `mt21_check_stock_acquisitions_0`');
        Schema::table('stock_acquisitions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_acquisitions_3'));
        Schema::table('stock_acquisitions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_acquisitions_2'));
        Schema::table('stock_acquisitions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_acquisitions_1'));
        Schema::table('stock_acquisitions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_stock_acquisitions_0'));
        Schema::table('outlet_admins', fn (Blueprint $table) => $table->dropForeign('mt21_fk_outlet_admins_1'));
        Schema::table('outlet_admins', fn (Blueprint $table) => $table->dropForeign('mt21_fk_outlet_admins_0'));
        DB::statement('ALTER TABLE `sales` DROP CHECK `mt21_check_sales_0`');
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign('mt21_fk_sales_4'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign('mt21_fk_sales_3'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign('mt21_fk_sales_2'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign('mt21_fk_sales_1'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign('mt21_fk_sales_0'));
        DB::statement('ALTER TABLE `products` DROP CHECK `mt21_check_products_0`');
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_6'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_5'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_4'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_3'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_2'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_1'));
        Schema::table('products', fn (Blueprint $table) => $table->dropForeign('mt21_fk_products_0'));
        Schema::table('product_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_imeis_4'));
        Schema::table('product_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_imeis_3'));
        Schema::table('product_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_imeis_2'));
        Schema::table('product_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_imeis_1'));
        Schema::table('product_imeis', fn (Blueprint $table) => $table->dropForeign('mt21_fk_product_imeis_0'));
        Schema::table('pos_media_usages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_pos_media_usages_0'));
        Schema::table('pos_master_data_usages', fn (Blueprint $table) => $table->dropForeign('mt21_fk_pos_master_data_usages_0'));
        Schema::table('pos_configuration_revisions', fn (Blueprint $table) => $table->dropForeign('mt21_fk_pos_configuration_revisions_0'));
        Schema::table('pos_audit_logs', fn (Blueprint $table) => $table->dropForeign('mt21_fk_pos_audit_logs_0'));
        DB::statement('ALTER TABLE `invoices` DROP CHECK `mt21_check_invoices_0`');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('mt21_fk_invoices_3'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('mt21_fk_invoices_2'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('mt21_fk_invoices_1'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('mt21_fk_invoices_0'));
        Schema::table('legacy_integration_requests', fn (Blueprint $table) => $table->dropForeign('mt21_fk_legacy_integration_requests_0'));
        DB::statement('ALTER TABLE `claims` DROP CHECK `mt21_check_claims_0`');
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_8'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_7'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_6'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_5'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_4'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_3'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_2'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_1'));
        Schema::table('claims', fn (Blueprint $table) => $table->dropForeign('mt21_fk_claims_0'));
        Schema::table('backup_records', fn (Blueprint $table) => $table->dropForeign('mt21_fk_backup_records_0'));
    }
};
