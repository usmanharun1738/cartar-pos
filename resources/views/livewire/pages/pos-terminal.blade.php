<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;

layout('layouts.app');

state([
    'search' => '',
    'selectedCategory' => null,
    'cart' => [],
    'showCheckoutModal' => false,
    'cashReceived' => '',
    'orderNotes' => '',
]);

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

$products = computed(function () {
    return Product::query()
        ->active()
        ->inStock()
        ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
        ->when($this->selectedCategory, fn($q) => $q->where('category_id', $this->selectedCategory))
        ->orderBy('name')
        ->get();
});

$cartTotal = computed(function () {
    return collect($this->cart)->sum(fn($item) => $item['price'] * $item['quantity']);
});

$taxAmount = computed(function () {
    return $this->cartTotal * 0.05; // 5% tax
});

$grandTotal = computed(function () {
    return $this->cartTotal + $this->taxAmount;
});

$changeDue = computed(function () {
    $cash = floatval($this->cashReceived);
    return max(0, $cash - $this->grandTotal);
});

$addToCart = function ($productId) {
    $product = Product::find($productId);
    
    if (!$product || $product->stock_quantity <= 0) {
        return;
    }
    
    $cartKey = array_search($productId, array_column($this->cart, 'id'));
    
    if ($cartKey !== false) {
        // Check stock before increasing
        if ($this->cart[$cartKey]['quantity'] < $product->stock_quantity) {
            $this->cart[$cartKey]['quantity']++;
        }
    } else {
        $this->cart[] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => floatval($product->selling_price),
            'quantity' => 1,
            'max_qty' => $product->stock_quantity,
        ];
    }
};

$updateQuantity = function ($index, $quantity) {
    if ($quantity <= 0) {
        $this->removeFromCart($index);
    } else {
        $this->cart[$index]['quantity'] = min($quantity, $this->cart[$index]['max_qty']);
    }
};

$removeFromCart = function ($index) {
    unset($this->cart[$index]);
    $this->cart = array_values($this->cart);
};

$clearCart = function () {
    $this->cart = [];
};

$openCheckout = function () {
    if (count($this->cart) === 0) return;
    $this->cashReceived = '';
    $this->showCheckoutModal = true;
};

$closeCheckout = function () {
    $this->showCheckoutModal = false;
    $this->cashReceived = '';
    $this->orderNotes = '';
};

$processPayment = function () {
    $cash = floatval($this->cashReceived);
    
    if ($cash < $this->grandTotal) {
        return;
    }
    
    // Create Order
    $order = Order::create([
        'user_id' => auth()->id(),
        'subtotal' => $this->cartTotal,
        'discount' => 0,
        'tax_amount' => $this->taxAmount,
        'tax_rate' => 5.00,
        'total' => $this->grandTotal,
        'cash_received' => $cash,
        'change_due' => $this->changeDue,
        'status' => 'paid',
        'notes' => $this->orderNotes,
    ]);
    
    // Create Order Items and update stock
    foreach ($this->cart as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item['id'],
            'product_name' => $item['name'],
            'unit_price' => $item['price'],
            'quantity' => $item['quantity'],
            'discount' => 0,
            'subtotal' => $item['price'] * $item['quantity'],
        ]);
        
        // Decrease stock
        Product::where('id', $item['id'])->decrement('stock_quantity', $item['quantity']);
    }
    
    // Clear cart
    $this->clearCart();
    $this->closeCheckout();
    
    // Show success
    session()->flash('message', 'Order ' . $order->order_number . ' completed successfully!');
};

$formatCurrency = function ($amount) {
    return '₦' . number_format($amount, 2);
};

?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">POS Terminal</h1>
            <p class="text-sm text-text-secondary mt-1">Process sales transactions</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-text-secondary">Cashier:</span>
            <span class="text-sm font-medium text-white">{{ auth()->user()->name }}</span>
        </div>
    </div>
</x-slot>

<div class="flex gap-6 h-[calc(100vh-180px)]">
    <!-- Products Grid -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Categories -->
        <div class="flex items-center gap-2 mb-4 overflow-x-auto pb-2">
            <button 
                wire:click="$set('selectedCategory', null)"
                class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors {{ !$selectedCategory ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
            >
                All Items
            </button>
            @foreach($this->categories as $category)
            <button 
                wire:click="$set('selectedCategory', {{ $category->id }})"
                class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-2 {{ $selectedCategory == $category->id ? 'bg-primary text-white' : 'bg-surface-dark text-text-secondary hover:text-white' }}"
            >
                <span class="material-symbols-outlined text-lg">{{ $category->icon }}</span>
                {{ $category->name }}
            </button>
            @endforeach
        </div>
        
        <!-- Search -->
        <div class="relative mb-4">
            <input 
                wire:model.live.debounce.300ms="search"
                type="text" 
                placeholder="Search products..." 
                class="w-full h-12 bg-surface-dark border border-border-dark rounded-xl text-white text-sm px-4 pl-12 focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
            />
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary">search</span>
        </div>
        
        <!-- Products Grid -->
        <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @forelse($this->products as $product)
                <button 
                    wire:click="addToCart({{ $product->id }})"
                    class="pos-card p-4 text-left hover:border-primary transition-colors group {{ $product->stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}
                >
                    <div class="h-16 w-full rounded-lg bg-border-dark flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-3xl text-text-secondary group-hover:text-primary transition-colors">inventory_2</span>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ $product->category->name }}</p>
                        </div>
                        @if($product->is_hot)
                            <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400">HOT</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-lg font-bold text-primary">{{ $product->formatted_price }}</span>
                        <span class="text-xs {{ $product->stock_quantity <= 5 ? 'text-orange-400' : 'text-text-secondary' }}">
                            {{ $product->stock_quantity }} left
                        </span>
                    </div>
                </button>
                @empty
                <div class="col-span-full py-12 text-center">
                    <span class="material-symbols-outlined text-4xl text-text-secondary mb-2">inventory_2</span>
                    <p class="text-text-secondary">No products found</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Cart Panel -->
    <div class="w-96 bg-surface-dark rounded-xl border border-border-dark flex flex-col">
        <!-- Cart Header -->
        <div class="p-4 border-b border-border-dark flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-white">shopping_cart</span>
                <h2 class="text-lg font-semibold text-white">Current Order</h2>
            </div>
            @if(count($cart) > 0)
            <button wire:click="clearCart" class="text-xs text-red-400 hover:text-red-300 transition-colors">
                Clear All
            </button>
            @endif
        </div>
        
        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse($cart as $index => $item)
            <div class="bg-border-dark/50 rounded-lg p-3">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-sm font-medium text-white">{{ $item['name'] }}</p>
                    <button wire:click="removeFromCart({{ $index }})" class="text-text-secondary hover:text-red-400 transition-colors">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})"
                            class="w-7 h-7 rounded-lg bg-surface-dark flex items-center justify-center text-white hover:bg-primary transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">remove</span>
                        </button>
                        <span class="text-sm font-medium text-white w-8 text-center">{{ $item['quantity'] }}</span>
                        <button 
                            wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                            class="w-7 h-7 rounded-lg bg-surface-dark flex items-center justify-center text-white hover:bg-primary transition-colors {{ $item['quantity'] >= $item['max_qty'] ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ $item['quantity'] >= $item['max_qty'] ? 'disabled' : '' }}
                        >
                            <span class="material-symbols-outlined text-lg">add</span>
                        </button>
                    </div>
                    <p class="text-sm font-bold text-white">{{ $this->formatCurrency($item['price'] * $item['quantity']) }}</p>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center h-full text-center py-12">
                <span class="material-symbols-outlined text-5xl text-text-secondary mb-3">shopping_cart</span>
                <p class="text-text-secondary">Cart is empty</p>
                <p class="text-xs text-text-secondary mt-1">Click products to add them</p>
            </div>
            @endforelse
        </div>
        
        <!-- Cart Summary & Checkout -->
        <div class="border-t border-border-dark p-4 space-y-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-text-secondary">Subtotal</span>
                    <span class="text-white">{{ $this->formatCurrency($this->cartTotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-text-secondary">Tax (5%)</span>
                    <span class="text-white">{{ $this->formatCurrency($this->taxAmount) }}</span>
                </div>
                <div class="flex items-center justify-between text-lg font-bold pt-2 border-t border-border-dark">
                    <span class="text-white">Total</span>
                    <span class="text-primary">{{ $this->formatCurrency($this->grandTotal) }}</span>
                </div>
            </div>
            
            <button 
                wire:click="openCheckout"
                class="w-full h-14 pos-button-primary text-lg font-semibold flex items-center justify-center gap-2 {{ count($cart) === 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ count($cart) === 0 ? 'disabled' : '' }}
            >
                <span class="material-symbols-outlined">payments</span>
                <span>Charge {{ $this->formatCurrency($this->grandTotal) }}</span>
            </button>
        </div>
    </div>
</div>

<!-- Flash Message -->
@if (session('message'))
<div 
    x-data="{ show: true }"
    x-show="show"
    x-init="setTimeout(() => show = false, 4000)"
    class="fixed bottom-6 right-6 bg-green-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3"
>
    <span class="material-symbols-outlined">check_circle</span>
    <span>{{ session('message') }}</span>
</div>
@endif

<!-- Checkout Modal -->
@if($showCheckoutModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div wire:click="closeCheckout" class="fixed inset-0 bg-black/70"></div>
        
        <div class="relative bg-surface-dark rounded-xl border border-border-dark shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-dark">
                <h3 class="text-lg font-semibold text-white">Cash Payment</h3>
                <button wire:click="closeCheckout" class="text-text-secondary hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <!-- Order Summary -->
                <div class="bg-border-dark/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-text-secondary">Items</span>
                        <span class="text-white">{{ count($cart) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-bold text-white">Total Due</span>
                        <span class="text-2xl font-bold text-primary">{{ $this->formatCurrency($this->grandTotal) }}</span>
                    </div>
                </div>
                
                <!-- Cash Input -->
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Cash Received (₦)</label>
                    <input 
                        wire:model.live="cashReceived"
                        type="number" 
                        step="0.01"
                        class="w-full h-14 bg-border-dark border-0 rounded-lg text-white text-2xl font-bold px-4 text-center focus:ring-1 focus:ring-primary"
                        placeholder="0.00"
                        autofocus
                    />
                </div>
                
                <!-- Quick Cash Buttons -->
                <div class="grid grid-cols-4 gap-2">
                    @php
                        $amounts = [500, 1000, 2000, 5000];
                    @endphp
                    @foreach($amounts as $amount)
                    <button 
                        wire:click="$set('cashReceived', {{ $amount }})"
                        class="py-2 rounded-lg bg-border-dark text-white text-sm font-medium hover:bg-primary transition-colors"
                    >
                        ₦{{ number_format($amount) }}
                    </button>
                    @endforeach
                </div>
                
                <!-- Change Due -->
                @if(floatval($cashReceived) >= $this->grandTotal)
                <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 text-center">
                    <p class="text-sm text-green-400 mb-1">Change Due</p>
                    <p class="text-3xl font-bold text-green-400">{{ $this->formatCurrency($this->changeDue) }}</p>
                </div>
                @endif
                
                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-1.5">Order Notes (Optional)</label>
                    <textarea 
                        wire:model="orderNotes"
                        rows="2"
                        class="w-full bg-border-dark border-0 rounded-lg text-white text-sm px-4 py-3 focus:ring-1 focus:ring-primary"
                        placeholder="Add any special notes..."
                    ></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-border-dark">
                <button 
                    wire:click="processPayment"
                    class="w-full h-14 pos-button-primary text-lg font-semibold flex items-center justify-center gap-2 {{ floatval($cashReceived) < $this->grandTotal ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ floatval($cashReceived) < $this->grandTotal ? 'disabled' : '' }}
                >
                    <span class="material-symbols-outlined">check_circle</span>
                    <span>Complete Payment</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
