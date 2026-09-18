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
        
        @if(session()->has('admin_impersonation_id'))
        <div class="bg-yellow-500 text-black px-4 py-2 text-center font-bold sticky top-0 z-50 flex justify-between items-center">
            <span>⚠️ Anda sedang dalam Sesi Bantuan Impersonasi. Segala perubahan akan dicatat.</span>
            <form action="{{ route('admin.impersonate.stop') }}" method="POST">
                @csrf
                <button type="submit" class="bg-black text-white px-3 py-1 rounded text-sm hover:bg-gray-800">Akhiri Sesi Bantuan</button>
            </form>
        </div>
        @endif
        {{ $slot }}
        
        <livewire:command-palette />
        
        @livewireScripts
    </body>
</html>
