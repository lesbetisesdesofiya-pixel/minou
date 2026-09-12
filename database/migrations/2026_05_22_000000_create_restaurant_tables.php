<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        Schema::create('dishes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2)->default(0.00);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('image')->nullable();
            $table->enum('menu_type', ['plats', 'bar'])->default('plats');
            $table->boolean('active')->default(true);
            $table->integer('orders_count')->default(0);
            $table->timestamps();
        });

        Schema::create('dish_variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('dish_id');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('group_name')->default('Variations');
            $table->decimal('stock_kg', 10, 2)->default(0.00);
            $table->timestamps();
            $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('cascade');
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->enum('type', ['garniture', 'supplement', 'parfum', 'custom_radio']);
            $table->decimal('prix', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('category_options', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('option_id')->constrained('options')->onDelete('cascade');
            $table->primary(['category_id', 'option_id']);
        });

        Schema::create('dish_options', function (Blueprint $table) {
            $table->unsignedInteger('dish_id');
            $table->foreignId('option_id')->constrained('options')->onDelete('cascade');
            $table->primary(['dish_id', 'option_id']);
            $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('cascade');
        });

        Schema::create('delivery_persons', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->boolean('active')->default(true);
            $table->boolean('suspended')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('neighborhood')->nullable();
            $table->integer('table_number')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('delivery_persons')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->text('options_text')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('RESTAURANT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('dish_options');
        Schema::dropIfExists('category_options');
        Schema::dropIfExists('dish_variations');
        Schema::dropIfExists('options');
        Schema::dropIfExists('dishes');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('delivery_persons');
        Schema::dropIfExists('admins');
    }
};
