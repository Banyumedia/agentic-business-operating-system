<div class="w-64 bg-gray-900 text-white flex flex-col h-screen fixed">
    <!-- Header Sidebar (Module Name & Back to Lobby) -->
    <div class="p-6 border-b border-gray-800 flex items-center justify-between">
        <div class="flex items-center space-x-3 min-w-0">
            <span class="w-3 h-3 rounded-full {{ $this->accent }} shrink-0" aria-hidden="true"></span>
            <h2 class="text-xl font-extrabold uppercase tracking-widest text-gray-200 truncate">
                {{ strtoupper($module) }}
            </h2>
        </div>
        <a href="{{ route('lobby') }}"
           wire:navigate
           class="p-2 bg-gray-800 rounded hover:bg-gray-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
           title="Kembali ke Lobby"
           aria-label="Kembali ke Lobby">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 hover:text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
            </svg>
        </a>
    </div>

    <!-- Menus -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-2" aria-label="Menu modul {{ $module }}">
        @forelse($this->menus as $menu)
            @php($isActive = request()->is(ltrim($menu['route'], '/')))
            <a href="{{ $menu['route'] }}"
               wire:navigate
               @if($isActive) aria-current="page" @endif
               class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 {{ $isActive ? 'bg-gray-800 text-white font-semibold' : '' }}">
                <span class="text-xl" aria-hidden="true">{{ $menu['icon'] }}</span>
                <span>{{ $menu['label'] }}</span>
            </a>
        @empty
            <p class="px-4 py-3 text-sm text-gray-500">
                Modul ini belum memiliki menu aktif.
            </p>
        @endforelse
    </nav>

    <!-- User Profile / Footer -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center font-bold" aria-hidden="true">
                B
            </div>
            <div>
                <p class="text-sm font-semibold">BOS Admin</p>
                <p class="text-xs text-gray-500">Superuser</p>
            </div>
        </div>
    </div>
</div>
