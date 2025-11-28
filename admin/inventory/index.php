<?php include '../includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background: #0f0f1a; }</style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-purple-950 to-slate-950 text-gray-100 min-h-screen">
    <?php include '../sidebar.php'; ?>
    <?php include '../header.php'; ?>

    <main class="ml-80 pt-20 px-10">
        <div class="mb-12">
            <h1 class="text-5xl font-black bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-red-500">Inventory Control</h1>
            <p class="text-gray-400 mt-3 text-lg">Critical stock alerts • Reorder recommendations</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="bg-gradient-to-br from-red-600/20 to-orange-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Critical Stock</p>
                <p class="text-6xl font-black mt-4 text-red-400">12</p>
                <p class="text-red-300 mt-3">Requires immediate reorder</p>
            </div>
            <div class="bg-gradient-to-br from-amber-600/20 to-yellow-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Low Stock</p>
                <p class="text-6xl font-black mt-4 text-amber-400">28</p>
                <p class="text-amber-300 mt-3">Monitor closely</p>
            </div>
            <div class="bg-gradient-to-br from-emerald-600/20 to-teal-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Total Value</p>
                <p class="text-6xl font-black mt-4">₱4.82M</p>
                <p class="text-emerald-300 mt-3">Across 1,284 SKUs</p>
            </div>
        </div>

        <div class="bg-black/40 backdrop-blur-2xl border border-white/10 rounded-3xl overflow-hidden">
            <div class="px-10 py-8 border-b border-white/10">
                <h2 class="text-3xl font-bold">Critical Stock Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">Product</th>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">SKU</th>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">Stock</th>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">Min Level</th>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">Supplier</th>
                            <th class="px-8 py-6 text-left text-sm font-semibold text-gray-300">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <tr class="hover:bg-white/5 transition">
                            <td class="px-8 py-6 font-medium">Oversized Gradient Hoodie</td>
                            <td class="px-8 py-6 text-gray-400">HOOD-GRD-2025</td>
                            <td class="px-8 py-6 text-red-400 font-bold">3 left</td>
                            <td class="px-8 py-6">30</td>
                            <td class="px-8 py-6">Urban Threads Co.</td>
                            <td class="px-8 py-6">
                                <button class="bg-gradient-to-r from-red-600 to-orange-600 px-6 py-3 rounded-xl font-bold hover:scale-105 transition">Reorder Now</button>
                            </td>
                        </tr>
                        <tr class="hover:bg-white/5 transition">
                            <td class="px-8 py-6 font-medium">Denim Jacket - Vintage Wash</td>
                            <td class="px-8 py-6 text-gray-400">DENIM-VW-89</td>
                            <td class="px-8 py-6 text-red-400 font-bold">5 left</td>
                            <td class="px-8 py-6">20</td>
                            <td class="px-8 py-6">Luxe Fabric Mills</td>
                            <td class="px-8 py-6">
                                <button class="bg-gradient-to-r from-red-600 to-orange-600 px-6 py-3 rounded-xl font-bold hover:scale-105 transition">Reorder Now</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>