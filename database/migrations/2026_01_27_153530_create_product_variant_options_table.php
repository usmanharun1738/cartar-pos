<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Each variant can only have one option per type
            $table->unique(['product_variant_id', 'variation_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
    }
};
