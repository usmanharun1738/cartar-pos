<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'path',
        'filename',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant (if applicable).
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the public URL for the image.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    /**
     * Get the thumbnail URL (same as URL for now, can add thumb logic later).
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->url;
    }

    /**
     * Delete the image file from storage when model is deleted.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($image) {
            if (Storage::exists($image->path)) {
                Storage::delete($image->path);
            }
        });
    }
}
