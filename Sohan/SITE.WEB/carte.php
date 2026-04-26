<?php
session_start();
if (!isset($_SESSION['admin_connecte']) || $_SESSION['admin_connecte'] !== true) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['id_admin'])) {
    die("Erreur de session. Veuillez vous reconnecter.");
}

$id_utilisateur = $_SESSION['id_admin'];

// ==========================================
// 1. LECTURE DU SEUIL DYNAMIQUE
// ==========================================
$fichier_config = 'config.json';
$seuil_alerte = 29.0; 

if (file_exists($fichier_config)) {
    $config_actuelle = json_decode(file_get_contents($fichier_config), true);
    if(isset($config_actuelle['seuil_temp'])) {
        $seuil_alerte = floatval($config_actuelle['seuil_temp']);
    }
}

// ==========================================
// 2. CONNEXION BDD ET LECTURE DYNAMIQUE
// ==========================================
$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

$ruches_map = [];

// 📍 LES FAUSSES COORDONNÉES DE DÉMO (Pour bien espacer les points)
$fausses_coords = [
    ['lat' => 14.8360, 'lon' => -61.0600], // Position démo 1 (Anciennement Alpha)
    ['lat' => 14.8347, 'lon' => -61.0588], // Position démo 2 (Anciennement Beta)
    ['lat' => 14.8400, 'lon' => -61.0500], // Position démo 3 (Au cas où y'a une 3ème ruche)
    ['lat' => 14.8300, 'lon' => -61.0650]  // Position démo 4
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // On récupère QUE les ruches de la personne connectée
    $stmt = $pdo->prepare("SELECT id_ruche, nom_ruche FROM ruche WHERE id_admin = :id");
    $stmt->execute(['id' => $id_utilisateur]);
    $ruches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $index_demo = 0; // Pour piocher dans les fausses coordonnées

    foreach ($ruches as $r) {
        $stmt_m = $pdo->prepare("SELECT temperature FROM mesures WHERE id_ruche = :id ORDER BY id_mesure DESC LIMIT 1");
        $stmt_m->execute(['id' => $r['id_ruche']]);
        $mesure = $stmt_m->fetch(PDO::FETCH_ASSOC);

        $temp = $mesure ? floatval($mesure['temperature']) : 25.0;
        
        // On force les coordonnées de démo pour l'affichage !
        $lat = isset($fausses_coords[$index_demo]) ? $fausses_coords[$index_demo]['lat'] : (14.8350 + ($index_demo * 0.002));
        $lon = isset($fausses_coords[$index_demo]) ? $fausses_coords[$index_demo]['lon'] : (-61.0590 + ($index_demo * 0.002));
        
        $ruches_map[] = [
            'id' => $r['id_ruche'],
            'nom' => $r['nom_ruche'],
            'temp' => $temp,
            'lat' => $lat,
            'lon' => $lon,
            'is_alert' => ($temp >= $seuil_alerte)
        ];
        $index_demo++;
    }
} catch(PDOException $e) {
    die("Erreur BDD");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BEE WEB // SATELLITE</title>
    <link rel="stylesheet" href="style_app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        .leaflet-popup-content-wrapper, .leaflet-popup-tip { background: rgba(15, 23, 42, 0.95); color: #fff; backdrop-filter: blur(5px); border: 1px solid var(--neon-blue); box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .leaflet-container a.popup-btn { display: block; margin-top: 5px; padding: 5px 10px; text-align: center; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: bold; }
        .btn-nav { background: var(--neon-blue); color: #000 !important; } .btn-nav:hover { opacity: 0.9; }
        .btn-gps { border: 1px solid var(--neon-blue); color: var(--neon-blue) !important; } .btn-gps:hover { background: rgba(56, 189, 248, 0.1); }
        .map-container { height: calc(100vh - 140px); min-height: 500px; position: relative; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        .fleet-overlay { position: absolute; top: 20px; right: 20px; width: 250px; background: rgba(2, 6, 23, 0.9); border: 1px solid var(--neon-blue); border-radius: 8px; padding: 20px; z-index: 1000; }
        @media screen and (max-width: 1024px) { .fleet-overlay { top: auto; bottom: 20px; right: 50%; transform: translateX(50%); width: 90%; } .map-container { height: 65vh !important; } }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand"><i class="fas fa-hexagon-nodes"></i> BEE_WEB</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i> DASHBOARD</a>
                <a href="carte.php" class="nav-item active"><i class="fas fa-globe-americas"></i> SATELLITE</a>
                <a href="monitoring.php" class="nav-item"><i class="fas fa-wave-square"></i> MONITORING AI</a>
                <a href="historique.php" class="nav-item"><i class="fas fa-history"></i> ARCHIVES</a>
                <a href="parametres.php" class="nav-item"><i class="fas fa-cogs"></i> SYSTÈME</a>
                <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 20px;"><i class="fas fa-power-off"></i> DÉCONNEXION</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h2>LOCALISATION <b>TACTIQUE</b></h2>
                    <p class="page-subtitle">POSITIONNEMENT GPS DÉMO (SEUIL ACTUEL: <?php echo $seuil_alerte; ?>°C)</p>
                </div>
            </header>

            <div class="map-container">
                <div id="map" style="width: 100%; height: 100%;"></div>
                
                <div class="fleet-overlay">
                    <h4 style="color: var(--neon-blue); margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px;">FLOTTE DÉPLOYÉE</h4>
                    <div id="fleet-list"></div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        const ruchesData = <?php echo json_encode($ruches_map); ?>;
        const fleetList = document.getElementById('fleet-list');
        
        var map = L.map('map').setView([14.8350, -61.0590], 15); 
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 20 }).addTo(map);

        var beeIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background:#0ea5e9; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow: 0 0 15px #0ea5e9;'></div>", iconSize: [20,20] });
        var alertIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background:#ef4444; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow: 0 0 15px #ef4444; animation: blinker 1s infinite;'></div>", iconSize: [20,20] });

        var markersGroup = L.featureGroup();

        if (ruchesData.length === 0) {
            fleetList.innerHTML = "<div style='font-size:0.85rem; color:#64748b;'>Aucune ruche détectée</div>";
        } else {
            ruchesData.forEach(hive => {
                // UI Overlay
                fleetList.innerHTML += `
                    <div style="font-size: 0.85rem; display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${hive.nom}</span>
                        <span>${hive.is_alert ? '<span style="color:var(--neon-red);" class="blink">● ALERTE</span>' : '<span style="color:var(--neon-green);">● ACTIF</span>'}</span>
                    </div>
                `;

                // Map Markers
                let icon = hive.is_alert ? alertIcon : beeIcon;
                let color = hive.is_alert ? "#ef4444" : "#0ea5e9";
                let text = hive.is_alert ? "⚠️ SURCHAUFFE" : "Statut: Nominal";
                let btnStyle = hive.is_alert ? "background:#ef4444; color:white !important;" : "";

                let marker = L.marker([hive.lat, hive.lon], {icon: icon})
                    .bindPopup(`
                        <b style='color:${color}; font-size:1.1rem;'>${hive.nom}</b><br>
                        ${text} (${hive.temp}°C)<br>
                        <div style='margin-top:10px;'>
                            <a href='monitoring.php?hive=${hive.id}' class='popup-btn btn-nav' style='${btnStyle}'><i class="fas fa-chart-line"></i> VOIR ANALYSE</a>
                            <a href='https://www.google.com/maps?q=$$${hive.lat},${hive.lon}' target='_blank' class='popup-btn btn-gps'><i class="fas fa-location-arrow"></i> ITINÉRAIRE</a>
                        </div>
                    `);
                
                markersGroup.addLayer(marker);
            });

            markersGroup.addTo(map);

            const urlParams = new URLSearchParams(window.location.search);
            const targetHive = urlParams.get('hive');

            if(targetHive) {
                let found = ruchesData.find(h => h.id == targetHive);
                if(found) map.setView([found.lat, found.lon], 18);
            } else {
                map.fitBounds(markersGroup.getBounds().pad(0.5));
            }
        }
    </script>
</body>
</html><?php
session_start();
if (!isset($_SESSION['admin_connecte']) || $_SESSION['admin_connecte'] !== true) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['id_admin'])) {
    die("Erreur de session. Veuillez vous reconnecter.");
}

$id_utilisateur = $_SESSION['id_admin'];

// ==========================================
// 1. LECTURE DU SEUIL DYNAMIQUE
// ==========================================
$fichier_config = 'config.json';
$seuil_alerte = 29.0; 

if (file_exists($fichier_config)) {
    $config_actuelle = json_decode(file_get_contents($fichier_config), true);
    if(isset($config_actuelle['seuil_temp'])) {
        $seuil_alerte = floatval($config_actuelle['seuil_temp']);
    }
}

// ==========================================
// 2. CONNEXION BDD ET LECTURE DYNAMIQUE
// ==========================================
$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

$ruches_map = [];

// 📍 LES FAUSSES COORDONNÉES DE DÉMO (Pour bien espacer les points)
$fausses_coords = [
    ['lat' => 14.8360, 'lon' => -61.0600], // Position démo 1 (Anciennement Alpha)
    ['lat' => 14.8347, 'lon' => -61.0588], // Position démo 2 (Anciennement Beta)
    ['lat' => 14.8400, 'lon' => -61.0500], // Position démo 3 (Au cas où y'a une 3ème ruche)
    ['lat' => 14.8300, 'lon' => -61.0650]  // Position démo 4
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // On récupère QUE les ruches de la personne connectée
    $stmt = $pdo->prepare("SELECT id_ruche, nom_ruche FROM ruche WHERE id_admin = :id");
    $stmt->execute(['id' => $id_utilisateur]);
    $ruches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $index_demo = 0; // Pour piocher dans les fausses coordonnées

    foreach ($ruches as $r) {
        $stmt_m = $pdo->prepare("SELECT temperature FROM mesures WHERE id_ruche = :id ORDER BY id_mesure DESC LIMIT 1");
        $stmt_m->execute(['id' => $r['id_ruche']]);
        $mesure = $stmt_m->fetch(PDO::FETCH_ASSOC);

        $temp = $mesure ? floatval($mesure['temperature']) : 25.0;
        
        // On force les coordonnées de démo pour l'affichage !
        $lat = isset($fausses_coords[$index_demo]) ? $fausses_coords[$index_demo]['lat'] : (14.8350 + ($index_demo * 0.002));
        $lon = isset($fausses_coords[$index_demo]) ? $fausses_coords[$index_demo]['lon'] : (-61.0590 + ($index_demo * 0.002));
        
        $ruches_map[] = [
            'id' => $r['id_ruche'],
            'nom' => $r['nom_ruche'],
            'temp' => $temp,
            'lat' => $lat,
            'lon' => $lon,
            'is_alert' => ($temp >= $seuil_alerte)
        ];
        $index_demo++;
    }
} catch(PDOException $e) {
    die("Erreur BDD");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BEE WEB // SATELLITE</title>
    <link rel="stylesheet" href="style_app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        .leaflet-popup-content-wrapper, .leaflet-popup-tip { background: rgba(15, 23, 42, 0.95); color: #fff; backdrop-filter: blur(5px); border: 1px solid var(--neon-blue); box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .leaflet-container a.popup-btn { display: block; margin-top: 5px; padding: 5px 10px; text-align: center; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: bold; }
        .btn-nav { background: var(--neon-blue); color: #000 !important; } .btn-nav:hover { opacity: 0.9; }
        .btn-gps { border: 1px solid var(--neon-blue); color: var(--neon-blue) !important; } .btn-gps:hover { background: rgba(56, 189, 248, 0.1); }
        .map-container { height: calc(100vh - 140px); min-height: 500px; position: relative; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        .fleet-overlay { position: absolute; top: 20px; right: 20px; width: 250px; background: rgba(2, 6, 23, 0.9); border: 1px solid var(--neon-blue); border-radius: 8px; padding: 20px; z-index: 1000; }
        @media screen and (max-width: 1024px) { .fleet-overlay { top: auto; bottom: 20px; right: 50%; transform: translateX(50%); width: 90%; } .map-container { height: 65vh !important; } }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand"><i class="fas fa-hexagon-nodes"></i> BEE_WEB</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i> DASHBOARD</a>
                <a href="carte.php" class="nav-item active"><i class="fas fa-globe-americas"></i> SATELLITE</a>
                <a href="monitoring.php" class="nav-item"><i class="fas fa-wave-square"></i> MONITORING AI</a>
                <a href="historique.php" class="nav-item"><i class="fas fa-history"></i> ARCHIVES</a>
                <a href="parametres.php" class="nav-item"><i class="fas fa-cogs"></i> SYSTÈME</a>
                <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 20px;"><i class="fas fa-power-off"></i> DÉCONNEXION</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h2>LOCALISATION <b>TACTIQUE</b></h2>
                    <p class="page-subtitle">POSITIONNEMENT GPS DÉMO (SEUIL ACTUEL: <?php echo $seuil_alerte; ?>°C)</p>
                </div>
            </header>

            <div class="map-container">
                <div id="map" style="width: 100%; height: 100%;"></div>
                
                <div class="fleet-overlay">
                    <h4 style="color: var(--neon-blue); margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px;">FLOTTE DÉPLOYÉE</h4>
                    <div id="fleet-list"></div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        const ruchesData = <?php echo json_encode($ruches_map); ?>;
        const fleetList = document.getElementById('fleet-list');
        
        var map = L.map('map').setView([14.8350, -61.0590], 15); 
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 20 }).addTo(map);

        var beeIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background:#0ea5e9; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow: 0 0 15px #0ea5e9;'></div>", iconSize: [20,20] });
        var alertIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background:#ef4444; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow: 0 0 15px #ef4444; animation: blinker 1s infinite;'></div>", iconSize: [20,20] });

        var markersGroup = L.featureGroup();

        if (ruchesData.length === 0) {
            fleetList.innerHTML = "<div style='font-size:0.85rem; color:#64748b;'>Aucune ruche détectée</div>";
        } else {
            ruchesData.forEach(hive => {
                // UI Overlay
                fleetList.innerHTML += `
                    <div style="font-size: 0.85rem; display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${hive.nom}</span>
                        <span>${hive.is_alert ? '<span style="color:var(--neon-red);" class="blink">● ALERTE</span>' : '<span style="color:var(--neon-green);">● ACTIF</span>'}</span>
                    </div>
                `;

                // Map Markers
                let icon = hive.is_alert ? alertIcon : beeIcon;
                let color = hive.is_alert ? "#ef4444" : "#0ea5e9";
                let text = hive.is_alert ? "⚠️ SURCHAUFFE" : "Statut: Nominal";
                let btnStyle = hive.is_alert ? "background:#ef4444; color:white !important;" : "";

                let marker = L.marker([hive.lat, hive.lon], {icon: icon})
                    .bindPopup(`
                        <b style='color:${color}; font-size:1.1rem;'>${hive.nom}</b><br>
                        ${text} (${hive.temp}°C)<br>
                        <div style='margin-top:10px;'>
                            <a href='monitoring.php?hive=${hive.id}' class='popup-btn btn-nav' style='${btnStyle}'><i class="fas fa-chart-line"></i> VOIR ANALYSE</a>
                            <a href='https://www.google.com/maps?q=$$${hive.lat},${hive.lon}' target='_blank' class='popup-btn btn-gps'><i class="fas fa-location-arrow"></i> ITINÉRAIRE</a>
                        </div>
                    `);
                
                markersGroup.addLayer(marker);
            });

            markersGroup.addTo(map);

            const urlParams = new URLSearchParams(window.location.search);
            const targetHive = urlParams.get('hive');

            if(targetHive) {
                let found = ruchesData.find(h => h.id == targetHive);
                if(found) map.setView([found.lat, found.lon], 18);
            } else {
                map.fitBounds(markersGroup.getBounds().pad(0.5));
            }
        }
    </script>
</body>
</html>