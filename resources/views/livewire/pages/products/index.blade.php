<?php

use App\Models\Product;
use App\Models\Category;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\on;

layout('layouts.app');

state([
    'search' => '',
    'categoryFilter' => '',
    'stockFilter' => 'all', // all, low, out
    'showModal' => false,
    'editingProduct' => null,
    // Form fields
    'form' => [
        'name' => '',
        'sku' => '',
        'category_id' => '',
        'description' => '',
        'cost_price' => '',
        'selling_price' => '',
        'stock_quantity' => '',
        'low_stock_threshold' => 5,
        'is_active' => true,
        'is_hot' => false,
    ],
]);

$products = computed(function () {
    return Product::query()
        ->with('category')
        ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
            ->orWhere('sku', 'like', '%' . $this->search . '%'))
        ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
        ->when($this->stockFilter === 'low', fn($q) => $q->lowStock())
        ->when($this->stockFilter === 'out', fn($q) => $q->outOfStock())
        ->orderBy('name')
        ->paginate(10);
});

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

$openAddModal = function () {
    $this->resetForm();
    $this->editingProduct = null;
    $this->showModal = true;
};

$openEditModal = function ($productId) {
    $product = Product::find($productId);
    $this->editingProduct = $product;
    $this->form = [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'description' => $product->description ?? '',
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'stock_quantity' => $product->stock_quantity,
        'low_stock_threshold' => $product->low_stock_threshold,
        'is_active' => $product->is_active,
        'is_hot' => $product->is_hot,
    ];
    $this->showModal = true;
};

$closeModal = function () {
    $this->showModal = false;
    $this->resetForm();
};

$resetForm = function () {
    $this->form = [
        'name' => '',
        'sku' => '',
        'category_id' => '',
        'description' => '',
        'cost_price' => '',
        'selling_price' => '',
        'stock_quantity' => '',
        'low_stock_threshold' => 5,
        'is_active' => true,
        'is_hot' => false,
    ];
};

$saveProduct = function () {
    $validated = $this->validate([
        'form.name' => 'required|string|max:255',
        'form.sku' => 'required|string|max:50|unique:products,sku,' . ($this->editingProduct?->id ?? 'NULL'),
        'form.category_id' => 'required|exists:categories,id',
        'form.cost_price' => 'required|numeric|min:0',
        'form.selling_price' => 'required|numeric|min:0',
        'form.stock_quantity' => 'required|integer|min:0',
        'form.low_stock_threshold' => 'required|integer|min:0',
    ]);

    $data = [
        'name' => $this->form['name'],
        'sku' => $this->form['sku'],
        'category_id' => $this->form['category_id'],
        'description' => $this->form['description'],
        'cost_price' => $this->form['cost_price'],
        'selling_price' => $this->form['selling_price'],
        'stock_quantity' => $this->form['stock_quantity'],
        'low_stock_threshold' => $this->form['low_stock_threshold'],
        'is_active' => $this->form['is_active'],
        'is_hot' => $this->form['is_hot'],
    ];

    if ($this->editingProduct) {
        $this->editingProduct->update($data);
    } else {
        Product::create($data);
    }

    $this->closeModal();
};

$deleteProduct = function ($productId) {
    Product::find($productId)?->delete();
};

$formatCurrency = function ($amount) {
    return '₦' . number_format($amount, 2);
};

?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Products</h1>
            <p class="text-sm text-text-secondary mt-1">Manage your inventory</p>
        </div>
        <button wire:click="openAddModal" class="pos-button-primary px-4 py-2.5 flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">add</span>
            <span>Add Product</span>
        </button>
    </div>
</x-slot>

<div>
    <!-- Filters -->
    <div class="pos-card p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    wire:model.live.debounce.300ms="search"
                    type="text" 
                    placeholder="Search products..." 
                    class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 pl-10 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                />
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">search</span>
            </div>
            
            <!-- Category Filter -->
            <select 
                wire:model.live="categoryFilter"
                class="h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary min-w-[150px]"
            >
                <option value="">All Categories</option>
                @foreach($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            
            <!-- Stock Filter -->
            <div class="flex rounded-lg overflow-hidden border border-border-dark">
                <button 
                    wire:click="$set('stockFilter', 'all')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $stockFilter === 'all' ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    All Items
                </button>
                <button 
                    wire:click="$set('stockFilter', 'low')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $stockFilter === 'low' ? 'bg-orange-500 text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    Low Stock
                </button>
                <button 
                    wire:click="$set('stockFilter', 'out')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $stockFilter === 'out' ? 'bg-red-500 text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    Out of Stock
                </button>
            </div>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="pos-card overflow-hidden">
        <table class="w-full">
            <thead class="bg-border-dark/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">SKU</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Stock</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-text-secondary uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-dark">
                @forelse($this->products as $product)
                <tr class="hover:bg-border-dark/30 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-border-dark flex items-center justify-center">
                                <span class="material-symbols-outlined text-text-secondary">inventory_2</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">{{ $product->name }}</p>
                                @if($product->is_hot)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400">HOT</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-text-secondary">{{ $product->category->name }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm font-mono text-text-secondary">{{ $product->sku }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm font-semibold text-white">{{ $product->formatted_price }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm font-medium {{ $product->stock_status === 'out_of_stock' ? 'text-red-400' : ($product->stock_status === 'low_stock' ? 'text-orange-400' : 'text-green-400') }}">
                            {{ $product->stock_quantity }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($product->stock_status === 'out_of_stock')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">Out of Stock</span>
                        @elseif($product->stock_status === 'low_stock')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-500/20 text-orange-400">Low Stock</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400">In Stock</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button 
                                wire:click="openEditModal({{ $product->id }})"
                                class="p-1.5 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors"
                            >
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button 
                                wire:click="deleteProduct({{ $product->id }})"
                                wire:confirm="Are you sure you want to delete this product?"
                                class="p-1.5 rounded-lg hover:bg-red-500/10 text-text-secondary hover:text-red-400 transition-colors"
                            >
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center">
                        <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">inventory_2</span>
                        <p class="text-text-secondary">No products found</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Pagination -->
        @if($this->products->hasPages())
        <div class="px-4 py-3 border-t border-border-dark">
            {{ $this->products->links() }}
        </div>
        @endif
    </div>
    
    <!-- Add/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <!-- Backdrop -->
            <div wire:click="closeModal" class="fixed inset-0 bg-black/70 transition-opacity"></div>
            
            <!-- Modal Panel -->
            <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-lg transform transition-all">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                    <h3 class="text-lg font-semibold text-white">
                        {{ $editingProduct ? 'Edit Product' : 'Add Product' }}
                    </h3>
                    <button wire:click="closeModal" class="text-text-secondary hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <!-- Form -->
                <form wire:submit="saveProduct" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Name -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Product Name</label>
                            <input 
                                wire:model="form.name" 
                                type="text" 
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="Enter product name"
                            />
                            @error('form.name') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- SKU -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">SKU</label>
                            <input 
                                wire:model="form.sku" 
                                type="text" 
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="e.g., PRD001"
                            />
                            @error('form.sku') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Category -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Category</label>
                            <select 
                                wire:model="form.category_id"
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                            >
                                <option value="">Select category</option>
                                @foreach($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('form.category_id') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Cost Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Cost Price (₦)</label>
                            <input 
                                wire:model="form.cost_price" 
                                type="number" 
                                step="0.01"
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="0.00"
                            />
                            @error('form.cost_price') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Selling Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Selling Price (₦)</label>
                            <input 
                                wire:model="form.selling_price" 
                                type="number" 
                                step="0.01"
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="0.00"
                            />
                            @error('form.selling_price') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Stock Quantity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Stock Quantity</label>
                            <input 
                                wire:model="form.stock_quantity" 
                                type="number" 
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="0"
                            />
                            @error('form.stock_quantity') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Low Stock Threshold -->
                        <div>
                            <label class="block text-sm font-medium text-gray-200 mb-1.5">Low Stock Alert</label>
                            <input 
                                wire:model="form.low_stock_threshold" 
                                type="number" 
                                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                                placeholder="5"
                            />
                        </div>
                        
                        <!-- Toggles -->
                        <div class="col-span-2 flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    wire:model="form.is_active" 
                                    type="checkbox" 
                                    class="w-4 h-4 rounded bg-border-dark border-0 text-primary focus:ring-primary focus:ring-offset-0"
                                />
                                <span class="text-sm text-gray-200">Active</span>
                            </label>
                            
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    wire:model="form.is_hot" 
                                    type="checkbox" 
                                    class="w-4 h-4 rounded bg-border-dark border-0 text-red-500 focus:ring-red-500 focus:ring-offset-0"
                                />
                                <span class="text-sm text-gray-200">Mark as HOT</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border-dark">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="pos-button-primary px-4 py-2">
                            {{ $editingProduct ? 'Update Product' : 'Add Product' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
