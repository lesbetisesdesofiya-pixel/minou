<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Administration</h1>
        @if (session('error'))
            <p class="bg-red-100 text-red-600 p-3 rounded mb-4 text-sm">
                {{ session('error') }}
            </p>
        @endif
        <form method="POST" action="/admin-login" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">Adresse e-mail</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mot de passe</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none">
            </div>
            <button type="submit"
                class="w-full bg-orange-500 text-white py-2 rounded-lg font-semibold hover:bg-orange-600 transition">Se
                connecter</button>
        </form>
    </div>
</body>

</html>
