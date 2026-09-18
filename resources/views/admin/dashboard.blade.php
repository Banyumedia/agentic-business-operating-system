<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-4">Panel Super Admin</h1>
    <table class="w-full text-left border-collapse border">
        <thead>
            <tr>
                <th class="border p-2">Company</th>
                <th class="border p-2">Owner</th>
                <th class="border p-2">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $c)
            <tr>
                <td class="border p-2">{{ $c->name }}</td>
                <td class="border p-2">{{ $c->owner->name ?? "-" }}</td>
                <td class="border p-2">
                    <form method="POST" action="{{ route("admin.impersonate", $c) }}">
                        @csrf
                        <button type="submit" class="text-blue-500 underline hover:text-blue-700">Login As</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
