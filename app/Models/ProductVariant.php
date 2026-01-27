<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'variant_name',
        'price',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(VariationOption::class, 'product_variant_options')
            ->withTimestamps();
    }

    // Format: "Product Name - Size / Color"
    public function getDisplayNameAttribute(): string
    {
        if ($this->variant_name) {
            return $this->product->name . ' - ' . $this->variant_name;
        }
        
        $optionNames = $this->options->pluck('name')->join(' / ');
        return $this->product->name . ($optionNames ? ' - ' . $optionNames : '');
    }

    // Formatted price in Naira
    public function getFormattedPriceAttribute(): string
    {
        return '₦' . number_format($this->price, 2);
    }

    // Stock status
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }
        if ($this->stock_quantity <= 5) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // Generate SKU from product prefix and option codes
    public static function generateSku(Product $product, array $optionIds): string
    {
        $prefix = $product->sku_prefix ?: $product->sku;
        $options = VariationOption::whereIn('id', $optionIds)
            ->orderBy('variation_type_id')
            ->get();
        
        $codes = $options->pluck('code')->join('-');
        
        return $prefix . ($codes ? '-' . $codes : '');
    }

    // Generate variant name from options
    public static function generateVariantName(array $optionIds): string
    {
        $options = VariationOption::whereIn('id', $optionIds)
            ->with('type')
            ->orderBy('variation_type_id')
            ->get();
        
        return $options->pluck('name')->join(' / ');
    }
}
