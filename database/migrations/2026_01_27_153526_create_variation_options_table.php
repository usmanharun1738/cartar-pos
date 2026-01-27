<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_type_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Small, Medium, Large, Red, Blue
            $table->string('code', 10); // S, M, L, RD, BL (for SKU generation)
            $table->string('value')->nullable(); // #FF0000 for colors, or other data
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_options');
    }
};
