<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CARTAR POS') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-display bg-background-dark min-h-screen antialiased">
        <div class="flex h-screen overflow-hidden">
            <!-- Sidebar Navigation -->
            <livewire:layout.sidebar />
            
            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Header -->
                @if (isset($header))
                <header class="bg-surface-dark border-b border-border-dark px-6 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <!-- Page Header Content (from slot) -->
                        <div class="flex-1 min-w-0">
                            {{ $header }}
                        </div>
                        <!-- Right side controls -->
                        <div class="flex items-center gap-4 flex-shrink-0">
                            <!-- Search Bar -->
                            <div class="relative hidden lg:block">
                                <input 
                                    type="text" 
                                    placeholder="Search..." 
                                    class="w-64 h-10 bg-border-dark border-0 rounded-lg text-white text-sm px-4 pl-10 focus:ring-1 focus:ring-primary placeholder:text-text-secondary"
                                />
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">search</span>
                            </div>
                            
                            <!-- Notifications -->
                            <button class="relative p-2 rounded-lg bg-border-dark hover:bg-surface-dark transition-colors">
                                <span class="material-symbols-outlined text-text-secondary text-xl">notifications</span>
                                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                            </button>
                            
                            <!-- Settings -->
                            <button class="p-2 rounded-lg bg-border-dark hover:bg-surface-dark transition-colors">
                                <span class="material-symbols-outlined text-text-secondary text-xl">settings</span>
                            </button>
                            
                            <!-- User Profile -->
                            <div class="flex items-center gap-3 pl-4 border-l border-border-dark">
                                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm">
                                    {{ substr(auth()->user()->name, 0, 2) }}
                                </div>
                                <div class="hidden xl:block">
                                    <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-text-secondary capitalize">{{ auth()->user()->role }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                @endif

                <!-- Page Content -->
                <main class="flex-1 overflow-auto p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
