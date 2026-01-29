<?php

use App\Models\Product;
use App\Models\ProductVariant;
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
    // Receipt modal
    'showReceiptModal' => false,
    'completedOrder' => null,
]);

$categories = computed(function () {
    return Category::active()->ordered()->get();
});

// Get all sellable items (simple products + variants)
$sellableItems = computed(function () {
    $items = collect();
    
    // Get products
    $products = Product::query()
        ->with(['category', 'variants', 'images'])
        ->active()
        ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
        ->when($this->selectedCategory, fn($q) => $q->where('category_id', $this->selectedCategory))
        ->orderBy('name')
        ->get();
    
    foreach ($products as $product) {
        if ($product->has_variants && $product->variants->count() > 0) {
            // Add each variant as a sellable item
            foreach ($product->variants->where('is_active', true) as $variant) {
                if ($variant->stock_quantity > 0) {
                    $items->push([
                        'type' => 'variant',
                        'id' => $variant->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'variant_name' => $variant->variant_name,
                        'sku' => $variant->sku,
                        'price' => floatval($variant->price),
                        'formatted_price' => $variant->formatted_price,
                        'stock' => $variant->stock_quantity,
                        'category' => $product->category->name,
                        'is_hot' => $product->is_hot,
                        'image' => $product->primary_image_url,
                    ]);
                }
            }
        } else {
            // Add simple product
            if ($product->stock_quantity > 0) {
                $items->push([
                    'type' => 'product',
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'price' => floatval($product->selling_price),
                    'formatted_price' => $product->formatted_price,
                    'stock' => $product->stock_quantity,
                    'category' => $product->category->name,
                    'is_hot' => $product->is_hot,
                    'image' => $product->primary_image_url,
                ]);
            }
        }
    }
    
    return $items;
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

// Add item to cart (handles both products and variants)
$addToCart = function ($type, $id) {
    if ($type === 'variant') {
        $variant = ProductVariant::with('product')->find($id);
        if (!$variant || $variant->stock_quantity <= 0) return;
        
        $cartKey = 'variant_' . $id;
        $existingIndex = array_search($cartKey, array_column($this->cart, 'cart_key'));
        
        if ($existingIndex !== false) {
            if ($this->cart[$existingIndex]['quantity'] < $variant->stock_quantity) {
                $this->cart[$existingIndex]['quantity']++;
            }
        } else {
            $this->cart[] = [
                'cart_key' => $cartKey,
                'type' => 'variant',
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $variant->product->name,
                'variant_name' => $variant->variant_name,
                'price' => floatval($variant->price),
                'quantity' => 1,
                'max_qty' => $variant->stock_quantity,
            ];
        }
    } else {
        $product = Product::find($id);
        if (!$product || $product->stock_quantity <= 0) return;
        
        $cartKey = 'product_' . $id;
        $existingIndex = array_search($cartKey, array_column($this->cart, 'cart_key'));
        
        if ($existingIndex !== false) {
            if ($this->cart[$existingIndex]['quantity'] < $product->stock_quantity) {
                $this->cart[$existingIndex]['quantity']++;
            }
        } else {
            $this->cart[] = [
                'cart_key' => $cartKey,
                'type' => 'product',
                'id' => $product->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'variant_name' => null,
                'price' => floatval($product->selling_price),
                'quantity' => 1,
                'max_qty' => $product->stock_quantity,
            ];
        }
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
    
    // Store cart items for receipt before clearing
    $cartItems = $this->cart;
    
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
    foreach ($cartItems as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item['product_id'],
            'product_name' => $item['name'] . ($item['variant_name'] ? ' - ' . $item['variant_name'] : ''),
            'unit_price' => $item['price'],
            'quantity' => $item['quantity'],
            'discount' => 0,
            'subtotal' => $item['price'] * $item['quantity'],
        ]);
        
        // Decrease stock based on type
        if ($item['type'] === 'variant') {
            ProductVariant::where('id', $item['id'])->decrement('stock_quantity', $item['quantity']);
        } else {
            Product::where('id', $item['id'])->decrement('stock_quantity', $item['quantity']);
        }
    }
    
    // Store completed order for receipt
    $this->completedOrder = [
        'order_number' => $order->order_number,
        'date' => $order->created_at->format('M d, Y'),
        'time' => $order->created_at->format('h:i A'),
        'cashier' => auth()->user()->name,
        'items' => $cartItems,
        'subtotal' => $this->cartTotal,
        'tax' => $this->taxAmount,
        'total' => $this->grandTotal,
        'cash_received' => $cash,
        'change_due' => $this->changeDue,
    ];
    
    // Clear cart and close checkout
    $this->clearCart();
    $this->closeCheckout();
    
    // Show receipt modal
    $this->showReceiptModal = true;
};

$closeReceipt = function () {
    $this->showReceiptModal = false;
    $this->completedOrder = null;
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

<div>
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
                @forelse($this->sellableItems as $item)
                <button 
                    wire:click="addToCart('{{ $item['type'] }}', {{ $item['id'] }})"
                    class="pos-card p-4 text-left hover:border-primary transition-colors group"
                >
                    <div class="h-16 w-full rounded-lg bg-border-dark flex items-center justify-center mb-3 overflow-hidden">
                        @if($item['image'])
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                        @else
                            <span class="material-symbols-outlined text-3xl text-text-secondary group-hover:text-primary transition-colors">inventory_2</span>
                        @endif
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $item['name'] }}</p>
                            @if($item['variant_name'])
                                <p class="text-xs text-primary truncate">{{ $item['variant_name'] }}</p>
                            @endif
                            <p class="text-xs text-text-secondary">{{ $item['category'] }}</p>
                        </div>
                        @if($item['is_hot'])
                            <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400">HOT</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-lg font-bold text-primary">{{ $item['formatted_price'] }}</span>
                        <span class="text-xs {{ $item['stock'] <= 5 ? 'text-orange-400' : 'text-text-secondary' }}">
                            {{ $item['stock'] }} left
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
                    <div>
                        <p class="text-sm font-medium text-white">{{ $item['name'] }}</p>
                        @if($item['variant_name'])
                            <p class="text-xs text-text-secondary">{{ $item['variant_name'] }}</p>
                        @endif
                    </div>
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

{{-- Receipt Modal --}}
@if($showReceiptModal && $completedOrder)
<div class="fixed inset-0 z-50 overflow-y-auto print:relative print:overflow-visible" aria-modal="true">
    <!-- Backdrop (hidden on print) -->
    <div class="fixed inset-0 bg-black/70 print:hidden" wire:click="closeReceipt"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 print:block print:min-h-0 print:px-0">
        <div class="relative bg-white w-full max-w-sm rounded-xl shadow-2xl print:shadow-none print:rounded-none print:max-w-none" id="receipt-content">
            
            <!-- Close button (hidden on print) -->
            <button wire:click="closeReceipt" class="absolute top-4 right-4 p-2 rounded-lg hover:bg-gray-100 text-gray-500 print:hidden">
                <span class="material-symbols-outlined">close</span>
            </button>
            
            <!-- Receipt Content -->
            <div class="p-6 print:p-2 print:text-xs">
                
                <!-- Store Header -->
                <div class="text-center mb-6 print:mb-3">
                    <h2 class="text-xl font-bold text-gray-900 print:text-base">CARTAR POS</h2>
                    <p class="text-sm text-gray-600 print:text-xs">123 Business Street</p>
                    <p class="text-sm text-gray-600 print:text-xs">Lagos, Nigeria</p>
                    <p class="text-sm text-gray-600 print:text-xs">Tel: +234 123 456 7890</p>
                </div>
                
                <!-- Divider -->
                <div class="border-t border-dashed border-gray-300 my-4 print:my-2"></div>
                
                <!-- Order Info -->
                <div class="space-y-1 text-sm print:text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order:</span>
                        <span class="font-semibold text-gray-900">{{ $completedOrder['order_number'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date:</span>
                        <span class="text-gray-900">{{ $completedOrder['date'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Time:</span>
                        <span class="text-gray-900">{{ $completedOrder['time'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cashier:</span>
                        <span class="text-gray-900">{{ $completedOrder['cashier'] }}</span>
                    </div>
                </div>
                
                <!-- Divider -->
                <div class="border-t border-dashed border-gray-300 my-4 print:my-2"></div>
                
                <!-- Items -->
                <div class="space-y-2 print:space-y-1">
                    <div class="flex justify-between text-xs font-semibold text-gray-600 uppercase tracking-wide">
                        <span>Item</span>
                        <span>Amount</span>
                    </div>
                    @foreach($completedOrder['items'] as $item)
                    <div class="text-sm print:text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-900 font-medium">
                                {{ $item['name'] }}
                                @if($item['variant_name'])
                                    <span class="text-gray-600 text-xs">- {{ $item['variant_name'] }}</span>
                                @endif
                            </span>
                            <span class="text-gray-900 font-semibold">₦{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $item['quantity'] }} x ₦{{ number_format($item['price'], 2) }}
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <!-- Divider -->
                <div class="border-t border-dashed border-gray-300 my-4 print:my-2"></div>
                
                <!-- Totals -->
                <div class="space-y-2 text-sm print:text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900">₦{{ number_format($completedOrder['subtotal'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax (5%)</span>
                        <span class="text-gray-900">₦{{ number_format($completedOrder['tax'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold print:text-sm">
                        <span class="text-gray-900">TOTAL</span>
                        <span class="text-gray-900">₦{{ number_format($completedOrder['total'], 2) }}</span>
                    </div>
                </div>
                
                <!-- Divider -->
                <div class="border-t border-dashed border-gray-300 my-4 print:my-2"></div>
                
                <!-- Payment Info -->
                <div class="space-y-1 text-sm print:text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cash Received</span>
                        <span class="text-gray-900">₦{{ number_format($completedOrder['cash_received'], 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-green-600">
                        <span>Change</span>
                        <span>₦{{ number_format($completedOrder['change_due'], 2) }}</span>
                    </div>
                </div>
                
                <!-- Divider -->
                <div class="border-t border-dashed border-gray-300 my-4 print:my-2"></div>
                
                <!-- Footer -->
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-900 print:text-xs">Thank you for shopping!</p>
                    <p class="text-xs text-gray-500 mt-1">Please keep this receipt for your records</p>
                </div>
            </div>
            
            <!-- Action Buttons (hidden on print) -->
            <div class="px-6 pb-6 flex gap-3 print:hidden">
                <button 
                    wire:click="closeReceipt"
                    class="flex-1 py-3 rounded-lg bg-gray-100 text-gray-700 font-medium hover:bg-gray-200 transition-colors"
                >
                    Close
                </button>
                <button 
                    onclick="window.print()"
                    class="flex-1 py-3 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition-colors flex items-center justify-center gap-2"
                >
                    <span class="material-symbols-outlined text-lg">print</span>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>
@endif
</div>
