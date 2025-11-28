<?php include '../includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & AI • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>body { font-family: 'Inter', sans-serif; background: #0f0f1a; }</style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-purple-950 to-slate-950 text-gray-100 min-h-screen">
    <?php include '../sidebar.php'; ?>
    <?php include '../header.php'; ?>

    <main class="ml-80 pt-20 px-10">
        <div class="mb-12">
            <h1 class="text-5xl font-black bg-clip-text text-transparent bg-gradient-to-r from-violet-400 via-pink-400 to-cyan-400">Analytics & AI Engine</h1>
            <p class="text-gray-400 mt-3 text-lg">Real-time data • Predictive forecasting • Smart insights</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <div class="lg:col-span-2 bg-black/40 backdrop-blur-2xl border border-white/10 rounded-3xl p-10">
                <h2 class="text-3xl font-bold mb-8">Revenue + AI Forecast (Next 90 Days)</h2>
                <canvas id="forecastChart" height="100"></canvas>
            </div>

            <div class="space-y-8">
                <div class="bg-gradient-to-br from-violet-600/30 to-pink-600/30 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-2xl font-bold mb-6">AI Prediction</h3>
                    <p class="text-6xl font-black text-pink-400">₱8.4M</p>
                    <p class="text-green-400 text-xl mt-4">+41.2% vs current quarter</p>
                    <p class="text-gray-400 mt-6">Confidence: <span class="text-pink-400 font-bold">96.7%</span></p>
                </div>

                <div class="bg-black/40 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-xl font-bold mb-6">Top Categories</h3>
                    <div class="space-y-4">
                        <div><span class="text-gray-400">1. Streetwear Hoodies</span> <span class="float-right text-green-400">+87%</span></div>
                        <div><span class="text-gray-400">2. Denim Collection</span> <span class="float-right text-green-400">+62%</span></div>
                        <div><span class="text-gray-400">3. Minimal Sneakers</span> <span class="float-right text-amber-400">+18%</span></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        new Chart(document.getElementById('forecastChart'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'],
                datasets: [{
                    label: 'Actual Sales',
                    data: [620000, 780000, 920000, 880000, 1100000, 1380000, 1620000, 1840000],
                    borderColor: '#a78bfa',
                    backgroundColor: 'rgba(167, 139, 250, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'AI Forecast',
                    data: [null, null, null, null, null, 1380000, 2100000, 2840000],
                    borderColor: '#f472b6',
                    borderDash: [10, 5],
                    tension: 0.4
                }]
            },
            options: {
                plugins: { legend: { labels: { color: '#e2e8f0' } } },
                scales: { y: { ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
            }
        });
    </script>
</body>
</html>