<?php

use App\Models\VariationType;
use App\Models\VariationOption;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;

layout('layouts.app');

state([
    // Selected type for viewing/editing options
    'selectedTypeId' => null,
    
    // Type Modal
    'showTypeModal' => false,
    'editingType' => null,
    'typeForm' => [
        'name' => '',
        'is_active' => true,
    ],
    
    // Option Modal
    'showOptionModal' => false,
    'editingOption' => null,
    'optionForm' => [
        'name' => '',
        'code' => '',
        'value' => '',
        'is_active' => true,
    ],
    
    // Delete confirmation
    'showDeleteModal' => false,
    'deleteTarget' => null, // { type: 'type' | 'option', id: int }
]);

$variationTypes = computed(function () {
    return VariationType::withCount('options')->ordered()->get();
});

$selectedType = computed(function () {
    if (!$this->selectedTypeId) return null;
    return VariationType::with(['options' => fn($q) => $q->ordered()])->find($this->selectedTypeId);
});

// Type Methods
$selectType = function ($typeId) {
    $this->selectedTypeId = $typeId;
};

$resetTypeForm = function () {
    $this->typeForm = [
        'name' => '',
        'is_active' => true,
    ];
    $this->editingType = null;
};

$openAddTypeModal = function () {
    $this->typeForm = [
        'name' => '',
        'is_active' => true,
    ];
    $this->editingType = null;
    $this->showTypeModal = true;
};

$openEditTypeModal = function ($typeId) {
    $type = VariationType::find($typeId);
    $this->editingType = $type;
    $this->typeForm = [
        'name' => $type->name,
        'is_active' => $type->is_active,
    ];
    $this->showTypeModal = true;
};

$closeTypeModal = function () {
    $this->showTypeModal = false;
    $this->typeForm = [
        'name' => '',
        'is_active' => true,
    ];
    $this->editingType = null;
};

$saveType = function () {
    $this->validate([
        'typeForm.name' => 'required|string|max:100',
    ]);
    
    if ($this->editingType) {
        $this->editingType->update([
            'name' => $this->typeForm['name'],
            'is_active' => $this->typeForm['is_active'],
        ]);
    } else {
        $maxSort = VariationType::max('sort_order') ?? 0;
        VariationType::create([
            'name' => $this->typeForm['name'],
            'is_active' => $this->typeForm['is_active'],
            'sort_order' => $maxSort + 1,
        ]);
    }
    
    $this->closeTypeModal();
};

// Option Methods
$resetOptionForm = function () {
    $this->optionForm = [
        'name' => '',
        'code' => '',
        'value' => '',
        'is_active' => true,
    ];
    $this->editingOption = null;
};

$openAddOptionModal = function () {
    if (!$this->selectedTypeId) return;
    $this->optionForm = [
        'name' => '',
        'code' => '',
        'value' => '',
        'is_active' => true,
    ];
    $this->editingOption = null;
    $this->showOptionModal = true;
};

$openEditOptionModal = function ($optionId) {
    $option = VariationOption::find($optionId);
    $this->editingOption = $option;
    $this->optionForm = [
        'name' => $option->name,
        'code' => $option->code,
        'value' => $option->value ?? '',
        'is_active' => $option->is_active,
    ];
    $this->showOptionModal = true;
};

$closeOptionModal = function () {
    $this->showOptionModal = false;
    $this->optionForm = [
        'name' => '',
        'code' => '',
        'value' => '',
        'is_active' => true,
    ];
    $this->editingOption = null;
};

$saveOption = function () {
    $this->validate([
        'optionForm.name' => 'required|string|max:100',
        'optionForm.code' => 'required|string|max:10',
    ]);
    
    if ($this->editingOption) {
        $this->editingOption->update([
            'name' => $this->optionForm['name'],
            'code' => strtoupper($this->optionForm['code']),
            'value' => $this->optionForm['value'] ?: null,
            'is_active' => $this->optionForm['is_active'],
        ]);
    } else {
        $maxSort = VariationOption::where('variation_type_id', $this->selectedTypeId)->max('sort_order') ?? 0;
        VariationOption::create([
            'variation_type_id' => $this->selectedTypeId,
            'name' => $this->optionForm['name'],
            'code' => strtoupper($this->optionForm['code']),
            'value' => $this->optionForm['value'] ?: null,
            'is_active' => $this->optionForm['is_active'],
            'sort_order' => $maxSort + 1,
        ]);
    }
    
    $this->closeOptionModal();
};

// Delete Methods
$confirmDelete = function ($type, $id) {
    $this->deleteTarget = ['type' => $type, 'id' => $id];
    $this->showDeleteModal = true;
};

$closeDeleteModal = function () {
    $this->showDeleteModal = false;
    $this->deleteTarget = null;
};

$executeDelete = function () {
    if (!$this->deleteTarget) return;
    
    if ($this->deleteTarget['type'] === 'type') {
        $type = VariationType::find($this->deleteTarget['id']);
        if ($type) {
            $type->delete();
            if ($this->selectedTypeId === $this->deleteTarget['id']) {
                $this->selectedTypeId = null;
            }
        }
    } else {
        $option = VariationOption::find($this->deleteTarget['id']);
        if ($option) {
            $option->delete();
        }
    }
    
    $this->closeDeleteModal();
};

// Toggle active status
$toggleTypeActive = function ($typeId) {
    $type = VariationType::find($typeId);
    if ($type) {
        $type->update(['is_active' => !$type->is_active]);
    }
};

$toggleOptionActive = function ($optionId) {
    $option = VariationOption::find($optionId);
    if ($option) {
        $option->update(['is_active' => !$option->is_active]);
    }
};

?>

<x-slot name="header">
    <h1 class="text-2xl font-bold text-white">Variations</h1>
    <p class="text-sm text-text-secondary mt-1">Manage product variation types and options (Size, Color, Material, etc.)</p>
</x-slot>

<div>
<div class="flex gap-6 h-[calc(100vh-220px)]">
    <!-- Left Panel: Variation Types -->
    <div class="w-80 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Variation Types</h2>
            <button wire:click="openAddTypeModal" class="pos-button-primary px-3 py-2 text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-lg">add</span>
                Add Type
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto space-y-2">
            @forelse($this->variationTypes as $type)
            <button 
                wire:click="selectType({{ $type->id }})"
                class="w-full pos-card p-4 text-left transition-colors {{ $selectedTypeId == $type->id ? 'border-primary bg-primary/10' : 'hover:border-border-dark' }}"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $type->is_active ? 'bg-primary/20' : 'bg-border-dark' }} flex items-center justify-center">
                            <span class="material-symbols-outlined {{ $type->is_active ? 'text-primary' : 'text-text-secondary' }}">tune</span>
                        </div>
                        <div>
                            <p class="font-medium {{ $type->is_active ? 'text-white' : 'text-text-secondary' }}">{{ $type->name }}</p>
                            <p class="text-xs text-text-secondary">{{ $type->options_count }} options</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-text-secondary">chevron_right</span>
                </div>
            </button>
            @empty
            <div class="py-12 text-center">
                <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">tune</span>
                <p class="text-text-secondary">No variation types yet</p>
                <p class="text-xs text-text-secondary mt-1">Add your first variation type</p>
            </div>
            @endforelse
        </div>
    </div>
    
    <!-- Right Panel: Options for Selected Type -->
    <div class="flex-1 pos-card flex flex-col overflow-hidden">
        @if($this->selectedType)
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">tune</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">{{ $this->selectedType->name }} Options</h3>
                    <p class="text-xs text-text-secondary">{{ $this->selectedType->options->count() }} options configured</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="openEditTypeModal({{ $this->selectedType->id }})" class="p-2 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors">
                    <span class="material-symbols-outlined">edit</span>
                </button>
                <button wire:click="confirmDelete('type', {{ $this->selectedType->id }})" class="p-2 rounded-lg hover:bg-red-500/10 text-text-secondary hover:text-red-400 transition-colors">
                    <span class="material-symbols-outlined">delete</span>
                </button>
                <button wire:click="openAddOptionModal" class="pos-button-primary px-3 py-2 text-sm flex items-center gap-1 ml-2">
                    <span class="material-symbols-outlined text-lg">add</span>
                    Add Option
                </button>
            </div>
        </div>
        
        <!-- Options List -->
        <div class="flex-1 overflow-y-auto p-6">
            <div class="space-y-2">
                @forelse($this->selectedType->options as $option)
                <div class="flex items-center justify-between p-4 bg-border-dark/30 rounded-lg hover:bg-border-dark/50 transition-colors group">
                    <div class="flex items-center gap-4">
                        <!-- Color Preview (if applicable) -->
                        @if($this->selectedType->slug === 'color' && $option->value)
                        <div class="w-8 h-8 rounded-lg border border-border-dark" style="background-color: {{ $option->value }}"></div>
                        @else
                        <div class="w-8 h-8 rounded-lg bg-surface-dark flex items-center justify-center">
                            <span class="text-xs font-bold text-primary">{{ strtoupper(substr($option->code, 0, 2)) }}</span>
                        </div>
                        @endif
                        
                        <div>
                            <p class="font-medium {{ $option->is_active ? 'text-white' : 'text-text-secondary line-through' }}">{{ $option->name }}</p>
                            <p class="text-xs text-text-secondary">
                                Code: <span class="font-mono text-primary">{{ $option->code }}</span>
                                @if($option->value)
                                • Value: {{ $option->value }}
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button 
                            wire:click="toggleOptionActive({{ $option->id }})"
                            class="p-2 rounded-lg hover:bg-surface-dark text-text-secondary hover:text-white transition-colors"
                            title="{{ $option->is_active ? 'Deactivate' : 'Activate' }}"
                        >
                            <span class="material-symbols-outlined">{{ $option->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                        </button>
                        <button 
                            wire:click="openEditOptionModal({{ $option->id }})"
                            class="p-2 rounded-lg hover:bg-surface-dark text-text-secondary hover:text-white transition-colors"
                        >
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                        <button 
                            wire:click="confirmDelete('option', {{ $option->id }})"
                            class="p-2 rounded-lg hover:bg-red-500/10 text-text-secondary hover:text-red-400 transition-colors"
                        >
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </div>
                @empty
                <div class="py-12 text-center">
                    <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">list</span>
                    <p class="text-text-secondary">No options yet</p>
                    <p class="text-xs text-text-secondary mt-1">Add options like "Small", "Medium", "Large"</p>
                </div>
                @endforelse
            </div>
        </div>
        @else
        <!-- Empty State -->
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <span class="material-symbols-outlined text-6xl text-text-secondary mb-4">tune</span>
                <h3 class="text-lg font-semibold text-white mb-2">Select a Variation Type</h3>
                <p class="text-text-secondary">Choose a type from the left to manage its options</p>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Type Modal -->
@if($showTypeModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div wire:click="closeTypeModal" class="fixed inset-0 bg-black/70"></div>
        
        <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                <h3 class="text-lg font-semibold text-white">
                    {{ $editingType ? 'Edit Variation Type' : 'Add Variation Type' }}
                </h3>
                <button wire:click="closeTypeModal" class="text-text-secondary hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Type Name</label>
                    <input 
                        wire:model="typeForm.name" 
                        type="text" 
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="e.g. Size, Color, Flavor"
                    />
                    @error('typeForm.name') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>
                
                <div class="flex items-center gap-3">
                    <input 
                        wire:model="typeForm.is_active"
                        type="checkbox" 
                        id="typeActive"
                        class="w-4 h-4 rounded bg-border-dark border-border-dark text-primary focus:ring-primary"
                    />
                    <label for="typeActive" class="text-sm text-gray-200">Active</label>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-border-dark">
                <button wire:click="closeTypeModal" class="px-4 py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                    Cancel
                </button>
                <button wire:click="saveType" class="pos-button-primary px-4 py-2 text-sm">
                    {{ $editingType ? 'Save Changes' : 'Add Type' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Option Modal -->
@if($showOptionModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div wire:click="closeOptionModal" class="fixed inset-0 bg-black/70"></div>
        
        <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                <h3 class="text-lg font-semibold text-white">
                    {{ $editingOption ? 'Edit Option' : 'Add Option' }}
                </h3>
                <button wire:click="closeOptionModal" class="text-text-secondary hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Option Name</label>
                    <input 
                        wire:model="optionForm.name" 
                        type="text" 
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="e.g. Small, Size 42, Royal Blue"
                    />
                    @error('optionForm.name') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">
                        Code <span class="text-text-secondary">(for SKU generation)</span>
                    </label>
                    <input 
                        wire:model="optionForm.code" 
                        type="text" 
                        maxlength="10"
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 font-mono uppercase focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="e.g. S, 42, RB"
                    />
                    <p class="text-xs text-text-secondary mt-1">Max 10 characters. Used in product SKUs.</p>
                    @error('optionForm.code') <span class="text-xs text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">
                        Value <span class="text-text-secondary">(optional)</span>
                    </label>
                    <input 
                        wire:model="optionForm.value" 
                        type="text" 
                        class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                        placeholder="e.g. #FF5733 for colors"
                    />
                    <p class="text-xs text-text-secondary mt-1">Optional. Use hex codes for colors.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <input 
                        wire:model="optionForm.is_active"
                        type="checkbox" 
                        id="optionActive"
                        class="w-4 h-4 rounded bg-border-dark border-border-dark text-primary focus:ring-primary"
                    />
                    <label for="optionActive" class="text-sm text-gray-200">Active</label>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-border-dark">
                <button wire:click="closeOptionModal" class="px-4 py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                    Cancel
                </button>
                <button wire:click="saveOption" class="pos-button-primary px-4 py-2 text-sm">
                    {{ $editingOption ? 'Save Changes' : 'Add Option' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Delete Confirmation Modal -->
@if($showDeleteModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div wire:click="closeDeleteModal" class="fixed inset-0 bg-black/70"></div>
        
        <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-red-400 text-2xl">warning</span>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Confirm Delete</h3>
                <p class="text-text-secondary text-sm mb-6">
                    @if($deleteTarget && $deleteTarget['type'] === 'type')
                        This will delete the variation type and all its options. This action cannot be undone.
                    @else
                        Are you sure you want to delete this option? This action cannot be undone.
                    @endif
                </p>
                <div class="flex justify-center gap-3">
                    <button wire:click="closeDeleteModal" class="px-4 py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-surface-dark transition-colors">
                        Cancel
                    </button>
                    <button wire:click="executeDelete" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-medium hover:bg-red-600 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>
