<div>
    <div class="rounded-lg border border-red-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-medium text-red-600">Hapus Data {{ ucfirst(term("contact")) }}</h2>
        <p class="mt-1 text-sm text-gray-500">
            Hapus seluruh data pribadi seorang {{ strtolower(term("contact")) }}. Jejak transaksi (invoice, pesanan) akan dianomimkan untuk kebutuhan pembukuan. Tindakan ini tidak dapat dibatalkan.
        </p>

        <div class="mt-4">
            @if ($feedback)
                <div class="mb-4 rounded p-4 {{ $feedbackType === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                    {{ $feedback }}
                </div>
            @endif

            <div class="grid gap-4 max-w-md">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Lengkap {{ ucfirst(term("contact")) }}</label>
                    <input type="text" wire:model="contactName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ketik YA untuk konfirmasi</label>
                    <input type="text" wire:model="confirmationCode" autocapitalize="characters" autocorrect="off" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm" placeholder="YA">
                </div>

                <div>
                    <button
                        wire:click="erase"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center rounded bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="erase">Hapus Permanen</span>
                        <span wire:loading wire:target="erase">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
