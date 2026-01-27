<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;

layout('layouts.app');

state([
    'todaySales' => 0,
    'todayTransactions' => 0,
    'totalProducts' => 0,
    'lowStockCount' => 0,
    'recentOrders' => [],
]);

mount(function () {
    // Today's sales
    $this->todaySales = Order::today()->paid()->sum('total');
    $this->todayTransactions = Order::today()->paid()->count();
    
    // Product stats
    $this->totalProducts = Product::active()->count();
    $this->lowStockCount = Product::active()->lowStock()->count();
    
    // Recent orders
    $this->recentOrders = Order::with('user')
        ->latest()
        ->take(5)
        ->get();
});

$formatCurrency = function ($amount) {
    return '₦' . number_format($amount, 2);
};

?>

<x-slot name="header">
    <div>
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>
        <p class="text-sm text-text-secondary mt-1">Welcome back, {{ auth()->user()->name }}! Here's your store overview.</p>
    </div>
</x-slot>

<div class="space-y-6">
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today's Sales -->
        <div class="pos-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-text-secondary">Today's Sales</p>
                    <p class="text-2xl font-bold text-white mt-1">{{ $this->formatCurrency($todaySales) }}</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-500 text-2xl">trending_up</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mt-3 text-xs">
                <span class="text-green-500 font-medium">+12.5%</span>
                <span class="text-text-secondary">vs yesterday</span>
            </div>
        </div>
        
        <!-- Transactions -->
        <div class="pos-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-text-secondary">Transactions</p>
                    <p class="text-2xl font-bold text-white mt-1">{{ $todayTransactions }}</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">receipt_long</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mt-3 text-xs">
                <span class="text-green-500 font-medium">+8 orders</span>
                <span class="text-text-secondary">today</span>
            </div>
        </div>
        
        <!-- Products -->
        <div class="pos-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-text-secondary">Total Products</p>
                    <p class="text-2xl font-bold text-white mt-1">{{ $totalProducts }}</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-purple-500/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-500 text-2xl">inventory_2</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mt-3 text-xs">
                <span class="text-text-secondary">Active in catalog</span>
            </div>
        </div>
        
        <!-- Low Stock Alert -->
        <div class="pos-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-text-secondary">Low Stock Items</p>
                    <p class="text-2xl font-bold text-white mt-1">{{ $lowStockCount }}</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-orange-500/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-orange-500 text-2xl">warning</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mt-3 text-xs">
                @if($lowStockCount > 0)
                    <span class="text-orange-500 font-medium">Needs attention</span>
                @else
                    <span class="text-green-500 font-medium">All stocked</span>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Transactions -->
        <div class="lg:col-span-2 pos-card">
            <div class="p-5 border-b border-border-dark flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Recent Transactions</h2>
                <a href="{{ route('orders.index') }}" wire:navigate class="text-sm text-primary hover:text-primary/80 transition-colors">
                    View all →
                </a>
            </div>
            <div class="divide-y divide-border-dark">
                @forelse($recentOrders as $order)
                <div class="p-4 flex items-center justify-between hover:bg-border-dark/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-border-dark flex items-center justify-center">
                            <span class="material-symbols-outlined text-text-secondary">receipt</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $order->order_number }}</p>
                            <p class="text-xs text-text-secondary">{{ $order->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-white">{{ $order->formatted_total }}</p>
                        <p class="text-xs {{ $order->status === 'paid' ? 'text-green-500' : 'text-orange-500' }}">
                            {{ ucfirst($order->status) }}
                        </p>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">receipt_long</span>
                    <p class="text-text-secondary">No transactions yet</p>
                    <a href="{{ route('pos') }}" wire:navigate class="inline-flex items-center gap-1 mt-3 text-sm text-primary hover:text-primary/80">
                        <span>Start selling</span>
                        <span class="material-symbols-outlined text-lg">arrow_forward</span>
                    </a>
                </div>
                @endforelse
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="pos-card">
            <div class="p-5 border-b border-border-dark">
                <h2 class="text-lg font-semibold text-white">Quick Actions</h2>
            </div>
            <div class="p-4 space-y-3">
                <a href="{{ route('pos') }}" wire:navigate 
                   class="flex items-center gap-3 p-4 rounded-xl bg-primary hover:bg-blue-600 transition-colors group">
                    <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-xl">point_of_sale</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">New Sale</p>
                        <p class="text-xs text-white/70">Open POS terminal</p>
                    </div>
                    <span class="material-symbols-outlined text-white/70 ml-auto group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
                
                <a href="{{ route('products.index') }}" wire:navigate 
                   class="flex items-center gap-3 p-4 rounded-xl bg-border-dark hover:bg-surface-dark transition-colors group">
                    <div class="h-10 w-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-purple-500 text-xl">add_box</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Add Product</p>
                        <p class="text-xs text-text-secondary">Manage inventory</p>
                    </div>
                    <span class="material-symbols-outlined text-text-secondary ml-auto group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
                
                <a href="{{ route('orders.index') }}" wire:navigate 
                   class="flex items-center gap-3 p-4 rounded-xl bg-border-dark hover:bg-surface-dark transition-colors group">
                    <div class="h-10 w-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-500 text-xl">history</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">View Orders</p>
                        <p class="text-xs text-text-secondary">Transaction history</p>
                    </div>
                    <span class="material-symbols-outlined text-text-secondary ml-auto group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
                
                @if(auth()->user()->canAccessReports())
                <a href="{{ route('reports') }}" wire:navigate 
                   class="flex items-center gap-3 p-4 rounded-xl bg-border-dark hover:bg-surface-dark transition-colors group">
                    <div class="h-10 w-10 rounded-lg bg-orange-500/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-orange-500 text-xl">bar_chart</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Sales Reports</p>
                        <p class="text-xs text-text-secondary">Analytics & insights</p>
                    </div>
                    <span class="material-symbols-outlined text-text-secondary ml-auto group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
