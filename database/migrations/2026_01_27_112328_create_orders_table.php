<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // #2934 format
            $table->foreignId('user_id')->constrained(); // Cashier
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(5.00); // 5% tax for Nigeria
            $table->decimal('total', 10, 2);
            $table->decimal('cash_received', 10, 2)->nullable();
            $table->decimal('change_due', 10, 2)->nullable();
            $table->enum('status', ['pending', 'paid', 'refund'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
