<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme ?? 'a' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Agentic BOS' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased h-screen flex overflow-hidden">
        
        <!-- Sidebar Navigation (Fixed on the left) -->
        <livewire:sidebar :module="request()->segment(2)" />

        <!-- Main Content Area -->
        <main id="main-content" class="flex-1 ml-64 overflow-y-auto p-4 sm:p-8">
            <div class="max-w-7xl mx-auto">
                {{ $slot }}
            </div>
        </main>

        <livewire:command-palette />

        @livewireScripts
    </body>
</html>
