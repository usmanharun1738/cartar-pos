<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    
    // POS Terminal
    Volt::route('pos', 'pages.pos-terminal')->name('pos');
    
    // Products
    Volt::route('products', 'pages.products.index')->name('products.index');
    
    // Orders
    Volt::route('orders', 'pages.orders.index')->name('orders.index');
    Volt::route('orders/{order}', 'pages.orders.show')->name('orders.show');
    
    // Reports (Admin/Manager only)
    Volt::route('reports', 'pages.reports')->name('reports');
    
    // Categories (Admin/Manager only)
    Volt::route('categories', 'pages.categories.index')->name('categories.index');
    
    // Profile
    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
