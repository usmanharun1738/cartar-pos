<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariationType;
use App\Models\VariationOption;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;

layout('layouts.app');

state([
    'product' => null,
    // General Information
    'productName' => '',
    'skuPrefix' => '',
    'categoryId' => '',
    'basePrice' => '',
    'isActive' => true,
    'isHot' => false,
    
    // Variants (for variant products)
    'variants' => [],
]);

mount(function ($product) {
    $this->product = Product::with(['variants', 'category'])->findOrFail($product);
    
    // Populate form
    $this->productName = $this->product->name;
    $this->skuPrefix = $this->product->sku_prefix ?? $this->product->sku;
    $this->categoryId = $this->product->category_id;
    $this->basePrice = $this->product->selling_price;
    $this->isActive = $this->product->is_active;
    $this->isHot = $this->product->is_hot;
    
    // Load variants
    if ($this->product->has_variants) {
        $this->variants = $this->product->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->variant_name,
                'price' => $variant->price,
                'stock' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
            ];
        })->toArray();
    }
});

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

// Update variant price
$updateVariantPrice = function ($index, $price) {
    if (isset($this->variants[$index])) {
        $this->variants[$index]['price'] = floatval($price);
    }
};

// Update variant stock
$updateVariantStock = function ($index, $stock) {
    if (isset($this->variants[$index])) {
        $this->variants[$index]['stock'] = intval($stock);
    }
};

// Toggle variant active status
$toggleVariantActive = function ($index) {
    if (isset($this->variants[$index])) {
        $this->variants[$index]['is_active'] = !$this->variants[$index]['is_active'];
    }
};

// Update all prices
$updateAllPrices = function ($price) {
    $priceValue = floatval($price);
    foreach ($this->variants as $index => $variant) {
        $this->variants[$index]['price'] = $priceValue;
    }
};

// Update all stock
$updateAllStock = function ($stock) {
    $stockValue = intval($stock);
    foreach ($this->variants as $index => $variant) {
        $this->variants[$index]['stock'] = $stockValue;
    }
};

// Save Product and Variants
$saveProduct = function () {
    // Validate
    $this->validate([
        'productName' => 'required|string|max:255',
        'skuPrefix' => 'required|string|max:20',
        'categoryId' => 'required|exists:categories,id',
    ]);
    
    // Update product
    $this->product->update([
        'name' => $this->productName,
        'sku' => $this->skuPrefix,
        'sku_prefix' => $this->skuPrefix,
        'category_id' => $this->categoryId,
        'selling_price' => floatval($this->basePrice) ?: 0,
        'is_active' => $this->isActive,
        'is_hot' => $this->isHot,
    ]);
    
    // Update variants
    if ($this->product->has_variants) {
        foreach ($this->variants as $variantData) {
            $variant = ProductVariant::find($variantData['id']);
            if ($variant) {
                $variant->update([
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock'],
                    'is_active' => $variantData['is_active'],
                ]);
            }
        }
    }
    
    // Redirect back to products
    return $this->redirect('/products', navigate: true);
};

?>

<x-slot name="header">
    <div class="flex items-center gap-3">
        <a href="/products" wire:navigate class="p-2 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white">Edit Product</h1>
            <p class="text-sm text-text-secondary mt-1">Update product details and variant inventory</p>
        </div>
    </div>
</x-slot>

<div class="max-w-4xl mx-auto">
    <div class="space-y-6">
        
        <!-- Section 1: General Information -->
        <div class="pos-card">
            <div class="flex items-center gap-3 px-6 py-4 border-b border-border-dark">
                <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-lg">info</span>
                </div>
                <h2 class="text-lg font-semibold text-white">General Information</h2>
            </div>
            <div class="p-6 space-y-4">
                <!-- Product Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Product Name</label>
                    <input 
                        wire:model.live="productName" 
                        type="text" 
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="e.g. Premium Cotton Crewneck"
                    />
                    @error('productName') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <!-- SKU Prefix -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">SKU Prefix</label>
                        <input 
                            wire:model.live="skuPrefix" 
                            type="text" 
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="PRD-101"
                        />
                        @error('skuPrefix') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Category</label>
                        <select 
                            wire:model="categoryId"
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                        >
                            <option value="">Select category</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <!-- Base Price -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Base Price (₦)</label>
                        <input 
                            wire:model.live="basePrice" 
                            type="number" 
                            step="0.01"
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="flex items-end gap-6 pb-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="isActive" class="rounded border-border-dark bg-border-dark text-primary focus:ring-primary focus:ring-offset-0">
                            <span class="text-sm text-gray-200">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="isHot" class="rounded border-border-dark bg-border-dark text-red-500 focus:ring-red-500 focus:ring-offset-0">
                            <span class="text-sm text-gray-200">Mark as HOT</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Variant Inventory (Only for products with variants) -->
        @if($product->has_variants && count($variants) > 0)
        <div class="pos-card">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-lg">inventory_2</span>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Variant Inventory</h2>
                    <span class="text-sm text-text-secondary">({{ count($variants) }} variants)</span>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        x-data
                        @click="$dispatch('update-all-prices', { price: prompt('Enter price for all variants:', '{{ $basePrice }}') })"
                        class="px-3 py-1.5 rounded-lg border border-border-dark text-text-secondary hover:text-white text-sm flex items-center gap-1"
                    >
                        <span class="material-symbols-outlined text-sm">edit</span>
                        Update All Prices
                    </button>
                    <button 
                        x-data
                        @click="$dispatch('update-all-stock', { stock: prompt('Enter stock for all variants:', '0') })"
                        class="px-3 py-1.5 rounded-lg border border-border-dark text-text-secondary hover:text-white text-sm flex items-center gap-1"
                    >
                        <span class="material-symbols-outlined text-sm">inventory</span>
                        Update All Stock
                    </button>
                </div>
            </div>
            
            <!-- Listen for bulk updates -->
            <div 
                x-data
                @update-all-prices.window="if($event.detail.price) $wire.updateAllPrices($event.detail.price)"
                @update-all-stock.window="if($event.detail.stock) $wire.updateAllStock($event.detail.stock)"
            >
                <!-- Variants Table -->
                <div class="p-6">
                    <div class="space-y-3">
                        <!-- Header -->
                        <div class="grid grid-cols-12 gap-4 px-4 text-xs font-semibold text-text-secondary uppercase tracking-wider">
                            <div class="col-span-4">Variant Info</div>
                            <div class="col-span-3">SKU</div>
                            <div class="col-span-2">Price (₦)</div>
                            <div class="col-span-2">Stock</div>
                            <div class="col-span-1">Active</div>
                        </div>
                        
                        <!-- Variant Rows -->
                        @foreach($variants as $index => $variant)
                        <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 bg-border-dark/30 rounded-lg {{ !$variant['is_active'] ? 'opacity-50' : '' }}">
                            <!-- Variant Info -->
                            <div class="col-span-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center text-primary font-bold text-xs">
                                        {{ strtoupper(substr($variant['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ $variant['name'] }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SKU -->
                            <div class="col-span-3">
                                <span class="text-sm font-mono text-text-secondary">{{ $variant['sku'] }}</span>
                            </div>
                            
                            <!-- Price -->
                            <div class="col-span-2">
                                <div class="flex items-center">
                                    <span class="text-text-secondary text-sm mr-1">₦</span>
                                    <input 
                                        type="number" 
                                        step="0.01"
                                        value="{{ $variant['price'] }}"
                                        wire:change="updateVariantPrice({{ $index }}, $event.target.value)"
                                        class="w-full h-8 bg-surface-dark border border-border-dark rounded text-white text-sm px-2 focus:ring-1 focus:ring-primary"
                                    />
                                </div>
                            </div>
                            
                            <!-- Stock -->
                            <div class="col-span-2">
                                <input 
                                    type="number" 
                                    value="{{ $variant['stock'] }}"
                                    wire:change="updateVariantStock({{ $index }}, $event.target.value)"
                                    class="w-full h-8 bg-surface-dark border border-border-dark rounded text-white text-sm px-2 focus:ring-1 focus:ring-primary"
                                />
                            </div>
                            
                            <!-- Active Toggle -->
                            <div class="col-span-1 text-center">
                                <button 
                                    wire:click="toggleVariantActive({{ $index }})"
                                    class="p-1.5 rounded-lg transition-colors {{ $variant['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}"
                                >
                                    <span class="material-symbols-outlined text-lg">
                                        {{ $variant['is_active'] ? 'check_circle' : 'cancel' }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Summary Stats (for variant products) -->
        @if($product->has_variants && count($variants) > 0)
        <div class="pos-card p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div>
                        <span class="text-xs text-text-secondary uppercase tracking-wider">Total Stock</span>
                        <p class="text-lg font-bold text-white">{{ collect($variants)->sum('stock') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-text-secondary uppercase tracking-wider">Price Range</span>
                        <p class="text-lg font-bold text-white">
                            ₦{{ number_format(collect($variants)->min('price'), 2) }} - ₦{{ number_format(collect($variants)->max('price'), 2) }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-text-secondary uppercase tracking-wider">Active Variants</span>
                        <p class="text-lg font-bold text-white">{{ collect($variants)->where('is_active', true)->count() }} / {{ count($variants) }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 py-6">
            <a href="/products" wire:navigate class="px-4 py-2.5 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                Cancel
            </a>
            <button 
                wire:click="saveProduct"
                class="pos-button-primary px-6 py-2.5 flex items-center gap-2"
            >
                <span class="material-symbols-outlined text-lg">save</span>
                <span>Update Product</span>
            </button>
        </div>
    </div>
</div>
