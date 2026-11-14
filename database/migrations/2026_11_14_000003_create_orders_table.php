<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Derived from the id, so it is filled right after the insert.
            $table->string('number')->nullable()->unique();
            $table->string('status')->index();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');
            $table->string('street');
            $table->string('city');
            $table->string('zip');
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('vat_rate');
            $table->unsignedInteger('total');
            $table->unsignedInteger('vat_total');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
