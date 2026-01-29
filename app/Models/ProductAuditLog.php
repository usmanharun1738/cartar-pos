<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'description',
    ];

    /**
     * Get the user who made the change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant if applicable.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get action badge color.
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'stock_update' => 'blue',
            'price_change' => 'orange',
            'information' => 'green',
            'created' => 'emerald',
            'deleted' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'stock_update' => 'STOCK UPDATE',
            'price_change' => 'PRICE CHANGE',
            'information' => 'INFORMATION',
            'created' => 'CREATED',
            'deleted' => 'DELETED',
            default => strtoupper($this->action),
        };
    }

    /**
     * Create an audit log entry.
     */
    public static function log(
        int $productId,
        string $action,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?int $variantId = null,
        ?string $description = null
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'action' => $action,
            'field' => $field,
            'old_value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
            'description' => $description,
        ]);
    }
}
