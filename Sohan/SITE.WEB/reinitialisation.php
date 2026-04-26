<?php
session_start();

$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

$erreur = "";
$succes = "";
$token_valide = false;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Vérifier si le token est présent dans l'URL
    if (isset($_GET['token'])) {
        $token = $_GET['token'];

        // 2. Chercher l'utilisateur avec ce token ET vérifier s'il n'est pas expiré
        // NOW() compare l'heure actuelle avec reset_expires_at en BDD
        $stmt = $pdo->prepare("SELECT id_admin FROM administrateur WHERE reset_token = :token AND reset_expires_at > NOW()");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if ($user) {
            $token_valide = true;

            // 3. Si le formulaire est envoyé
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $nouveau_mdp = $_POST['nouveau_mdp'];
                $confirmation = $_POST['confirmation'];

                if ($nouveau_mdp === $confirmation) {
                    // Hachage du nouveau mot de passe
                    $mdp_hache = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

                    // Mise à jour du MDP et SUPPRESSION du token (pour qu'il ne soit plus réutilisable)
                    $update = $pdo->prepare("UPDATE administrateur SET mot_de_passe = :mdp, reset_token = NULL, reset_expires_at = NULL WHERE reset_token = :token");
                    $update->execute(['mdp' => $mdp_hache, 'token' => $token]);

                    $succes = "MOT DE PASSE MIS À JOUR. Redirection vers le login...";
                    header("Refresh: 3; url=login.php");
                } else {
                    $erreur = "Les mots de passe ne correspondent pas.";
                }
            }
        } else {
            $erreur = "Le lien est invalide ou a expiré (limite de 10 minutes dépassée).";
        }
    } else {
        $erreur = "Aucun jeton d'accès détecté.";
    }

} catch (PDOException $e) {
    $erreur = "Erreur système : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MELAINA - Reset Password</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { 
            background-color: #020617; 
            color: #0ea5e9; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; /* Mieux pour les téléphones quand le clavier s'ouvre */
            padding: 20px; /* Évite que la boîte touche les bords de l'écran */
        }
        .login-box {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid #0ea5e9;
            padding: 30px; 
            border-radius: 8px; 
            width: 100%; 
            max-width: 400px; 
            text-align: center;
        }
        h2 { color: #f59e0b; margin-bottom: 20px; text-transform: uppercase; font-size: 1.5rem; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: bold; }
        .input-group input { 
            width: 100%; 
            padding: 12px; /* Un peu plus grand pour faciliter le clic au doigt */
            background: transparent; 
            border: 1px solid #334155; 
            color: #fff; 
            outline: none; 
            font-size: 16px; /* Empêche l'iPhone de zoomer automatiquement */
            border-radius: 4px;
        }
        .input-group input:focus { border-color: #0ea5e9; box-shadow: 0 0 8px rgba(14, 165, 233, 0.5); }
        .btn-submit { 
            width: 100%; 
            padding: 15px; 
            background: #0ea5e9; 
            color: #020617; 
            border: none; 
            font-weight: bold; 
            cursor: pointer; 
            text-transform: uppercase; 
            border-radius: 4px;
            font-size: 14px;
        }
        .error-msg { color: #ef4444; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
        .success-msg { color: #10b981; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>SÉCURITÉ SYSTÈME</h2>
        
        <?php if($erreur) echo "<div class='error-msg'>[!] $erreur</div>"; ?>
        <?php if($succes) echo "<div class='success-msg'>[OK] $succes</div>"; ?>

        <?php if($token_valide && !$succes): ?>
            <p style="color: #64748b; font-size: 12px; margin-bottom: 20px;">TOKEN VALIDÉ. VEUILLEZ SAISIR VOTRE NOUVELLE ACCRÉDITATION.</p>
            <form method="POST">
                <div class="input-group">
                    <label>NOUVEAU MOT DE PASSE</label>
                    <input type="password" name="nouveau_mdp" required placeholder="••••••••">
                </div>
                <div class="input-group">
                    <label>CONFIRMATION</label>
                    <input type="password" name="confirmation" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit">RÉINITIALISER</button>
            </form>
        <?php else: ?>
            <br>
            <a href="mot_de_passe_oublie.php" style="color:#0ea5e9; text-decoration:none; font-size: 14px;">Demander un nouveau lien</a>
        <?php endif; ?>
    </div>
</body>
</html>