<?php
session_start();
if (!isset($_SESSION['admin_connecte']) || $_SESSION['admin_connecte'] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bee Web - Cockpit Superviseur</title>
    <link rel="stylesheet" href="style_app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
    
    <style>
        /* --- DESIGN NEON & GLASS --- */
        .live-badge {
            display: inline-flex; align-items: center; background: rgba(46, 204, 113, 0.15);
            color: #27ae60; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;
            border: 1px solid rgba(46, 204, 113, 0.3); transition: all 0.3s;
        }
        .live-badge.offline { filter: grayscale(100%); opacity: 0.5; }
        
        .live-dot {
            width: 8px; height: 8px; background-color: #2ecc71; border-radius: 50%;
            margin-right: 8px; animation: pulse-green 1.5s infinite;
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }

        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .kpi-card-pro {
            background: var(--glass-bg); backdrop-filter: blur(12px); border: var(--glass-border);
            border-radius: 16px; padding: 20px; position: relative; overflow: hidden;
            transition: transform 0.3s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .kpi-card-pro:hover { transform: translateY(-5px); }
        .kpi-card-pro::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .kpi-temp::before { background: linear-gradient(to bottom, #e74c3c, #ff7675); }
        .kpi-weight::before { background: linear-gradient(to bottom, #f39c12, #f1c40f); }
        
        .kpi-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .kpi-title { font-size: 0.85rem; color: #7f8c8d; text-transform: uppercase; font-weight: 600; }
        .kpi-value { font-size: 2.2rem; font-weight: 700; color: #2c3e50; line-height: 1; transition: color 0.3s; }
        .kpi-unit { font-size: 1rem; color: #95a5a6; font-weight: 500;}

        /* BONUS : STYLE D'ALERTE CLIGNOTANT */
        .alarm-text {
            color: #e74c3c !important;
            animation: blink-red 0.5s infinite alternate;
        }
        @keyframes blink-red { from { opacity: 1; } to { opacity: 0.6; } }
        
        .kpi-stats {
            margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; font-size: 0.85rem; color: #666;
        }
        .stat-item i { margin-right: 5px; color: #aaa; }

        .chart-controls { display: flex; gap: 10px; }
        .time-btn {
            background: transparent; border: 1px solid #bdc3c7; color: #7f8c8d;
            padding: 6px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s;
        }
        .time-btn.active { background: var(--accent-color); border-color: var(--accent-color); color: white; }
        .time-btn.live-active { background: #27ae60; border-color: #27ae60; color: white; box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3); }

        .export-btn {
            background: #2c3e50; color: white; border: none; padding: 8px 15px;
            border-radius: 8px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;
            transition: transform 0.2s;
        }
        .export-btn:hover { transform: scale(1.05); background: #34495e; }
    </style>
</head>
<body class="history-body"> 
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand"><i class="fas fa-shield-alt"></i> Bee Web</div>
           <nav class="sidebar-menu">
    <a href="webapp.php" class="nav-item"><i class="fas fa-th-large"></i> DASHBOARD</a>
    <a href="carte.php" class="nav-item"><i class="fas fa-globe-americas"></i> SATELLITE</a>
    <a href="monitoring.php" class="nav-item"><i class="fas fa-wave-square"></i> MONITORING AI</a>
    <a href="historique.php" class="nav-item"><i class="fas fa-history"></i> ARCHIVES</a>
    <a href="parametres.php" class="nav-item"><i class="fas fa-cogs"></i> SYSTÈME</a>
    <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 20px; border-top: 1px solid #ef4444; padding-top: 15px;"><i class="fas fa-power-off"></i> DÉCONNEXION</a>
</nav>
        </aside>

        <main class="main-content">
            <header class="glass" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 5px;">
                        <h2>Monitoring Cockpit</h2>
                        <div id="status-badge" class="live-badge"><div class="live-dot"></div> EN DIRECT</div>
                    </div>
                    <p style="color: #666;"><i class="far fa-calendar-alt"></i> <span id="real-date">Chargement...</span> • Ruche Alpha-01</p>
                </div>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> Exporter .CSV
                </button>
            </header>

            <section class="kpi-row">
                <div class="kpi-card-pro kpi-temp">
                    <div class="kpi-header">
                        <div class="kpi-title"><i class="fas fa-thermometer-half"></i> Température</div>
                        <i class="fas fa-wifi" style="color: #2ecc71; opacity: 0.5;"></i>
                    </div>
                    <div class="kpi-value" id="val-temp">-- <span class="kpi-unit">°C</span></div>
                    <div class="kpi-stats">
                        <div class="stat-item"><i class="fas fa-clock"></i> Moy. 1h : <b id="avg-temp-hour">--</b></div>
                        <div class="stat-item"><i class="fas fa-calendar-day"></i> Moy. Jour : <b id="avg-temp-day">--</b></div>
                    </div>
                </div>

                <div class="kpi-card-pro kpi-weight">
                    <div class="kpi-header">
                        <div class="kpi-title"><i class="fas fa-balance-scale"></i> Poids</div>
                        <i class="fas fa-wifi" style="color: #2ecc71; opacity: 0.5;"></i>
                    </div>
                    <div class="kpi-value" id="val-weight">-- <span class="kpi-unit">kg</span></div>
                    <div class="kpi-stats">
                        <div class="stat-item"><i class="fas fa-clock"></i> Moy. 1h : <b id="avg-weight-hour">--</b></div>
                        <div class="stat-item"><i class="fas fa-calendar-day"></i> Moy. Jour : <b id="avg-weight-day">--</b></div>
                    </div>
                </div>
            </section>

            <section class="glass" style="padding: 25px;">
                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3>Flux & Historique</h3>
                    <div class="chart-controls">
                        <button class="time-btn" onclick="setMode('1H', this)">1H</button>
                        <button class="time-btn" onclick="setMode('24H', this)">24H</button>
                        <button class="time-btn" onclick="setMode('7J', this)">7J</button>
                        <button class="time-btn live-active" onclick="setMode('LIVE', this)">LIVE</button>
                    </div>
                </div>
                <div style="height: 400px; position: relative;">
                    <canvas id="superChart"></canvas>
                </div>
            </section>
        </main>
    </div>

    <script>
        // --- CONFIG BASE ---
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const today = new Date().toLocaleDateString('fr-FR', dateOptions);
        document.getElementById('real-date').textContent = today.charAt(0).toUpperCase() + today.slice(1);

        const ctx = document.getElementById('superChart').getContext('2d');
        let simulationInterval = null;
        
        let currentTemp = 34.0; // Valeurs de départ
        let currentWeight = 42.0;

        // Dégradés
        let gradientTemp = ctx.createLinearGradient(0, 0, 0, 400);
        gradientTemp.addColorStop(0, 'rgba(231, 76, 60, 0.4)'); gradientTemp.addColorStop(1, 'rgba(231, 76, 60, 0.0)');
        let gradientWeight = ctx.createLinearGradient(0, 0, 0, 400);
        gradientWeight.addColorStop(0, 'rgba(241, 196, 15, 0.4)'); gradientWeight.addColorStop(1, 'rgba(241, 196, 15, 0.0)');

        // Données Statiques
        const staticData = {
            '1H': { labels: ['14:00', '14:10', '14:20', '14:30', '14:40', '14:50'], temp: [33.5, 33.8, 34.0, 34.2, 34.1, 34.2], weight: [42.0, 42.0, 42.1, 42.1, 42.1, 42.1] },
            '24H': { labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'], temp: [18, 17, 22, 28, 34, 30], weight: [40, 40, 40.2, 41.5, 42.8, 42.5] },
            '7J': { labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'], temp: [30, 32, 29, 31, 34, 33, 35], weight: [38, 38.5, 39, 40, 41, 42, 42.8] }
        };

        const initialLiveData = {
            labels: ['14:50:00', '14:50:05', '14:50:10', '14:50:15', '14:50:20'],
            temp: [34.0, 34.1, 34.2, 34.3, 34.2], weight: [42.0, 42.0, 42.1, 42.1, 42.1]
        };

        const superChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...initialLiveData.labels],
                datasets: [
                    { label: 'Température (°C)', data: [...initialLiveData.temp], borderColor: '#e74c3c', backgroundColor: gradientTemp, borderWidth: 3, fill: true, tension: 0.4, yAxisID: 'y' },
                    { label: 'Poids (kg)', data: [...initialLiveData.weight], borderColor: '#f1c40f', backgroundColor: gradientWeight, borderWidth: 3, fill: true, tension: 0.4, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 800 }, interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false } },
                    y: { type: 'linear', display: true, position: 'left', min: 0, max: 60 }, // Echelle large pour voir les pics God Mode
                    y1: { type: 'linear', display: true, position: 'right', grid: { display: false }, min: 0, max: 50 }
                }
            }
        });

        // --- MOTEUR LIVE ---
        function runSimulation() {
            const now = new Date();
            const timeLabel = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0');

            // Variation douce (sauf si God Mode a changé les valeurs)
            currentTemp += (Math.random() - 0.5) * 0.4;
            currentWeight += (Math.random() - 0.5) * 0.05;
            
            // Sécurité pour éviter valeurs absurdes si pas de God Mode
            if(currentWeight < 0) currentWeight = 0;

            const t = parseFloat(currentTemp.toFixed(1));
            const w = parseFloat(currentWeight.toFixed(1));

            superChart.data.labels.push(timeLabel);
            superChart.data.datasets[0].data.push(t);
            superChart.data.datasets[1].data.push(w);

            if (superChart.data.labels.length > 12) {
                superChart.data.labels.shift(); superChart.data.datasets[0].data.shift(); superChart.data.datasets[1].data.shift();
            }
            superChart.update('none');
            updateKPI(t, w);
        }

        function updateKPI(t, w) {
            const tempEl = document.getElementById('val-temp');
            const weightEl = document.getElementById('val-weight');
            
            tempEl.innerHTML = t + ' <span class="kpi-unit">°C</span>';
            weightEl.innerHTML = w + ' <span class="kpi-unit">kg</span>';

            // BONUS 3: ALERTES VISUELLES
            if(t > 38) tempEl.parentElement.classList.add('alarm-text');
            else tempEl.parentElement.classList.remove('alarm-text');

            if(w < 5) weightEl.parentElement.classList.add('alarm-text');
            else weightEl.parentElement.classList.remove('alarm-text');

            // Moyennes (calcul simple)
            document.getElementById('avg-temp-hour').innerText = (t - 0.5).toFixed(1) + '°C';
            document.getElementById('avg-temp-day').innerText = (t - 1.2).toFixed(1) + '°C';
            document.getElementById('avg-weight-hour').innerText = w.toFixed(1) + 'kg';
            document.getElementById('avg-weight-day').innerText = (w + 0.2).toFixed(1) + 'kg';
        }

        // --- GESTION MODES ---
        function setMode(mode, btn) {
            document.querySelectorAll('.time-btn').forEach(b => { b.classList.remove('active'); b.classList.remove('live-active'); });
            
            if (mode === 'LIVE') {
                btn.classList.add('live-active');
                document.getElementById('status-badge').className = 'live-badge';
                document.getElementById('status-badge').innerHTML = '<div class="live-dot"></div> EN DIRECT';
                if (!simulationInterval) {
                    superChart.data.labels = [...initialLiveData.labels];
                    superChart.data.datasets[0].data = [...initialLiveData.temp];
                    superChart.data.datasets[1].data = [...initialLiveData.weight];
                    superChart.update();
                    simulationInterval = setInterval(runSimulation, 2000);
                }
            } else {
                btn.classList.add('active');
                clearInterval(simulationInterval); simulationInterval = null;
                document.getElementById('status-badge').className = 'live-badge offline';
                document.getElementById('status-badge').innerHTML = '<i class="fas fa-history"></i> HISTORIQUE';
                if (staticData[mode]) {
                    superChart.data.labels = staticData[mode].labels;
                    superChart.data.datasets[0].data = staticData[mode].temp;
                    superChart.data.datasets[1].data = staticData[mode].weight;
                    superChart.update();
                    updateKPI(staticData[mode].temp.slice(-1)[0], staticData[mode].weight.slice(-1)[0]);
                }
            }
        }

        // --- BONUS 1 : GOD MODE (CLAVIER) ---
        document.addEventListener('keydown', (event) => {
            const key = event.key.toLowerCase();
            // Touche A = Alerte Température
            if (key === 'a') {
                currentTemp = 48.5; // Pic de chaleur
                console.log('GOD MODE: SURCHAUFFE ACTIVÉE');
            }
            // Touche V = Vol
            if (key === 'v') {
                currentWeight = 2.0; // Poids vide
                console.log('GOD MODE: VOL ACTIVÉ');
            }
            // Touche R = Reset
            if (key === 'r') {
                currentTemp = 34.0;
                currentWeight = 42.0;
                console.log('GOD MODE: RESET');
            }
        });

        // --- BONUS 2 : EXPORT CSV ---
        function exportToCSV() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Heure,Temperature,Poids\r\n"; // En-têtes

            superChart.data.labels.forEach((label, index) => {
                let row = label + "," + superChart.data.datasets[0].data[index] + "," + superChart.data.datasets[1].data[index];
                csvContent += row + "\r\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "bee_web_export_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Start
        simulationInterval = setInterval(runSimulation, 2000);
    </script>
</body>
</html>