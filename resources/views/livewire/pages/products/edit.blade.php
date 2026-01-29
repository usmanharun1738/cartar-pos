<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductAuditLog;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;

layout('layouts.app');

state([
    'product' => null,
    'activeTab' => 'general', // general, variants, audit
    
    // General Information
    'productName' => '',
    'skuPrefix' => '',
    'categoryId' => '',
    'basePrice' => '',
    'stockQuantity' => 0,
    'isActive' => true,
    'isHot' => false,
    
    // Variants (for variant products)
    'variants' => [],
    
    // Original values for tracking changes
    'originalValues' => [],
]);

mount(function ($product) {
    $this->product = Product::with(['variants', 'category'])->findOrFail($product);
    
    // Populate form
    $this->productName = $this->product->name;
    $this->skuPrefix = $this->product->sku_prefix ?? $this->product->sku;
    $this->categoryId = $this->product->category_id;
    $this->basePrice = $this->product->selling_price;
    $this->stockQuantity = $this->product->stock_quantity;
    $this->isActive = $this->product->is_active;
    $this->isHot = $this->product->is_hot;
    
    // Store original values for audit tracking
    $this->originalValues = [
        'name' => $this->product->name,
        'sku' => $this->product->sku,
        'category_id' => $this->product->category_id,
        'selling_price' => $this->product->selling_price,
        'stock_quantity' => $this->product->stock_quantity,
        'is_active' => $this->product->is_active,
        'is_hot' => $this->product->is_hot,
    ];
    
    // Load variants
    if ($this->product->has_variants) {
        $this->variants = $this->product->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->variant_name,
                'price' => $variant->price,
                'original_price' => $variant->price,
                'stock' => $variant->stock_quantity,
                'original_stock' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
            ];
        })->toArray();
    }
});

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

$auditLogs = computed(function () {
    return ProductAuditLog::where('product_id', $this->product->id)
        ->with(['user', 'variant'])
        ->orderBy('created_at', 'desc')
        ->take(20)
        ->get();
});

// Tab navigation
$setTab = function ($tab) {
    $this->activeTab = $tab;
};

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
        'skuPrefix' => 'nullable|string|max:20',
        'categoryId' => 'required|exists:categories,id',
    ]);
    
    $changes = [];
    
    // Track name change
    if ($this->productName !== $this->originalValues['name']) {
        $changes[] = [
            'action' => 'information',
            'field' => 'name',
            'old' => $this->originalValues['name'],
            'new' => $this->productName,
            'description' => "Renamed from '{$this->originalValues['name']}' to '{$this->productName}'",
        ];
    }
    
    // Track price change (for simple products)
    if (!$this->product->has_variants && floatval($this->basePrice) !== floatval($this->originalValues['selling_price'])) {
        $changes[] = [
            'action' => 'price_change',
            'field' => 'selling_price',
            'old' => '₦' . number_format($this->originalValues['selling_price'], 2),
            'new' => '₦' . number_format($this->basePrice, 2),
            'description' => null,
        ];
    }
    
    // Track stock change (for simple products)
    if (!$this->product->has_variants && intval($this->stockQuantity) !== intval($this->originalValues['stock_quantity'])) {
        $changes[] = [
            'action' => 'stock_update',
            'field' => 'stock_quantity',
            'old' => $this->originalValues['stock_quantity'] . ' units',
            'new' => $this->stockQuantity . ' units',
            'description' => null,
        ];
    }
    
    // Update product
    $this->product->update([
        'name' => $this->productName,
        'sku' => $this->skuPrefix ?: $this->product->sku,
        'sku_prefix' => $this->skuPrefix ?: $this->product->sku_prefix,
        'category_id' => $this->categoryId,
        'selling_price' => floatval($this->basePrice) ?: 0,
        'stock_quantity' => intval($this->stockQuantity) ?: 0,
        'is_active' => $this->isActive,
        'is_hot' => $this->isHot,
    ]);
    
    // Update variants and track changes
    if ($this->product->has_variants) {
        foreach ($this->variants as $variantData) {
            $variant = ProductVariant::find($variantData['id']);
            if ($variant) {
                // Track variant price change
                if (floatval($variantData['price']) !== floatval($variantData['original_price'])) {
                    $changes[] = [
                        'action' => 'price_change',
                        'field' => 'price',
                        'old' => '₦' . number_format($variantData['original_price'], 2),
                        'new' => '₦' . number_format($variantData['price'], 2),
                        'variant_id' => $variant->id,
                        'description' => "Variant: {$variantData['name']}",
                    ];
                }
                
                // Track variant stock change
                if (intval($variantData['stock']) !== intval($variantData['original_stock'])) {
                    $changes[] = [
                        'action' => 'stock_update',
                        'field' => 'stock_quantity',
                        'old' => $variantData['original_stock'] . ' units',
                        'new' => $variantData['stock'] . ' units',
                        'variant_id' => $variant->id,
                        'description' => "Variant: {$variantData['name']}",
                    ];
                }
                
                $variant->update([
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock'],
                    'is_active' => $variantData['is_active'],
                ]);
            }
        }
    }
    
    // Log all changes
    foreach ($changes as $change) {
        ProductAuditLog::log(
            $this->product->id,
            $change['action'],
            $change['field'],
            $change['old'],
            $change['new'],
            $change['variant_id'] ?? null,
            $change['description'] ?? null
        );
    }
    
    session()->flash('message', 'Product updated successfully!');
    return $this->redirect('/products', navigate: true);
};

?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/products" wire:navigate class="p-2 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-white">Edit Product</h1>
                <p class="text-sm text-text-secondary mt-0.5">Modify product details and manage variant inventory levels.</p>
            </div>
        </div>
    </div>
</x-slot>

<div class="max-w-4xl mx-auto">
    <div class="pos-card">
        <!-- Tab Navigation -->
        <div class="flex items-center gap-6 px-6 py-4 border-b border-border-dark">
            <button 
                wire:click="setTab('general')"
                class="pb-2 text-sm font-medium transition-colors relative {{ $activeTab === 'general' ? 'text-primary' : 'text-text-secondary hover:text-white' }}"
            >
                General
                @if($activeTab === 'general')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary rounded-full"></div>
                @endif
            </button>
            @if($product->has_variants)
            <button 
                wire:click="setTab('variants')"
                class="pb-2 text-sm font-medium transition-colors relative {{ $activeTab === 'variants' ? 'text-primary' : 'text-text-secondary hover:text-white' }}"
            >
                Variants
                @if($activeTab === 'variants')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary rounded-full"></div>
                @endif
            </button>
            @endif
            <button 
                wire:click="setTab('audit')"
                class="pb-2 text-sm font-medium transition-colors relative {{ $activeTab === 'audit' ? 'text-primary' : 'text-text-secondary hover:text-white' }}"
            >
                Audit Log
                @if($activeTab === 'audit')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary rounded-full"></div>
                @endif
            </button>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            
            <!-- General Tab -->
            @if($activeTab === 'general')
            <div class="space-y-6">
                <!-- Section Header -->
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-sm">info</span>
                    </div>
                    <h3 class="text-lg font-semibold text-white">General Information</h3>
                </div>
                
                <!-- Product Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Product Name</label>
                    <input 
                        wire:model.live="productName" 
                        type="text" 
                        class="w-full h-11 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
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
                            class="w-full h-11 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="PROD-101"
                        />
                        @error('skuPrefix') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Category</label>
                        <select 
                            wire:model="categoryId"
                            class="w-full h-11 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                        >
                            <option value="">Select category</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <!-- Price & Stock for simple products -->
                @if(!$product->has_variants)
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Price (₦)</label>
                        <input 
                            wire:model.live="basePrice" 
                            type="number" 
                            step="0.01"
                            class="w-full h-11 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Stock Quantity</label>
                        <input 
                            wire:model="stockQuantity" 
                            type="number" 
                            min="0"
                            class="w-full h-11 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="0"
                        />
                    </div>
                </div>
                @endif
                
                <!-- Status Toggles -->
                <div class="flex items-center gap-6 pt-2">
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
            @endif
            
            <!-- Variants Tab -->
            @if($activeTab === 'variants' && $product->has_variants)
            <div class="space-y-4">
                <!-- Bulk Actions -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full bg-purple-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-sm">inventory_2</span>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Variant Inventory</h3>
                        <span class="text-sm text-text-secondary">({{ count($variants) }} variants)</span>
                    </div>
                    <div class="flex items-center gap-2"
                        x-data
                        @update-all-prices.window="if($event.detail.price) $wire.updateAllPrices($event.detail.price)"
                        @update-all-stock.window="if($event.detail.stock) $wire.updateAllStock($event.detail.stock)"
                    >
                        <button 
                            @click="$dispatch('update-all-prices', { price: prompt('Enter price for all variants:', '{{ $basePrice }}') })"
                            class="px-3 py-1.5 rounded-lg border border-border-dark text-text-secondary hover:text-white text-xs flex items-center gap-1"
                        >
                            <span class="material-symbols-outlined text-sm">edit</span>
                            Update All Prices
                        </button>
                        <button 
                            @click="$dispatch('update-all-stock', { stock: prompt('Enter stock for all variants:', '0') })"
                            class="px-3 py-1.5 rounded-lg border border-border-dark text-text-secondary hover:text-white text-xs flex items-center gap-1"
                        >
                            <span class="material-symbols-outlined text-sm">inventory</span>
                            Update All Stock
                        </button>
                    </div>
                </div>
                
                <!-- Variants Table -->
                <div class="space-y-2">
                    <!-- Header -->
                    <div class="grid grid-cols-12 gap-4 px-4 py-2 text-xs font-semibold text-text-secondary uppercase tracking-wider">
                        <div class="col-span-4">Variant</div>
                        <div class="col-span-3">SKU</div>
                        <div class="col-span-2">Price (₦)</div>
                        <div class="col-span-2">Stock</div>
                        <div class="col-span-1">Active</div>
                    </div>
                    
                    <!-- Variant Rows -->
                    @foreach($variants as $index => $variant)
                    <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 bg-border-dark/30 rounded-lg {{ !$variant['is_active'] ? 'opacity-50' : '' }}">
                        <div class="col-span-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center text-primary font-bold text-xs">
                                    {{ strtoupper(substr($variant['name'], 0, 2)) }}
                                </div>
                                <span class="text-sm font-medium text-white">{{ $variant['name'] }}</span>
                            </div>
                        </div>
                        
                        <div class="col-span-3">
                            <span class="text-sm font-mono text-text-secondary">{{ $variant['sku'] }}</span>
                        </div>
                        
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
                        
                        <div class="col-span-2">
                            <input 
                                type="number" 
                                value="{{ $variant['stock'] }}"
                                wire:change="updateVariantStock({{ $index }}, $event.target.value)"
                                class="w-full h-8 bg-surface-dark border border-border-dark rounded text-white text-sm px-2 focus:ring-1 focus:ring-primary"
                            />
                        </div>
                        
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
                
                <!-- Summary Stats -->
                <div class="flex items-center gap-8 pt-4 mt-4 border-t border-border-dark">
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
            @endif
            
            <!-- Audit Log Tab -->
            @if($activeTab === 'audit')
            <div class="space-y-4">
                <!-- Section Header -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-6 h-6 rounded-full bg-amber-500 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-sm">history</span>
                    </div>
                    <h3 class="text-lg font-semibold text-white">Audit Log</h3>
                </div>
                
                @if($this->auditLogs->isEmpty())
                <div class="text-center py-12 text-text-secondary">
                    <span class="material-symbols-outlined text-4xl mb-2">history</span>
                    <p>No changes recorded yet</p>
                </div>
                @else
                <!-- Audit Log Table -->
                <div class="space-y-1">
                    <!-- Header -->
                    <div class="grid grid-cols-12 gap-4 px-4 py-2 text-xs font-semibold text-text-secondary uppercase tracking-wider">
                        <div class="col-span-3">Date & Time</div>
                        <div class="col-span-2">User</div>
                        <div class="col-span-2">Action</div>
                        <div class="col-span-5">Changes (Old → New)</div>
                    </div>
                    
                    <!-- Log Entries -->
                    @foreach($this->auditLogs as $log)
                    <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border-b border-border-dark/50">
                        <!-- Date & Time -->
                        <div class="col-span-3">
                            <p class="text-sm text-white">{{ $log->created_at->format('M d, Y') }}</p>
                            <p class="text-xs text-text-secondary">{{ $log->created_at->format('H:i') }}</p>
                        </div>
                        
                        <!-- User -->
                        <div class="col-span-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold">
                                    {{ strtoupper(substr($log->user->name ?? 'U', 0, 2)) }}
                                </div>
                                <span class="text-sm text-white">{{ $log->user->name ?? 'Unknown' }}</span>
                            </div>
                        </div>
                        
                        <!-- Action Badge -->
                        <div class="col-span-2">
                            @php
                                $badgeColors = [
                                    'stock_update' => 'bg-blue-500/20 text-blue-400',
                                    'price_change' => 'bg-orange-500/20 text-orange-400',
                                    'information' => 'bg-green-500/20 text-green-400',
                                    'created' => 'bg-emerald-500/20 text-emerald-400',
                                    'deleted' => 'bg-red-500/20 text-red-400',
                                ];
                                $badgeClass = $badgeColors[$log->action] ?? 'bg-gray-500/20 text-gray-400';
                            @endphp
                            <span class="inline-block px-2 py-1 rounded text-xs font-bold uppercase {{ $badgeClass }}">
                                {{ str_replace('_', ' ', $log->action) }}
                            </span>
                        </div>
                        
                        <!-- Changes -->
                        <div class="col-span-5">
                            @if($log->old_value && $log->new_value)
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-text-secondary line-through">{{ $log->old_value }}</span>
                                <span class="material-symbols-outlined text-text-secondary text-sm">arrow_forward</span>
                                <span class="text-sm text-green-400 font-medium">{{ $log->new_value }}</span>
                            </div>
                            @endif
                            @if($log->description)
                            <p class="text-xs text-text-secondary mt-0.5">{{ $log->description }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>
        
        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border-dark bg-surface-dark/50">
            <a href="/products" wire:navigate class="px-5 py-2.5 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                Cancel
            </a>
            <button 
                wire:click="saveProduct"
                class="pos-button-primary px-5 py-2.5 flex items-center gap-2"
            >
                <span class="material-symbols-outlined text-lg">save</span>
                <span>Save Changes</span>
            </button>
        </div>
    </div>
</div>
