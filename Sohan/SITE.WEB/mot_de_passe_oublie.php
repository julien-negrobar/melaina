<?php
// On importe les fichiers de PHPMailer que tu viens d'ajouter
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it";

$erreur = "";
$succes = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Vérifier si l'email existe
        $stmt = $pdo->prepare("SELECT id_admin FROM administrateur WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user_exists = $stmt->fetch();

        if ($user_exists) {
            // 2. Générer un token unique et une expiration (+10 minutes)
            $token = bin2hex(random_bytes(32)); // Clé aléatoire sécurisée
            $expire = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            // 3. Stocker ça dans la BDD
            $update = $pdo->prepare("UPDATE administrateur SET reset_token = :token, reset_expires_at = :expire WHERE email = :email");
            $update->execute(['token' => $token, 'expire' => $expire, 'email' => $email]);

            // 4. Envoyer le mail avec PHPMailer
            $mail = new PHPMailer(true);

            // --- LA LIGNE MAGIQUE POUR LES ACCENTS ---
            $mail->CharSet = 'UTF-8';

            // Config Serveur LWS (d'après ta capture)
            $mail->isSMTP();
            $mail->Host       = 'mail.madebylucas.fr';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'system.melaina@madebylucas.fr';
            $mail->Password   = 'Melaina_972!'; // Ton mot de passe LWS
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port       = 465;

            // Destinataires
            $mail->setFrom('system.melaina@madebylucas.fr', 'MELAINA SYSTEM');
            $mail->addAddress($email);

            // Contenu du mail
            $mail->isHTML(true);
            $mail->Subject = 'RÉINITIALISATION DE VOTRE ACCRÉDITATION';
            
            // Le lien de reset (Change bien l'URL si elle n'est pas exacte)
            $lien = "https://beeapp.madebylucas.fr/reinitialisation.php?token=" . $token;
            
            $mail->Body = "
                <div style='background:#020617; color:#0ea5e9; padding:20px; font-family:monospace; border:1px solid #0ea5e9;'>
                    <h2 style='color:#f59e0b;'>MELAINA_OS : PROTOCOLE DE RÉCUPÉRATION</h2>
                    <p>Une demande de nouveau mot de passe a été détectée.</p>
                    <p>Cliquez sur le lien ci-dessous pour changer vos accès (Valide 10 minutes) :</p>
                    <a href='$lien' style='color:#020617; background:#0ea5e9; padding:10px; text-decoration:none; font-weight:bold;'>RÉINITIALISER MON MOT DE PASSE</a>
                    <p style='margin-top:20px; font-size:10px; color:#64748b;'>Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</p>
                </div>";

            $mail->send();
            $succes = "Un lien de secours a été envoyé à votre adresse mail.";
        } else {
            // Pour la sécurité, on ne dit pas si le mail existe ou pas, mais ici on va le faire pour tes tests
            $erreur = "Cette adresse email n'est pas enregistrée dans le système.";
        }
    } catch (Exception $e) {
        $erreur = "Erreur lors de l'envoi : " . $mail->ErrorInfo;
    } catch (PDOException $e) {
        $erreur = "Erreur BDD : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MELAINA - Récupération</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { 
            background-color: #020617; 
            color: #0ea5e9; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
        }
        .login-box {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid #f59e0b;
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
            padding: 12px; 
            background: transparent; 
            border: 1px solid #334155; 
            color: #fff; 
            outline: none; 
            font-size: 16px; 
            border-radius: 4px;
        }
        .input-group input:focus { border-color: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.5); }
        .btn-submit { 
            width: 100%; 
            padding: 15px; 
            background: #f59e0b; 
            color: #020617; 
            border: none; 
            font-weight: bold; 
            cursor: pointer; 
            border-radius: 4px;
            text-transform: uppercase; 
            font-size: 14px;
        }
        .error-msg { color: #ef4444; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
        .success-msg { color: #10b981; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>RÉCUPÉRATION</h2>
        <?php if($erreur) echo "<div class='error-msg'>[!] $erreur</div>"; ?>
        <?php if($succes) echo "<div class='success-msg'>[OK] $succes</div>"; ?>
        
        <form method="POST">
            <div class="input-group">
                <label>ENTREZ VOTRE EMAIL_ID</label>
                <input type="email" name="email" required placeholder="ex: carole@madebylucas.fr">
            </div>
            <button type="submit" class="btn-submit">ENVOYER LIEN DE SECOURS</button>
        </form>
        <br>
        <a href="login.php" style="color:#64748b; font-size:14px; text-decoration:none;">Retour au login</a>
    </div>
</body>
</html>