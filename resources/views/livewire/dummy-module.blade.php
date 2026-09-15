<div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 capitalize">{{ $module }} : {{ str_replace('-', ' ', $path) }}</h1>
        <p class="text-gray-500 mt-2">Ini adalah halaman tiruan (dummy) untuk meninjau UI sebelum dihubungkan dengan database.</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center space-x-4">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xl">
                📈
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Data</p>
                <p class="text-2xl font-bold text-gray-900">1,204</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center space-x-4">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xl">
                ✅
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Selesai Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">45</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center space-x-4">
            <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xl">
                ⚠️
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Perlu Perhatian</p>
                <p class="text-2xl font-bold text-gray-900">12</p>
            </div>
        </div>
    </div>

    <!-- Dummy Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">Data Terbaru</h3>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                + Tambah Baru
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-sm">
                        <th class="p-4 font-medium border-b border-gray-100">ID</th>
                        <th class="p-4 font-medium border-b border-gray-100">Nama</th>
                        <th class="p-4 font-medium border-b border-gray-100">Status</th>
                        <th class="p-4 font-medium border-b border-gray-100">Tanggal</th>
                        <th class="p-4 font-medium border-b border-gray-100">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100">
                    @for($i=1; $i<=5; $i++)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="p-4 font-mono text-gray-500">#{{ 1000 + $i }}</td>
                        <td class="p-4 font-medium text-gray-900">Data Contoh Ke-{{ $i }}</td>
                        <td class="p-4">
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">Aktif</span>
                        </td>
                        <td class="p-4 text-gray-500">24 Okt 2026</td>
                        <td class="p-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900 font-medium mr-2">Edit</a>
                            <a href="#" class="text-red-600 hover:text-red-900 font-medium">Hapus</a>
                        </td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>
