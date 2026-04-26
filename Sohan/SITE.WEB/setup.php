<?php
$host = "localhost";
$dbname = "melena";
$user = "root";
$pass = "root"; // Mets "" si tu es sur Windows

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    // PHP calcule le VRAI hash mathématique pour "Carole2026"
    $vrai_hash = password_hash("Carole2026", PASSWORD_BCRYPT);
    
    // On met à jour la base de données avec ce vrai hash
    $stmt = $pdo->prepare("UPDATE administrateur SET mot_de_passe = :hash WHERE identifiant = 'Carole'");
    $stmt->execute(['hash' => $vrai_hash]);
    
    echo "<h1>✅ SUCCÈS !</h1>";
    echo "Le vrai mot de passe sécurisé a été enregistré en base de données.<br>";
    echo "<a href='login.php'>Clique ici pour aller te connecter</a>";
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>