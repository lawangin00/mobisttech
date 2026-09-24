<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_order_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('label', 80);
            $table->text('instructions');
            $table->string('cod_min_amount', 12)->nullable();
            $table->string('cod_max_amount', 12)->nullable();
            $table->unsignedInteger('presentation_version');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_order_payment_terms');
    }
};
