<div class="w-64 bg-gray-900 text-white flex flex-col h-screen fixed">
    <!-- Header Sidebar (Module Name & Back to Lobby) -->
    <div class="p-6 border-b border-gray-800 flex items-center justify-between">
        <h2 class="text-xl font-extrabold uppercase tracking-widest text-gray-200">
            {{ strtoupper($module) }}
        </h2>
        <a href="{{ route('lobby') }}" class="p-2 bg-gray-800 rounded hover:bg-gray-700 transition" title="Kembali ke Lobby">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 hover:text-white" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
            </svg>
        </a>
    </div>

    <!-- Menus -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-2">
        @foreach($this->menus as $menu)
            <a href="{{ $menu['route'] }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors {{ request()->is(ltrim($menu['route'], '/')) ? 'bg-gray-800 text-white font-semibold' : '' }}">
                <span class="text-xl">{{ $menu['icon'] }}</span>
                <span>{{ $menu['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <!-- User Profile / Footer -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center font-bold">
                B
            </div>
            <div>
                <p class="text-sm font-semibold">BOS Admin</p>
                <p class="text-xs text-gray-500">Superuser</p>
            </div>
        </div>
    </div>
</div>
