<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme ?? 'a' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Agentic BOS' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen">
        {{ $slot }}
        
        <livewire:command-palette />
        
        @livewireScripts
    </body>
</html>
