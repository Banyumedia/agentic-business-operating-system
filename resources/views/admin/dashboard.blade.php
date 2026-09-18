<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-6">Super Admin Dashboard</h1>
    
    <table class="w-full border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 p-2 text-left">Company ID</th>
                <th class="border border-gray-300 p-2 text-left">Nama Company</th>
                <th class="border border-gray-300 p-2 text-left">Owner</th>
                <th class="border border-gray-300 p-2 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $company)
            <tr>
                <td class="border border-gray-300 p-2">{{ $company->id }}</td>
                <td class="border border-gray-300 p-2">{{ $company->name }}</td>
                <td class="border border-gray-300 p-2">{{ $company->owner->name ?? '-' }}</td>
                <td class="border border-gray-300 p-2 text-center">
                    <form action="{{ route('admin.impersonate', $company->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">Login As</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
