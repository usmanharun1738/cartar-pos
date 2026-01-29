<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'sku_prefix',
        'description',
        'image',
        'cost_price',
        'selling_price',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
        'is_hot',
        'has_variants',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'is_hot' => 'boolean',
        'has_variants' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($product) {
            // Auto-generate SKU if not provided
            if (empty($product->sku)) {
                $product->sku = self::generateSku($product->category_id);
            }
        });
    }

    /**
     * Generate a unique SKU with category prefix.
     */
    public static function generateSku(?int $categoryId = null): string
    {
        // Get category prefix (first 4 chars of category name)
        $prefix = 'PRD';
        if ($categoryId) {
            $category = Category::find($categoryId);
            if ($category) {
                // Use first 4 letters of category name, uppercase
                $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category->name), 0, 4));
                if (empty($prefix)) {
                    $prefix = 'PRD';
                }
            }
        }

        do {
            $sku = $prefix . '-' . strtoupper(substr(uniqid(), -5));
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Get the category that the product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the variants for the product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the images for the product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the primary image for the product.
     */
    public function getPrimaryImageAttribute(): ?ProductImage
    {
        return $this->images->where('is_primary', true)->first() 
            ?? $this->images->first();
    }

    /**
     * Get the primary image URL.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->url;
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include products in stock.
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope a query to only include low stock products.
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                     ->where('stock_quantity', '>', 0);
    }

    /**
     * Scope a query to only include out of stock products.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    /**
     * Get the stock status attribute.
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }
        
        if ($this->stock_quantity <= $this->low_stock_threshold) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Get formatted selling price in Naira.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->has_variants && $this->variants->count() > 0) {
            $minPrice = $this->variants->min('price');
            $maxPrice = $this->variants->max('price');
            if ($minPrice != $maxPrice) {
                return '₦' . number_format($minPrice, 2) . ' - ₦' . number_format($maxPrice, 2);
            }
            return '₦' . number_format($minPrice, 2);
        }
        return '₦' . number_format($this->selling_price, 2);
    }

    /**
     * Get total stock across all variants or base stock.
     */
    public function getTotalStockAttribute(): int
    {
        if ($this->has_variants) {
            return $this->variants->sum('stock_quantity');
        }
        return $this->stock_quantity;
    }
}

