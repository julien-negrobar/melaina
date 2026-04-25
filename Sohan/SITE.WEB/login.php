<?php
session_start();

// Si l'utilisateur est déjà connecté, on l'envoie direct sur l'accueil
if (isset($_SESSION['admin_connecte']) && $_SESSION['admin_connecte'] === true) {
    header("Location: index.php");
    exit();
}

$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it"; 

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. VÉRIFICATION DU GOOGLE RECAPTCHA ---
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        
        // ⚠️ 1. COLLE TA CLÉ SECRÈTE GOOGLE JUSTE EN DESSOUS (entre les guillemets) ⚠️
        $secretKey = "6LfhOqQsAAAAAKCoPhhPoFSiuTxwxXPXJ9OtjyX8";
        
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secretKey.'&response='.$_POST['g-recaptcha-response']);
        $responseData = json_decode($verifyResponse);

        if ($responseData->success) {
            // Le Captcha est validé (c'est bien un humain), on lance la connexion
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $login_input = trim($_POST['identifiant']);
                $mot_de_passe_saisi = $_POST['mot_de_passe'];

                $stmt = $pdo->prepare("SELECT * FROM administrateur WHERE identifiant = :login OR email = :login");
                $stmt->execute(['login' => $login_input]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($mot_de_passe_saisi, $admin['mot_de_passe'])) {
                    $_SESSION['admin_connecte'] = true;
                    $_SESSION['nom_admin'] = $admin['identifiant'];
                    
                    // 🚨 LA FAMEUSE LIGNE QUI DONNE LE BADGE :
                    $_SESSION['id_admin'] = $admin['id_admin']; 
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $erreur = "Identifiants incorrects. Accès refusé.";
                }
            } catch (PDOException $e) { 
                $erreur = "Erreur de connexion au système."; 
            }
        } else {
            $erreur = "Échec de la validation anti-robot.";
        }
    } else {
        $erreur = "Veuillez cocher la case 'Je ne suis pas un robot'.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MELAINA - Connexion</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { background-color: #020617; color: #0ea5e9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px;}
        .login-box { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); border: 1px solid #0ea5e9; padding: 40px; border-radius: 8px; width: 100%; max-width: 400px; text-align: center; }
        .login-box h2 { color: #0ea5e9; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 2px; }
        
        .input-group { margin-bottom: 20px; text-align: left; position: relative; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        
        input:-webkit-autofill { -webkit-box-shadow: 0 0 0 30px #0f172a inset !important; -webkit-text-fill-color: #fff !important; }
        .input-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.3); border: 1px solid #334155; color: #fff; border-radius: 4px; outline: none; transition: border 0.3s; font-size: 16px;}
        .input-group input:focus { border-color: #0ea5e9; box-shadow: 0 0 8px rgba(14, 165, 233, 0.5); }
        
        .toggle-password { position: absolute; right: 12px; top: 37px; cursor: pointer; color: #64748b; transition: 0.3s; }
        .toggle-password:hover { color: #0ea5e9; }

        .btn-submit { width: 100%; padding: 14px; background: #0ea5e9; color: #020617; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; text-transform: uppercase; transition: 0.3s; margin-bottom: 20px; }
        .btn-submit:hover { background: #38bdf8; box-shadow: 0 0 15px rgba(14, 165, 233, 0.5); }
        .error-msg { color: #ef4444; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
        .logo-system { font-size: 40px; margin-bottom: 10px; color: #fff; }
        .links-container { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; }
        .action-link { color: #64748b; font-size: 14px; text-decoration: none; transition: 0.3s; }
        .action-link:hover { color: #0ea5e9; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo-system">🐝</div>
        <h2>Se connecter</h2>

        <?php if (!empty($erreur)): ?>
            <div class="error-msg">[!] <?php echo $erreur; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label>Pseudo ou Email</label>
                <input type="text" name="identifiant" required autocomplete="off" placeholder="Ex: Carole">
            </div>
            
            <div class="input-group">
                <label>Mot de passe</label>
                <input type="password" name="mot_de_passe" id="password-field" required placeholder="">
                <i class="fas fa-eye toggle-password" id="toggle-icon"></i>
            </div>

            <div style="display:flex; justify-content:center; margin-bottom:20px;">
                <div class="g-recaptcha" data-sitekey="6LfhOqQsAAAAAPwIciDFk2VBCT9Gi-rN77dkbB0D" data-theme="dark"></div>
            </div>

            <button type="submit" class="btn-submit">Valider</button>
        </form>

        <div class="links-container">
            <a href="inscription.php" class="action-link">Créer un compte</a>
            <a href="mot_de_passe_oublie.php" class="action-link">Mot de passe oublié ?</a>
        </div>
    </div>

    <script>
        const passwordField = document.getElementById('password-field');
        const toggleIcon = document.getElementById('toggle-icon');

        toggleIcon.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>