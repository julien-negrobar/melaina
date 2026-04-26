<?php
// Paramètres de ton hébergeur
$host = "127.0.0.1"; 
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

try {
    // Connexion silencieuse
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ne parle que s'il y a une erreur fatale
    die("❌ Erreur de connexion BDD : " . $e->getMessage());
}
?>