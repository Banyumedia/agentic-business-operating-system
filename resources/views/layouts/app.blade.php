<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Agentic BOS' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-gray-900 text-gray-100 font-sans antialiased min-h-screen">
        {{ $slot }}
        
        <livewire:command-palette />
        
        @livewireScripts
    </body>
</html>
