<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;

layout('layouts.guest');

form(LoginForm::class);

state(['showPassword' => false]);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

$togglePassword = function () {
    $this->showPassword = !$this->showPassword;
};

?>

<div class="relative w-full max-w-[480px] flex flex-col rounded-xl bg-surface-dark shadow-2xl border border-border-dark overflow-hidden">
    <!-- Branding / Header Image -->
    <div class="relative h-32 w-full overflow-hidden bg-primary/10">
        <div class="absolute inset-0 bg-gradient-to-b from-primary/20 to-surface-dark"></div>
        <div class="absolute inset-0 flex flex-col items-center justify-center z-10">
            <div class="h-12 w-12 rounded-lg bg-primary flex items-center justify-center text-white mb-2 shadow-lg">
                <span class="material-symbols-outlined text-3xl">point_of_sale</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">CARTAR POS</h1>
        </div>
    </div>
    
    <!-- Form Container -->
    <div class="p-8 pt-4 flex flex-col gap-6">
        <div class="text-center">
            <h2 class="text-xl font-bold text-white">Welcome back</h2>
            <p class="text-sm text-text-secondary mt-1">Please sign in to access the terminal</p>
        </div>
        
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />
        
        <form wire:submit="login" class="flex flex-col gap-5">
            <!-- Email/Employee ID Field -->
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-gray-200" for="login">Employee ID or Email</label>
                <div class="relative flex items-center">
                    <input 
                        wire:model="form.login" 
                        id="login" 
                        type="text" 
                        name="login" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Enter ID or Email"
                        class="w-full rounded-lg border border-border-dark bg-[#11161b] text-white h-12 px-4 pl-11 focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-text-secondary text-base transition-colors"
                    />
                    <div class="absolute left-3 text-text-secondary flex items-center pointer-events-none">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('form.login')" class="mt-1" />
            </div>
            
            <!-- Password Field -->
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-gray-200" for="password">Password</label>
                <div class="relative flex items-center">
                    <input 
                        wire:model="form.password" 
                        id="password" 
                        :type="$showPassword ? 'text' : 'password'"
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="Enter Password"
                        class="w-full rounded-lg border border-border-dark bg-[#11161b] text-white h-12 px-4 pl-11 pr-11 focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-text-secondary text-base transition-colors"
                    />
                    <div class="absolute left-3 text-text-secondary flex items-center pointer-events-none">
                        <span class="material-symbols-outlined text-[20px]">lock</span>
                    </div>
                    <button 
                        type="button" 
                        wire:click="togglePassword"
                        class="absolute right-3 text-text-secondary hover:text-primary transition-colors flex items-center"
                    >
                        <span class="material-symbols-outlined text-[20px]">
                            {{ $showPassword ? 'visibility_off' : 'visibility' }}
                        </span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
                
                @if (Route::has('password.request'))
                    <div class="flex justify-end mt-1">
                        <a 
                            class="text-sm font-medium text-primary hover:text-primary/80 transition-colors" 
                            href="{{ route('password.request') }}" 
                            wire:navigate
                        >
                            Forgot password?
                        </a>
                    </div>
                @endif
            </div>
            
            <!-- Login Button -->
            <button 
                type="submit"
                class="w-full h-12 bg-primary hover:bg-blue-600 text-white font-bold rounded-lg shadow-md hover:shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-2"
            >
                <span>Log In</span>
                <span class="material-symbols-outlined text-[20px]">login</span>
            </button>
        </form>
        
        <!-- Divider -->
        <div class="relative py-2">
            <div class="absolute inset-0 flex items-center">
                <span class="w-full border-t border-border-dark"></span>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-surface-dark px-2 text-text-secondary">Or sign in with</span>
            </div>
        </div>
        
        <!-- Secondary Action - Scan Badge -->
        <button 
            type="button"
            class="w-full h-12 bg-background-dark hover:bg-border-dark text-gray-200 font-medium rounded-lg border border-border-dark transition-all flex items-center justify-center gap-2"
        >
            <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
            <span>Scan Badge</span>
        </button>
    </div>
    
    <!-- Footer -->
    <div class="bg-surface-darker p-4 text-center border-t border-border-dark">
        <p class="text-xs text-text-secondary">
            Need help? Contact Support at <span class="font-medium text-gray-400">support@cartarpos.com</span>
        </p>
    </div>
</div>

