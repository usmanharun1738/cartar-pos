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
    'step' => 1, // 1: General, 2: Options, 3: Variants
    // General Information
    'productName' => '',
    'skuPrefix' => '',
    'categoryId' => '',
    'basePrice' => '',
    
    // Product Options
    'selectedOptions' => [], // ['size' => [1, 2, 3], 'color' => [4, 5]]
    
    // Generated Variants
    'variants' => [], // Array of auto-generated variants with price/stock
    
    // State
    'showAddOptionModal' => false,
    'currentOptionType' => null,
]);

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

$variationTypes = computed(function () {
    return VariationType::with('options')->active()->ordered()->get();
});

// Get options for a specific type
$getOptionsForType = function ($typeId) {
    return VariationOption::where('variation_type_id', $typeId)
        ->active()
        ->ordered()
        ->get();
};

// Toggle an option selection
$toggleOption = function ($typeSlug, $optionId) {
    if (!isset($this->selectedOptions[$typeSlug])) {
        $this->selectedOptions[$typeSlug] = [];
    }
    
    if (in_array($optionId, $this->selectedOptions[$typeSlug])) {
        $this->selectedOptions[$typeSlug] = array_values(
            array_filter($this->selectedOptions[$typeSlug], fn($id) => $id != $optionId)
        );
    } else {
        $this->selectedOptions[$typeSlug][] = $optionId;
    }
    
    // Regenerate variants when options change
    $this->generateVariants();
};

// Remove an option from selection
$removeOption = function ($typeSlug, $optionId) {
    if (isset($this->selectedOptions[$typeSlug])) {
        $this->selectedOptions[$typeSlug] = array_values(
            array_filter($this->selectedOptions[$typeSlug], fn($id) => $id != $optionId)
        );
    }
    $this->generateVariants();
};

// Clear all options for a type
$clearOptions = function ($typeSlug) {
    unset($this->selectedOptions[$typeSlug]);
    $this->generateVariants();
};

// Generate all variant combinations
$generateVariants = function () {
    if (empty($this->selectedOptions)) {
        $this->variants = [];
        return;
    }
    
    // Get all option arrays
    $optionArrays = [];
    foreach ($this->selectedOptions as $typeSlug => $optionIds) {
        if (!empty($optionIds)) {
            $options = VariationOption::whereIn('id', $optionIds)
                ->with('type')
                ->get()
                ->map(fn($o) => ['id' => $o->id, 'name' => $o->name, 'code' => $o->code, 'type' => $typeSlug])
                ->toArray();
            if (!empty($options)) {
                $optionArrays[] = $options;
            }
        }
    }
    
    if (empty($optionArrays)) {
        $this->variants = [];
        return;
    }
    
    // Generate all combinations using cartesian product
    $combinations = $this->cartesianProduct($optionArrays);
    
    // Create variant entries
    $basePrice = floatval($this->basePrice) ?: 0;
    $prefix = $this->skuPrefix ?: 'PRD';
    
    $this->variants = [];
    foreach ($combinations as $combo) {
        $optionIds = array_column($combo, 'id');
        $names = array_column($combo, 'name');
        $codes = array_column($combo, 'code');
        
        $sku = $prefix . '-' . implode('-', $codes);
        $variantName = implode(' / ', $names);
        
        $this->variants[] = [
            'option_ids' => $optionIds,
            'sku' => $sku,
            'name' => $variantName,
            'price' => $basePrice,
            'stock' => 0,
        ];
    }
};

// Cartesian product helper
$cartesianProduct = function ($arrays) {
    $result = [[]];
    foreach ($arrays as $array) {
        $newResult = [];
        foreach ($result as $combo) {
            foreach ($array as $item) {
                $newResult[] = array_merge($combo, [$item]);
            }
        }
        $result = $newResult;
    }
    return $result;
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

// Remove a variant
$removeVariant = function ($index) {
    unset($this->variants[$index]);
    $this->variants = array_values($this->variants);
};

// Save Product with Variants
$saveProduct = function () {
    // Validate
    $this->validate([
        'productName' => 'required|string|max:255',
        'skuPrefix' => 'required|string|max:20',
        'categoryId' => 'required|exists:categories,id',
    ]);
    
    if (empty($this->variants)) {
        // Save as simple product
        Product::create([
            'name' => $this->productName,
            'sku' => $this->skuPrefix,
            'sku_prefix' => $this->skuPrefix,
            'category_id' => $this->categoryId,
            'selling_price' => floatval($this->basePrice) ?: 0,
            'cost_price' => 0,
            'stock_quantity' => 0,
            'is_active' => true,
            'has_variants' => false,
        ]);
    } else {
        // Save product with variants
        $product = Product::create([
            'name' => $this->productName,
            'sku' => $this->skuPrefix,
            'sku_prefix' => $this->skuPrefix,
            'category_id' => $this->categoryId,
            'selling_price' => floatval($this->basePrice) ?: 0,
            'cost_price' => 0,
            'stock_quantity' => 0,
            'is_active' => true,
            'has_variants' => true,
        ]);
        
        // Create variants
        foreach ($this->variants as $variantData) {
            $variant = $product->variants()->create([
                'sku' => $variantData['sku'],
                'variant_name' => $variantData['name'],
                'price' => $variantData['price'],
                'stock_quantity' => $variantData['stock'],
                'is_active' => true,
            ]);
            
            // Attach options
            $variant->options()->attach($variantData['option_ids']);
        }
    }
    
    // Redirect back to products
    return $this->redirect('/products', navigate: true);
};

// Get selected option objects for display
$getSelectedOptionObjects = function ($typeSlug) {
    if (!isset($this->selectedOptions[$typeSlug]) || empty($this->selectedOptions[$typeSlug])) {
        return collect([]);
    }
    return VariationOption::whereIn('id', $this->selectedOptions[$typeSlug])->get();
};

?>

<x-slot name="header">
    <div class="flex items-center gap-3">
        <a href="/products" class="p-2 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white">Add New Product</h1>
            <p class="text-sm text-text-secondary mt-1">Create a new item and its variants for your inventory.</p>
        </div>
    </div>
</x-slot>

<div class="max-w-4xl mx-auto">
    <!-- Form Sections -->
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
                <div class="w-1/2">
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Base Price (₦)</label>
                    <input 
                        wire:model.live="basePrice" 
                        type="number" 
                        step="0.01"
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="0.00"
                    />
                </div>
            </div>
        </div>
        
        <!-- Section 2: Product Options -->
        <div class="pos-card">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-lg">tune</span>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Product Options</h2>
                </div>
            </div>
            <div class="p-6 space-y-4">
                @foreach($this->variationTypes as $type)
                <div class="bg-border-dark/30 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-gray-200">OPTION: {{ strtoupper($type->name) }}</span>
                        @if(isset($selectedOptions[$type->slug]) && count($selectedOptions[$type->slug]) > 0)
                        <button 
                            wire:click="clearOptions('{{ $type->slug }}')"
                            class="p-1.5 rounded-lg hover:bg-surface-dark text-text-secondary hover:text-red-400 transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                        @endif
                    </div>
                    
                    <!-- Selected Options as Chips -->
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($this->getSelectedOptionObjects($type->slug) as $option)
                        <div class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-white text-sm">
                            @if($type->slug === 'color' && $option->value)
                                <span class="w-3 h-3 rounded-full border border-white/30" style="background-color: {{ $option->value }}"></span>
                            @endif
                            {{ $option->name }}
                            <button 
                                wire:click="removeOption('{{ $type->slug }}', {{ $option->id }})"
                                class="ml-1 hover:text-red-200"
                            >
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                        </div>
                        @endforeach
                        
                        <!-- Add Option Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button 
                                @click="open = !open"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-dashed border-border-dark text-text-secondary hover:text-white hover:border-primary text-sm transition-colors"
                            >
                                <span class="material-symbols-outlined text-lg">add</span>
                                Add {{ strtolower($type->name) }}...
                            </button>
                            
                            <div 
                                x-show="open" 
                                @click.away="open = false"
                                x-transition
                                class="absolute z-10 mt-2 w-48 bg-surface-dark border border-border-dark rounded-lg shadow-xl"
                            >
                                <div class="p-2 max-h-48 overflow-y-auto">
                                    @foreach($type->options as $option)
                                        @if(!isset($selectedOptions[$type->slug]) || !in_array($option->id, $selectedOptions[$type->slug]))
                                        <button 
                                            wire:click="toggleOption('{{ $type->slug }}', {{ $option->id }})"
                                            @click="open = false"
                                            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-left hover:bg-border-dark text-text-secondary hover:text-white transition-colors"
                                        >
                                            @if($type->slug === 'color' && $option->value)
                                                <span class="w-4 h-4 rounded-full border border-border-dark" style="background-color: {{ $option->value }}"></span>
                                            @endif
                                            {{ $option->name }}
                                        </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        
        <!-- Section 3: Variant Inventory -->
        @if(count($variants) > 0)
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
                            <div class="col-span-1"></div>
                        </div>
                        
                        <!-- Variant Rows -->
                        @foreach($variants as $index => $variant)
                        <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 bg-border-dark/30 rounded-lg">
                            <!-- Variant Info -->
                            <div class="col-span-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center text-primary font-bold text-xs">
                                        {{ strtoupper(substr($variant['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ $variant['name'] }}</p>
                                        <p class="text-xs text-text-secondary">Standard Fit</p>
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
                            
                            <!-- Delete -->
                            <div class="col-span-1 text-right">
                                <button 
                                    wire:click="removeVariant({{ $index }})"
                                    class="p-1.5 rounded-lg hover:bg-red-500/10 text-text-secondary hover:text-red-400 transition-colors"
                                >
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 py-6">
            <a href="/products" class="px-4 py-2.5 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                Cancel
            </a>
            <button 
                wire:click="saveProduct"
                class="pos-button-primary px-6 py-2.5 flex items-center gap-2"
            >
                <span class="material-symbols-outlined text-lg">save</span>
                <span>Create Product & Variants</span>
            </button>
        </div>
    </div>
</div>
