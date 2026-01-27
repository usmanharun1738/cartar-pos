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
    <body class="font-display bg-background-dark min-h-screen flex flex-col overflow-hidden antialiased">
        <!-- Header / System Status Bar -->
        <div class="w-full flex justify-between items-center px-6 py-3 absolute top-0 left-0 z-10">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                <span class="text-xs font-medium text-text-secondary">System Online</span>
            </div>
            <div class="text-xs font-medium text-text-secondary">v1.0.0</div>
        </div>
        
        <!-- Main Content Area -->
        <div class="flex flex-1 items-center justify-center p-4">
            {{ $slot }}
        </div>
        
        <!-- Background Pattern Decoration (Subtle) -->
        <div class="fixed inset-0 pointer-events-none z-[-1] opacity-[0.03]" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 24px 24px;"></div>
    </body>
</html>

