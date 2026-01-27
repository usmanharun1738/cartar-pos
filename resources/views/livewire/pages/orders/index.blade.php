<?php

use App\Models\Order;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;

layout('layouts.app');

state([
    'search' => '',
    'statusFilter' => '',
    'dateFilter' => 'today', // today, week, month, all
    'selectedOrder' => null,
]);

$orders = computed(function () {
    return Order::query()
        ->with('user', 'items')
        ->when($this->search, fn($q) => $q->where('order_number', 'like', '%' . $this->search . '%'))
        ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
        ->when($this->dateFilter === 'today', fn($q) => $q->whereDate('created_at', today()))
        ->when($this->dateFilter === 'week', fn($q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]))
        ->when($this->dateFilter === 'month', fn($q) => $q->whereMonth('created_at', now()->month))
        ->latest()
        ->paginate(15);
});

$viewOrder = function ($orderId) {
    $this->selectedOrder = Order::with('user', 'items.product')->find($orderId);
};

$closeOrderDetail = function () {
    $this->selectedOrder = null;
};

$formatCurrency = function ($amount) {
    return '₦' . number_format($amount, 2);
};

?>

<x-slot name="header">
    <div>
        <h1 class="text-2xl font-bold text-white">Orders</h1>
        <p class="text-sm text-text-secondary mt-1">View and manage transaction history</p>
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
                    placeholder="Search order number..." 
                    class="w-full h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 pl-10 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                />
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">search</span>
            </div>
            
            <!-- Status Filter -->
            <select 
                wire:model.live="statusFilter"
                class="h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 focus:ring-1 focus:ring-primary min-w-[130px]"
            >
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
                <option value="refund">Refund</option>
            </select>
            
            <!-- Date Filter -->
            <div class="flex rounded-lg overflow-hidden border border-border-dark">
                <button 
                    wire:click="$set('dateFilter', 'today')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $dateFilter === 'today' ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    Today
                </button>
                <button 
                    wire:click="$set('dateFilter', 'week')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $dateFilter === 'week' ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    This Week
                </button>
                <button 
                    wire:click="$set('dateFilter', 'month')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $dateFilter === 'month' ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    This Month
                </button>
                <button 
                    wire:click="$set('dateFilter', 'all')"
                    class="px-4 py-2 text-sm font-medium transition-colors {{ $dateFilter === 'all' ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
                >
                    All Time
                </button>
            </div>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="pos-card overflow-hidden">
        <table class="w-full">
            <thead class="bg-border-dark/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Cashier</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-text-secondary uppercase tracking-wider">Items</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Total</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-text-secondary uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-dark">
                @forelse($this->orders as $order)
                <tr class="hover:bg-border-dark/30 transition-colors">
                    <td class="px-4 py-3">
                        <span class="text-sm font-semibold text-white">{{ $order->order_number }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-text-secondary">{{ $order->user->name }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm text-text-secondary">{{ $order->items->count() }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm font-semibold text-white">{{ $order->formatted_total }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($order->status === 'paid')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400">Paid</span>
                        @elseif($order->status === 'pending')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-500/20 text-orange-400">Pending</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">Refund</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm text-text-secondary">{{ $order->created_at->format('M d, H:i') }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button 
                            wire:click="viewOrder({{ $order->id }})"
                            class="p-1.5 rounded-lg hover:bg-border-dark text-text-secondary hover:text-white transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center">
                        <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">receipt_long</span>
                        <p class="text-text-secondary">No orders found</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Pagination -->
        @if($this->orders->hasPages())
        <div class="px-4 py-3 border-t border-border-dark">
            {{ $this->orders->links() }}
        </div>
        @endif
    </div>
    
    <!-- Order Detail Modal -->
    @if($selectedOrder)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div wire:click="closeOrderDetail" class="fixed inset-0 bg-black/70"></div>
            
            <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark sticky top-0 bg-surface-dark">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ $selectedOrder->order_number }}</h3>
                        <p class="text-sm text-text-secondary">{{ $selectedOrder->created_at->format('F d, Y - H:i') }}</p>
                    </div>
                    <button wire:click="closeOrderDetail" class="text-text-secondary hover:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Order Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-border-dark/50 rounded-lg p-3">
                            <p class="text-xs text-text-secondary mb-1">Cashier</p>
                            <p class="text-sm font-medium text-white">{{ $selectedOrder->user->name }}</p>
                        </div>
                        <div class="bg-border-dark/50 rounded-lg p-3">
                            <p class="text-xs text-text-secondary mb-1">Status</p>
                            <p class="text-sm font-medium text-green-400">{{ ucfirst($selectedOrder->status) }}</p>
                        </div>
                    </div>
                    
                    <!-- Items -->
                    <div>
                        <h4 class="text-sm font-medium text-text-secondary mb-2">Items</h4>
                        <div class="space-y-2">
                            @foreach($selectedOrder->items as $item)
                            <div class="bg-border-dark/50 rounded-lg p-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-white">{{ $item->product_name }}</p>
                                    <p class="text-xs text-text-secondary">{{ $item->quantity }} × {{ $this->formatCurrency($item->unit_price) }}</p>
                                </div>
                                <p class="text-sm font-semibold text-white">{{ $this->formatCurrency($item->subtotal) }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="border-t border-border-dark pt-4 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text-secondary">Subtotal</span>
                            <span class="text-white">{{ $this->formatCurrency($selectedOrder->subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text-secondary">Tax ({{ $selectedOrder->tax_rate }}%)</span>
                            <span class="text-white">{{ $this->formatCurrency($selectedOrder->tax_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-lg font-bold pt-2 border-t border-border-dark">
                            <span class="text-white">Total</span>
                            <span class="text-primary">{{ $selectedOrder->formatted_total }}</span>
                        </div>
                    </div>
                    
                    <!-- Payment Info -->
                    <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-green-400">Cash Received</span>
                            <span class="text-sm font-medium text-white">{{ $this->formatCurrency($selectedOrder->cash_received) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-green-400">Change Given</span>
                            <span class="text-sm font-medium text-white">{{ $this->formatCurrency($selectedOrder->change_due) }}</span>
                        </div>
                    </div>
                    
                    @if($selectedOrder->notes)
                    <div class="bg-border-dark/50 rounded-lg p-3">
                        <p class="text-xs text-text-secondary mb-1">Notes</p>
                        <p class="text-sm text-white">{{ $selectedOrder->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
