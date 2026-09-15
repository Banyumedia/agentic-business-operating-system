<div 
    x-data="{ isOpen: false }" 
    @keydown.window.ctrl.k.prevent="isOpen = true; $nextTick(() => $refs.searchInput.focus())"
    @keydown.window.meta.k.prevent="isOpen = true; $nextTick(() => $refs.searchInput.focus())"
    @keydown.escape.window="isOpen = false"
>
    <!-- Background Overlay -->
    <div 
        x-show="isOpen" 
        x-transition.opacity.duration.300ms
        class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-start justify-center pt-20 sm:pt-32"
        style="display: none;"
    >
        <!-- Modal Panel -->
        <div 
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="isOpen = false"
            class="bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl ring-1 ring-gray-700 overflow-hidden mx-4"
        >
            <!-- Search Input -->
            <div class="relative border-b border-gray-700">
                <svg class="pointer-events-none absolute left-4 top-4 h-6 w-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    x-ref="searchInput"
                    class="h-14 w-full bg-transparent pl-12 pr-4 text-white placeholder-gray-400 focus:outline-none focus:ring-0 sm:text-lg"
                    placeholder="Cari apa saja... (Mis: Budi, Invoice, HRD)"
                >
                <div class="absolute right-4 top-4 text-xs font-mono text-gray-500 bg-gray-900 px-2 py-1 rounded">
                    ESC
                </div>
            </div>

            <!-- Results List -->
            <div class="max-h-96 overflow-y-auto p-2">
                @if(strlen($search) < 2)
                    <div class="p-8 text-center text-gray-500">
                        Mulai mengetik untuk mencari data kontak, tagihan, atau menu...
                    </div>
                @elseif(count($results) > 0)
                    <ul class="space-y-1">
                        @foreach($results as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex items-center p-3 rounded-lg hover:bg-indigo-600 transition group">
                                <span class="text-2xl mr-4">{{ $item['icon'] }}</span>
                                <div class="flex-1">
                                    <h4 class="text-white font-medium group-hover:text-white">{{ $item['title'] }}</h4>
                                    <p class="text-sm text-gray-400 group-hover:text-indigo-200">{{ $item['type'] }} &bull; {{ $item['module'] }}</p>
                                </div>
                                <svg class="h-5 w-5 text-gray-500 group-hover:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="p-8 text-center text-gray-500">
                        Tidak ditemukan hasil untuk "<span class="text-gray-300">{{ $search }}</span>".
                    </div>
                @endif
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-900/50 p-4 border-t border-gray-700 text-xs text-gray-500 flex justify-between">
                <div>Gunakan <kbd class="font-mono bg-gray-700 text-gray-300 px-1 rounded">↑</kbd> <kbd class="font-mono bg-gray-700 text-gray-300 px-1 rounded">↓</kbd> untuk navigasi</div>
                <div>Tekan <kbd class="font-mono bg-gray-700 text-gray-300 px-1 rounded">Enter</kbd> untuk memilih</div>
            </div>
        </div>
    </div>
</div>
