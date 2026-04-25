<?php
// Paramètres MAMP
$host = "127.0.0.1"; // Le PHP et la BDD sont sur le même serveur distant
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";
try {
    // Tentative de connexion
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    // Si on arrive ici, c'est que la connexion a marché !
    echo "<h1>✅ CONNEXION RÉUSSIE !</h1>";
    echo "<p>Le lien avec la base de données MELAINA fonctionne parfaitement.</p>";
    
    // On va chercher les données pour prouver que ça marche
    $stmt = $pdo->prepare("SELECT temperature, humidite, poids, date_mesure FROM mesures ORDER BY date_mesure DESC LIMIT 10");
    $stmt->execute();
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Voici les fausses données générées :</h3>";
    echo "<pre>";
    print_r($resultats); // Affiche les données de façon lisible
    echo "</pre>";

} catch (PDOException $e) {
    // Si ça échoue, ça affichera l'erreur exacte
    echo "<h1>❌ ERREUR DE CONNEXION</h1>";
    echo "Détail de l'erreur : " . $e->getMessage();
}
?>