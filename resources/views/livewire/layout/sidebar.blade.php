<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();
    $this->redirect('/', navigate: true);
};

?>

<aside class="w-64 bg-surface-dark border-r border-border-dark flex flex-col h-full">
    <!-- Logo -->
    <div class="h-16 flex items-center justify-center border-b border-border-dark">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-lg bg-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-xl">point_of_sale</span>
            </div>
            <span class="text-lg font-bold text-white">CARTAR POS</span>
        </a>
    </div>
    
    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <!-- Main Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-text-secondary uppercase tracking-wider">Main</p>
            
            <a href="{{ route('dashboard') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl {{ request()->routeIs('dashboard') ? 'fill' : '' }}">dashboard</span>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            
            <a href="{{ route('pos') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('pos') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl">point_of_sale</span>
                <span class="text-sm font-medium">POS Terminal</span>
            </a>
        </div>
        
        <!-- Management Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-text-secondary uppercase tracking-wider">Management</p>
            
            <a href="{{ route('products.index') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('products.*') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl">inventory_2</span>
                <span class="text-sm font-medium">Products</span>
            </a>
            
            <a href="{{ route('orders.index') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('orders.*') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl">receipt_long</span>
                <span class="text-sm font-medium">Orders</span>
            </a>
            
            @if(auth()->user()->canAccessReports())
            <a href="{{ route('reports') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('reports') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl">bar_chart</span>
                <span class="text-sm font-medium">Reports</span>
            </a>
            @endif
        </div>
        
        <!-- Settings Section (Admin/Manager Only) -->
        @if(auth()->user()->canManageInventory())
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-text-secondary uppercase tracking-wider">Settings</p>
            
            <a href="{{ route('categories.index') }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('categories.*') ? 'bg-primary text-white' : 'text-text-secondary hover:bg-border-dark hover:text-white' }}">
                <span class="material-symbols-outlined text-xl">category</span>
                <span class="text-sm font-medium">Categories</span>
            </a>
        </div>
        @endif
    </nav>
    
    <!-- User Section / Logout -->
    <div class="p-3 border-t border-border-dark">
        <button wire:click="logout" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-text-secondary hover:bg-red-500/10 hover:text-red-400 transition-colors">
            <span class="material-symbols-outlined text-xl">logout</span>
            <span class="text-sm font-medium">Logout</span>
        </button>
    </div>
</aside>
