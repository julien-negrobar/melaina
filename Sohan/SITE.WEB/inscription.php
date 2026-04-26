<?php
session_start();

$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user = "madeb2677953_2zjnm";
$pass = "wddzero3it"; 

$erreur = "";
$inscription_reussie = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. VÉRIFICATION DU GOOGLE RECAPTCHA ---
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        
        // ⚠️ 1. COLLE TA CLÉ SECRÈTE GOOGLE JUSTE EN DESSOUS ⚠️
        $secretKey = "6LfhOqQsAAAAAKCoPhhPoFSiuTxwxXPXJ9OtjyX8";
        
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secretKey.'&response='.$_POST['g-recaptcha-response']);
        $responseData = json_decode($verifyResponse);

        if ($responseData->success) {
            // Le Captcha est validé, on lance l'inscription
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $identifiant = trim($_POST['identifiant']);
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $email = trim($_POST['email']);
                $mot_de_passe_saisi = $_POST['mot_de_passe'];
                $confirmation_mdp = $_POST['confirmation_mdp']; // On récupère la confirmation

                // --- 2. VÉRIFICATION DE LA CONFIRMATION DU MOT DE PASSE ---
                if ($mot_de_passe_saisi !== $confirmation_mdp) {
                    $erreur = "Erreur : Les mots de passe ne correspondent pas.";
                } else {
                    // Les mots de passe correspondent, on continue
                    $stmt_check = $pdo->prepare("SELECT id_admin FROM administrateur WHERE identifiant = :id OR email = :email");
                    $stmt_check->execute(['id' => $identifiant, 'email' => $email]);
                    
                    if ($stmt_check->fetch()) {
                        $erreur = "Identifiant ou Email déjà utilisé dans la base.";
                    } else {
                        $mdp_hache = password_hash($mot_de_passe_saisi, PASSWORD_DEFAULT);
                        $stmt_insert = $pdo->prepare("INSERT INTO administrateur (identifiant, nom, prenom, email, mot_de_passe) VALUES (:id, :nom, :prenom, :email, :mdp)");
                        
                        if ($stmt_insert->execute(['id' => $identifiant, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'mdp' => $mdp_hache])) {
                            $inscription_reussie = true; // Succès !
                        } else {
                            $erreur = "Erreur système lors de l'enregistrement.";
                        }
                    }
                }
            } catch (PDOException $e) { $erreur = "Erreur de connexion à la base de données."; }
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
    <title>MELAINA - Inscription</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { background-color: #020617; color: #10b981; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        
        .login-box { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); border: 1px solid #10b981; padding: 30px; border-radius: 8px; width: 100%; max-width: 450px; text-align: center; }
        
        .input-group { text-align: left; margin-bottom: 15px; position: relative; }
        .input-group label { display: block; margin-bottom: 5px; font-size: 12px; color: #64748b; }
        
        input:-webkit-autofill { -webkit-box-shadow: 0 0 0 30px #0f172a inset !important; -webkit-text-fill-color: #fff !important; }
        .input-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.3); border: 1px solid #334155; color: #fff; border-radius: 4px; outline: none; font-size: 16px; }
        .toggle-password { position: absolute; right: 12px; top: 35px; cursor: pointer; color: #64748b; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: #10b981; }

        .strength-container { width: 100%; height: 4px; background: #334155; margin-top: 8px; border-radius: 10px; display: none; }
        .strength-bar { height: 100%; width: 0%; transition: 0.4s; }
        .strength-text { font-size: 10px; margin-top: 5px; text-transform: uppercase; font-weight: bold; display: none; }

        .btn-submit { width: 100%; padding: 14px; background: #10b981; color: #020617; border: none; font-weight: bold; cursor: pointer; text-transform: uppercase; margin-top: 10px; transition: 0.3s; border-radius: 4px;}
        .btn-submit:hover { background: #059669; box-shadow: 0 0 15px rgba(16, 185, 129, 0.5); }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 6, 23, 0.9); backdrop-filter: blur(5px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #0f172a; border: 2px solid #10b981; padding: 40px; border-radius: 8px; text-align: center; max-width: 350px; box-shadow: 0 0 30px rgba(16, 185, 129, 0.3); }
        .btn-modal { display: inline-block; margin-top: 25px; padding: 10px 20px; background: #10b981; color: #020617; text-decoration: none; font-weight: bold; border-radius: 4px; text-transform: uppercase; }

        .error-msg { color: #ef4444; font-size: 14px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <i class="fas fa-check-circle" style="font-size: 50px; color: #10b981; margin-bottom: 15px;"></i>
            <h2 style="color: #fff; margin-bottom: 10px;">C'est tout bon !</h2>
            <p style="color: #cbd5e1; font-family: sans-serif; font-size: 14px;">Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.</p>
            <a href="login.php" class="btn-modal">Retour à la connexion</a>
        </div>
    </div>

    <div class="login-box">
        <h2 style="margin-bottom: 20px;">CRÉER UN COMPTE</h2>
        <?php if($erreur) echo "<div class='error-msg'>[!] $erreur</div>"; ?>

        <form method="POST">
            <div style="display:flex; gap:10px;">
                <div class="input-group"><label>NOM</label><input type="text" name="nom" required></div>
                <div class="input-group"><label>PRÉNOM</label><input type="text" name="prenom" required></div>
            </div>
            <div class="input-group"><label>EMAIL</label><input type="email" name="email" required></div>
            <div class="input-group"><label>PSEUDO</label><input type="text" name="identifiant" required></div>
            
            <div class="input-group">
                <label>MOT DE PASSE</label>
                <input type="password" name="mot_de_passe" id="password-field-1" required>
                <i class="fas fa-eye toggle-password" id="toggle-icon-1"></i>
                
                <div class="strength-container" id="strength-container"><div class="strength-bar" id="strength-bar"></div></div>
                <span class="strength-text" id="strength-text"></span>
            </div>

            <div class="input-group">
                <label>CONFIRMER LE MOT DE PASSE</label>
                <input type="password" name="confirmation_mdp" id="password-field-2" required>
                <i class="fas fa-eye toggle-password" id="toggle-icon-2"></i>
            </div>
            
            <div style="display:flex; justify-content:center; margin-top: 10px; margin-bottom:15px;">
                <div class="g-recaptcha" data-sitekey="6LfhOqQsAAAAAPwIciDFk2VBCT9Gi-rN77dkbB0D" data-theme="dark"></div>
            </div>

            <button type="submit" class="btn-submit">S'INSCRIRE</button>
            <br><br>
            <a href="login.php" style="color:#64748b; font-size:12px; text-decoration:none;">Retour à la connexion</a>
        </form>
    </div>

    <script>
        // --- 1. L'Œil pour le mot de passe 1 ---
        const pass1 = document.getElementById('password-field-1');
        const icon1 = document.getElementById('toggle-icon-1');
        icon1.addEventListener('click', () => {
            pass1.type = pass1.type === 'password' ? 'text' : 'password';
            icon1.classList.toggle('fa-eye'); 
            icon1.classList.toggle('fa-eye-slash');
        });

        // --- 2. L'Œil pour la confirmation (mot de passe 2) ---
        const pass2 = document.getElementById('password-field-2');
        const icon2 = document.getElementById('toggle-icon-2');
        icon2.addEventListener('click', () => {
            pass2.type = pass2.type === 'password' ? 'text' : 'password';
            icon2.classList.toggle('fa-eye'); 
            icon2.classList.toggle('fa-eye-slash');
        });

        // --- 3. L'Indicateur de Force ---
        const strengthBar = document.getElementById('strength-bar');
        const strengthContainer = document.getElementById('strength-container');
        const strengthText = document.getElementById('strength-text');

        pass1.addEventListener('input', () => {
            const val = pass1.value;
            
            if (val.length > 0) {
                strengthContainer.style.display = 'block';
                strengthText.style.display = 'block';
                
                let hasNum = /[0-9]/.test(val);
                let hasSpec = /[^A-Za-z0-9]/.test(val);
                let strength = 1;

                if (val.length < 6) {
                    strength = 1; 
                } else if (val.length >= 6 && val.length <= 10) {
                    if (hasSpec || hasNum) { strength = 2; } else { strength = 1; }
                } else if (val.length > 10) {
                    if (hasSpec && hasNum) { strength = 3; } else { strength = 2; }
                }

                if (strength === 1) {
                    strengthBar.style.width = "33%";
                    strengthBar.style.background = "#ef4444"; 
                    strengthText.innerText = "FORCE : FAIBLE";
                    strengthText.style.color = "#ef4444";
                } else if (strength === 2) {
                    strengthBar.style.width = "66%";
                    strengthBar.style.background = "#f59e0b"; 
                    strengthText.innerText = "FORCE : FORT";
                    strengthText.style.color = "#f59e0b";
                } else if (strength === 3) {
                    strengthBar.style.width = "100%";
                    strengthBar.style.background = "#10b981"; 
                    strengthText.innerText = "FORCE : ÉLEVÉ";
                    strengthText.style.color = "#10b981";
                }

            } else {
                strengthContainer.style.display = 'none';
                strengthText.style.display = 'none';
            }
        });

        // --- 4. POP-UP ---
        <?php if($inscription_reussie): ?>
            document.getElementById('successModal').style.display = 'flex';
        <?php endif; ?>
    </script>
</body>
</html>