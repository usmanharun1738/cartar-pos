<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariationType;
use App\Models\VariationOption;
use Livewire\WithFileUploads;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use function Livewire\Volt\uses;

uses([WithFileUploads::class]);

layout('layouts.app');

state([
    'step' => 1, // 1: General, 2: Options, 3: Variants
    // General Information
    'productName' => '',
    'skuPrefix' => '',
    'categoryId' => '',
    'basePrice' => '',
    'stockQuantity' => 0,
    
    // Product Images (max 3)
    'productImages' => [],
    
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

// Remove an image from upload queue
$removeImage = function ($index) {
    $images = $this->productImages;
    unset($images[$index]);
    $this->productImages = array_values($images);
};

// Save Product with Variants
$saveProduct = function () {
    // Validate - skuPrefix is now optional for simple products
    $this->validate([
        'productName' => 'required|string|max:255',
        'skuPrefix' => 'nullable|string|max:20',
        'categoryId' => 'required|exists:categories,id',
        'productImages.*' => 'image|max:2048', // 2MB max
    ]);
    
    // Create product data
    $productData = [
        'name' => $this->productName,
        'category_id' => $this->categoryId,
        'selling_price' => floatval($this->basePrice) ?: 0,
        'cost_price' => 0,
        'stock_quantity' => intval($this->stockQuantity) ?: 0,
        'is_active' => true,
        'has_variants' => !empty($this->variants),
    ];
    
    // Only set SKU if provided
    if (!empty($this->skuPrefix)) {
        $productData['sku'] = $this->skuPrefix;
        $productData['sku_prefix'] = $this->skuPrefix;
    }
    
    $product = Product::create($productData);
    
    // Save images
    if (!empty($this->productImages)) {
        foreach ($this->productImages as $index => $image) {
            $path = $image->store("products/{$product->id}", 'public');
            
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'filename' => $image->getClientOriginalName(),
                'is_primary' => $index === 0, // First image is primary
                'sort_order' => $index,
            ]);
        }
    }
    
    // Create variants if any
    if (!empty($this->variants)) {
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
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">SKU <span class="text-text-secondary font-normal">(Optional - auto-generates)</span></label>
                        <input 
                            wire:model.live="skuPrefix" 
                            type="text" 
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="Leave blank to auto-generate"
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
                
                <!-- Base Price & Stock Quantity -->
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
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Stock Quantity <span class="text-text-secondary font-normal">(for simple products)</span></label>
                        <input 
                            wire:model="stockQuantity" 
                            type="number" 
                            min="0"
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                            placeholder="0"
                        />
                    </div>
                </div>
                
                <!-- Product Images -->
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">
                        Product Images 
                        <span class="text-text-secondary font-normal">(Max 3, up to 2MB each)</span>
                    </label>
                    
                    @if(count($productImages) < 3)
                    <div class="relative">
                        <input 
                            type="file" 
                            wire:model="productImages"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        />
                        <div class="border-2 border-dashed border-border-dark rounded-lg p-6 text-center hover:border-primary/50 transition-colors">
                            <span class="material-symbols-outlined text-3xl text-text-secondary mb-2">add_photo_alternate</span>
                            <p class="text-sm text-text-secondary">Click or drag images here</p>
                            <p class="text-xs text-text-secondary mt-1">JPG, PNG, or WebP</p>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Image Preview Grid -->
                    @if(!empty($productImages))
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        @foreach($productImages as $index => $image)
                        <div class="relative group rounded-lg overflow-hidden bg-border-dark aspect-square">
                            <img 
                                src="{{ $image->temporaryUrl() }}" 
                                alt="Preview {{ $index + 1 }}"
                                class="w-full h-full object-cover"
                            />
                            @if($index === 0)
                            <span class="absolute top-2 left-2 px-2 py-0.5 bg-primary text-white text-xs font-medium rounded">
                                Primary
                            </span>
                            @endif
                            <button 
                                type="button"
                                wire:click="removeImage({{ $index }})"
                                class="absolute top-2 right-2 p-1 bg-red-500 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    
                    @error('productImages.*') 
                    <span class="text-xs text-red-400 mt-1">{{ $message }}</span> 
                    @enderror
                    
                    <div wire:loading wire:target="productImages" class="text-sm text-primary mt-2">
                        <span class="material-symbols-outlined text-sm animate-spin">refresh</span>
                        Uploading...
                    </div>
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
