<?php

use App\Models\Category;
use Illuminate\Support\Str;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;

layout('layouts.app');

state([
    'search' => '',
    'showModal' => false,
    'editingCategory' => null,
    'form' => [
        'name' => '',
        'icon' => 'category',
        'sort_order' => 0,
        'is_active' => true,
    ],
]);

// Available Material Symbols icons for different store types
$availableIcons = computed(function () {
    return [
        // General
        'category', 'inventory_2', 'storefront', 'shopping_bag', 'sell',
        // Food & Beverage
        'local_cafe', 'restaurant', 'cookie', 'cake', 'dining', 'local_bar', 'coffee', 'bakery_dining', 'ramen_dining', 'fastfood',
        // Fashion & Apparel
        'checkroom', 'dry_cleaning', 'styler', 'watch', 'diamond',
        // Electronics
        'phone_android', 'laptop', 'headphones', 'tv', 'videogame_asset', 'smart_toy', 'memory', 'cable',
        // Hardware & Tools
        'construction', 'handyman', 'plumbing', 'electrical_services', 'bolt', 'hardware',
        // Home & Living
        'chair', 'bed', 'kitchen', 'blender', 'iron', 'air', 'light',
        // Health & Beauty
        'spa', 'self_improvement', 'face', 'sanitizer', 'medication',
        // Sports & Outdoor
        'sports_soccer', 'fitness_center', 'hiking', 'directions_bike', 'pool',
        // Books & Stationery
        'menu_book', 'edit_note', 'draw', 'palette',
        // Other
        'redeem', 'local_shipping', 'recycling', 'pets', 'child_care', 'eco',
    ];
});

$categories = computed(function () {
    return Category::query()
        ->withCount('products')
        ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();
});

$openAddModal = function () {
    $this->resetForm();
    $this->editingCategory = null;
    $this->showModal = true;
};

$openEditModal = function ($categoryId) {
    $category = Category::find($categoryId);
    $this->editingCategory = $category;
    $this->form = [
        'name' => $category->name,
        'icon' => $category->icon ?? 'category',
        'sort_order' => $category->sort_order,
        'is_active' => $category->is_active,
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
        'icon' => 'category',
        'sort_order' => 0,
        'is_active' => true,
    ];
    $this->editingCategory = null;
};

$saveCategory = function () {
    $this->validate([
        'form.name' => 'required|string|max:255',
        'form.icon' => 'required|string',
        'form.sort_order' => 'required|integer|min:0',
    ]);

    $data = [
        'name' => $this->form['name'],
        'slug' => Str::slug($this->form['name']),
        'icon' => $this->form['icon'],
        'sort_order' => $this->form['sort_order'],
        'is_active' => $this->form['is_active'],
    ];

    if ($this->editingCategory) {
        $this->editingCategory->update($data);
    } else {
        Category::create($data);
    }

    $this->closeModal();
};

$deleteCategory = function ($categoryId) {
    $category = Category::withCount('products')->find($categoryId);
    
    if ($category && $category->products_count === 0) {
        $category->delete();
    }
};

$toggleActive = function ($categoryId) {
    $category = Category::find($categoryId);
    if ($category) {
        $category->update(['is_active' => !$category->is_active]);
    }
};

?>

<x-slot name="header">
    <h1 class="text-2xl font-bold text-white">Categories</h1>
    <p class="text-sm text-text-secondary mt-1">Organize your products by category - works for any store type</p>
</x-slot>

<div>
    <!-- Page Header with Action Button -->
    <div class="flex items-center justify-end mb-6">
        <button wire:click="openAddModal" class="pos-button-primary px-4 py-2.5 flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">add</span>
            <span>Add Category</span>
        </button>
    </div>

    <!-- Info Banner -->
    <div class="pos-card p-4 mb-6 border-l-4 border-primary">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-xl">info</span>
            <div>
                <p class="text-sm font-medium text-white">Universal Category System</p>
                <p class="text-xs text-text-secondary mt-1">
                    Create categories for any type of store: fashion boutique, hardware store, electronics shop, grocery, restaurant, and more. 
                    Choose from 50+ icons to match your business.
                </p>
            </div>
        </div>
    </div>


    <!-- Search -->
    <div class="pos-card p-4 mb-6">
        <div class="relative max-w-md">
            <input 
                wire:model.live.debounce.300ms="search"
                type="text" 
                placeholder="Search categories..." 
                class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 pl-10 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
            />
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">search</span>
        </div>
    </div>
    
    <!-- Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($this->categories as $category)
        <div class="pos-card p-4 {{ !$category->is_active ? 'opacity-50' : '' }}">
            <div class="flex items-start justify-between mb-3">
                <div class="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">{{ $category->icon ?? 'category' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <!-- Toggle Active -->
                    <button 
                        wire:click="toggleActive({{ $category->id }})"
                        class="p-1.5 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors"
                        title="{{ $category->is_active ? 'Deactivate' : 'Activate' }}"
                    >
                        <span class="material-symbols-outlined text-lg">{{ $category->is_active ? 'visibility' : 'visibility_off' }}</span>
                    </button>
                    <!-- Edit -->
                    <button 
                        wire:click="openEditModal({{ $category->id }})"
                        class="p-1.5 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors"
                    >
                        <span class="material-symbols-outlined text-lg">edit</span>
                    </button>
                    <!-- Delete (only if no products) -->
                    @if($category->products_count === 0)
                    <button 
                        wire:click="deleteCategory({{ $category->id }})"
                        wire:confirm="Are you sure you want to delete this category?"
                        class="p-1.5 rounded-lg hover:bg-red-500/10 text-text-secondary hover:text-red-400 transition-colors"
                    >
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                    @endif
                </div>
            </div>
            
            <h3 class="text-base font-semibold text-white mb-1">{{ $category->name }}</h3>
            <div class="flex items-center justify-between">
                <span class="text-sm text-text-secondary">{{ $category->products_count }} products</span>
                @if(!$category->is_active)
                    <span class="text-xs text-orange-400">Inactive</span>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-full py-12 text-center pos-card">
            <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">category</span>
            <p class="text-text-secondary">No categories found</p>
            <button wire:click="openAddModal" class="mt-3 text-sm text-primary hover:text-primary/80">
                Create your first category →
            </button>
        </div>
        @endforelse
    </div>
    
    <!-- Add/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div wire:click="closeModal" class="fixed inset-0 bg-black/70"></div>
            
            <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-lg">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                    <h3 class="text-lg font-semibold text-white">
                        {{ $editingCategory ? 'Edit Category' : 'Add Category' }}
                    </h3>
                    <button wire:click="closeModal" class="text-text-secondary hover:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <!-- Form -->
                <form wire:submit="saveCategory" class="p-6 space-y-5">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Category Name</label>
                        <input 
                            wire:model="form.name" 
                            type="text" 
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                            placeholder="e.g., Electronics, Clothing, Food, Tools..."
                        />
                        @error('form.name') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Icon Picker -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-2">Icon</label>
                        <div class="grid grid-cols-8 gap-2 max-h-48 overflow-y-auto p-2 bg-border-dark/50 rounded-lg">
                            @foreach($this->availableIcons as $icon)
                            <button 
                                type="button"
                                wire:click="$set('form.icon', '{{ $icon }}')"
                                class="h-10 w-10 rounded-lg flex items-center justify-center transition-colors {{ $form['icon'] === $icon ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:bg-border-dark hover:text-white' }}"
                                title="{{ $icon }}"
                            >
                                <span class="material-symbols-outlined text-lg">{{ $icon }}</span>
                            </button>
                            @endforeach
                        </div>
                        <p class="text-xs text-text-secondary mt-2">Selected: <span class="text-white">{{ $form['icon'] }}</span></p>
                    </div>
                    
                    <!-- Sort Order -->
                    <div>
                        <label class="block text-sm font-medium text-gray-200 mb-1.5">Display Order</label>
                        <input 
                            wire:model="form.sort_order" 
                            type="number" 
                            min="0"
                            class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary"
                            placeholder="0"
                        />
                        <p class="text-xs text-text-secondary mt-1">Lower numbers appear first in the POS terminal</p>
                    </div>
                    
                    <!-- Active Toggle -->
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input 
                                wire:model="form.is_active" 
                                type="checkbox" 
                                class="w-4 h-4 rounded bg-border-dark border-0 text-primary focus:ring-primary focus:ring-offset-0"
                            />
                            <span class="text-sm text-gray-200">Active</span>
                        </label>
                        <span class="text-xs text-text-secondary">(visible in POS terminal)</span>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border-dark">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="pos-button-primary px-4 py-2">
                            {{ $editingCategory ? 'Update Category' : 'Create Category' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
