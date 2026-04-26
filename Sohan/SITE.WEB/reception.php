<?php
// On cache les erreurs PHP pour ne pas faire planter le script Python
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// 1. PARAMÈTRES DE TA BASE DE DONNÉES
// ==========================================
$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

// ==========================================
// 2. LECTURE DU COLIS (JSON) ENVOYÉ PAR PYTHON
// ==========================================
$donnees_recues = file_get_contents("php://input");
$mesures = json_decode($donnees_recues, true);

if($mesures) {
    try {
        // Connexion à la base
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // ==========================================
        // 3. EXTRACTION DES DONNÉES DU PYTHON
        // ==========================================
        // L'ASTUCE EST ICI : On lit l'ID envoyé par le capteur. 
        // S'il n'y a pas d'ID précisé, on le met par défaut dans la ruche 1.
        $id_ruche = isset($mesures['id_ruche']) ? intval($mesures['id_ruche']) : 1;

        $temp = isset($mesures['temperature']) ? floatval($mesures['temperature']) : 0;
        $humidite = isset($mesures['humidite']) ? floatval($mesures['humidite']) : 0;
        $poids = isset($mesures['poids']) ? floatval($mesures['poids']) : 0;
        $latitude = isset($mesures['latitude']) ? floatval($mesures['latitude']) : 14.834720;
        $longitude = isset($mesures['longitude']) ? floatval($mesures['longitude']) : -61.058890;
        $meteo = isset($mesures['meteo']) ? $mesures['meteo'] : 'Inconnu';

        // ==========================================
        // 4. INSERTION DANS LA TABLE "mesures"
        // ==========================================
        $sql = "INSERT INTO mesures (id_ruche, temperature, humidite, poids, latitude, longitude, meteo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_ruche, $temp, $humidite, $poids, $latitude, $longitude, $meteo]);
        
        // On répond au Python que tout s'est bien passé
        echo json_encode(["status" => "success", "message" => "Donnees inserees avec succes pour la ruche $id_ruche !"]);

    } catch (PDOException $e) {
        // En cas d'erreur avec la base de données
        echo json_encode(["status" => "error", "message" => "Erreur BDD."]);
    }
} else {
    // Si le fichier est ouvert directement dans le navigateur sans données
    echo json_encode(["status" => "error", "message" => "Aucune donnee JSON reçue. Ce fichier sert d'API."]);
}
?>