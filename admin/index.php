<?php 
// CORRECT PATHS FOR XAMPP (we are already inside admin folder)
include 'includes/auth.php';     // → admin/includes/auth.php
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-purple-950 to-slate-950 text-gray-100 min-h-screen">
    <?php include 'sidebar.php'; ?>   <!-- same folder as index.php -->
    <?php include 'header.php'; ?>    <!-- same folder as index.php -->

    <main class="ml-80 pt-20 px-10">
        <div class="mb-12">
            <h1 class="text-5xl font-black bg-clip-text text-transparent bg-gradient-to-r from-violet-400 to-pink-400">Admin Dashboard</h1>
            <p class="text-gray-400 mt-3 text-lg">Real-time insights • AI-powered decisions • Full control</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="bg-gradient-to-br from-violet-600/20 to-pink-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition-transform">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Total Revenue</p>
                <p class="text-5xl font-black mt-4">₱2.84M</p>
                <p class="text-green-400 text-lg mt-3">34.8% MoM</p>
            </div>
            <div class="bg-gradient-to-br from-emerald-600/20 to-teal-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition-transform">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Orders</p>
                <p class="text-5xl font-black mt-4">8,429</p>
                <p class="text-green-400 text-lg mt-3">22.1% MoM</p>
            </div>
            <div class="bg-gradient-to-br from-amber-600/20 to-orange-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition-transform">
                <p class="text-gray-400 text-sm uppercase tracking-wider">Low Stock</p>
                <p class="text-5xl font-black mt-4 text-orange-400">12</p>
                <p class="text-gray-400 text-sm mt-3">Requires attention</p>
            </div>
            <div class="bg-gradient-to-br from-pink-600/20 to-rose-600/20 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition-transform">
                <p class="text-gray-400 text-sm uppercase tracking-wider">AI Confidence</p>
                <p class="text-5xl font-black mt-4 text-pink-400">96.3%</p>
                <p class="text-gray-400 text-sm mt-3">Forecast accuracy</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-black/40 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                <h2 class="text-2xl font-bold mb-6">Revenue Trend + AI Forecast</h2>
                <canvas id="revenueChart" height="120"></canvas>
            </div>

            <div class="bg-black/40 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                <h2 class="text-2xl font-bold mb-6">Quick Actions</h2>
                <div class="space-y-4">
                    <a href="products/add.php" class="block text-center bg-gradient-to-r from-violet-600 to-pink-600 py-5 rounded-2xl font-bold text-xl hover:scale-105 transition">+ Add Product</a>
                    <a href="inventory/" class="block text-center bg-gradient-to-r from-orange-600 to-red-600 py-5 rounded-2xl font-bold text-xl hover:scale-105 transition">View Critical Stock</a>
                    <a href="analytics/" class="block text-center bg-gradient-to-r from-teal-600 to-cyan-600 py-5 rounded-2xl font-bold text-xl hover:scale-105 transition">Run New Forecast</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Actual Revenue',
                    data: [420000, 580000, 720000, 890000, 1100000, 1380000, 2840000],
                    borderColor: '#c084fc',
                    backgroundColor: 'rgba(192, 132, 252, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'AI Forecast',
                    data: [null, null, null, null, null, 1380000, 3200000],
                    borderColor: '#f472b6',
                    borderDash: [8, 4],
                    tension: 0.4
                }]
            },
            options: {
                plugins: { legend: { labels: { color: '#e2e8f0' } } },
                scales: {
                    y: { ticks: { color: '#94a3b8', callback: v => '₱' + (v/1000000).toFixed(1) + 'M' } },
                    x: { ticks: { color: '#94a3b8' } }
                }
            }
        });
    </script>
</body>
</html>