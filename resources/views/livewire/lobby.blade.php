<div class="min-h-screen bg-gray-900 text-white flex flex-col items-center justify-center p-6">
    <div class="w-full max-w-4xl">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-extrabold tracking-tight mb-2">Agentic BOS</h1>
            <p class="text-gray-400">Pilih modul untuk memulai sesi kerja Anda.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
            @foreach($apps as $app)
                <a href="{{ route('app.module', ['module' => $app['slug']]) }}"
                   wire:navigate
                   aria-label="Buka aplikasi {{ $app['name'] }}"
                   class="group block p-6 rounded-2xl bg-gray-800 border border-gray-700 hover:border-gray-500 hover:bg-gray-750 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="w-16 h-16 rounded-full {{ $app['color'] }} flex items-center justify-center text-3xl shadow-lg group-hover:scale-110 transition-transform duration-300" aria-hidden="true">
                            {{ $app['icon'] }}
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-100 group-hover:text-white">{{ $app['name'] }}</h3>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <!-- Search Bar (Command Palette Hint) -->
        <div class="mt-16 text-center">
            <button type="button"
                    x-data
                    @click="$dispatch('keydown', new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true }))"
                    aria-label="Buka pencarian universal (Ctrl + K)"
                    aria-keyshortcuts="Control+K Meta+K"
                    class="px-6 py-3 bg-gray-800 border border-gray-700 rounded-full text-gray-400 hover:text-white hover:border-gray-500 transition-colors flex items-center mx-auto space-x-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                </svg>
                <span>Cari kontak, menu, atau tagihan...</span>
                <kbd class="ml-2 px-2 py-1 bg-gray-900 rounded text-xs text-gray-500 font-mono" aria-hidden="true">Ctrl + K</kbd>
            </button>
        </div>
    </div>
</div>
