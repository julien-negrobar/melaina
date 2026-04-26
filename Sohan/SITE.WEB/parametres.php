<?php
session_start();
if (!isset($_SESSION['admin_connecte']) || $_SESSION['admin_connecte'] !== true) {
    header("Location: login.php");
    exit();
}

// --- SÉCURITÉ SESSION ---
if (!isset($_SESSION['id_admin'])) {
    die("Erreur de session : Veuillez vous reconnecter.");
}

$id_utilisateur = $_SESSION['id_admin'];
$fichier_config = 'config.json';

// ==========================================
// CONNEXION BDD POUR LE PROFIL
// ==========================================
$host = "127.0.0.1";
$dbname = "madeb2677953_2zjnm";
$user_db = "madeb2677953_2zjnm";
$pass_db = "wddzero3it";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user_db, $pass_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur BDD");
}

// IMPORTATION DE PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==========================================
// REQUÊTES AJAX (AUTO-SAVE & MOT DE PASSE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ACTION 1 : DEMANDE DE CODE (OTP) AVEC PHPMAILER ---
    if (isset($_POST['action']) && $_POST['action'] === 'request_otp') {
        
        $stmt = $pdo->prepare("SELECT email FROM administrateur WHERE id_admin = :id");
        $stmt->execute(['id' => $id_utilisateur]);
        $user_email = $stmt->fetchColumn();
        
        if (empty($user_email)) {
            echo json_encode(['status' => 'error', 'msg' => 'Aucune adresse e-mail associée à ce compte.']);
            exit();
        }

        // Génération du code à 6 chiffres
        $code_otp = sprintf("%06d", mt_rand(1, 999999));
        $expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $upd = $pdo->prepare("UPDATE administrateur SET reset_token = :token, reset_expires_at = :exp WHERE id_admin = :id");
        $upd->execute(['token' => $code_otp, 'exp' => $expiration, 'id' => $id_utilisateur]);
        
        // --- BLOC PHPMAILER (CHEMINS CORRIGÉS) ---
        require 'phpmailer/Exception.php';
        require 'phpmailer/PHPMailer.php';
        require 'phpmailer/SMTP.php';

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8'; // Pour les accents
            $mail->isSMTP();
            $mail->Host       = 'mail.madebylucas.fr'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'system.melaina@madebylucas.fr'; 
            $mail->Password   = 'Melaina_972!'; // Ton mot de passe exact
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            $mail->Port       = 465; 

            $mail->setFrom('system.melaina@madebylucas.fr', 'BEE WEB Security');
            $mail->addAddress($user_email);

            $mail->isHTML(false);
            $mail->Subject = 'Code de sécurité - BEE WEB';
            $mail->Body    = "Bonjour,\n\nVous avez demandé la modification de votre mot de passe.\nVoici votre code de vérification (valable 10 minutes) :\n\nCODE : $code_otp\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.";

            $mail->send();
            
            echo json_encode(['status' => 'success', 'msg' => 'Code de sécurité envoyé par e-mail !']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => "Erreur d'envoi du mail : {$mail->ErrorInfo}"]);
        }
        exit();
    }

    // --- ACTION 2 : VÉRIFICATION ET MISE À JOUR ---
    if (isset($_POST['action']) && $_POST['action'] === 'verify_otp_and_update') {
        $otp_saisi = trim($_POST['otp_code']);
        $nouveau_mdp = $_POST['new_password'];
        
        $stmt = $pdo->prepare("SELECT id_admin FROM administrateur WHERE id_admin = :id AND reset_token = :token AND reset_expires_at > NOW()");
        $stmt->execute(['id' => $id_utilisateur, 'token' => $otp_saisi]);
        
        if ($stmt->rowCount() > 0) {
            $nouveau_hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE administrateur SET mot_de_passe = :mdp, reset_token = NULL, reset_expires_at = NULL WHERE id_admin = :id");
            $upd->execute(['mdp' => $nouveau_hash, 'id' => $id_utilisateur]);
            
            echo json_encode(['status' => 'success', 'msg' => 'Mot de passe mis à jour avec succès !']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Code invalide ou expiré (10 min max).']);
        }
        exit();
    }
    
    // --- AUTO-SAVE DES PARAMÈTRES CAPTEURS ---
    if (isset($_POST['seuil_froid'])) {
        $seuil_froid = floatval($_POST['seuil_froid']);
        $seuil_chaud = floatval($_POST['seuil_chaud']);
        $notif_push = isset($_POST['notif_push']) ? true : false;
        $sensibilite_gps = isset($_POST['sensibilite_gps']) ? $_POST['sensibilite_gps'] : 'Moyenne';
        
        $config_data = ['seuil_froid' => $seuil_froid, 'seuil_chaud' => $seuil_chaud, 'notif_push' => $notif_push, 'sensibilite_gps' => $sensibilite_gps];
        file_put_contents($fichier_config, json_encode($config_data));

        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'success', 'msg' => 'Paramètres sauvegardés']);
            exit();
        }
    }
}

// ==========================================
// RÉCUPÉRATION DES INFOS UTILISATEUR
// ==========================================
$stmt_info = $pdo->prepare("SELECT identifiant, nom, prenom, email FROM administrateur WHERE id_admin = :id");
$stmt_info->execute(['id' => $id_utilisateur]);
$info_user = $stmt_info->fetch(PDO::FETCH_ASSOC);

$nom_affiche = $info_user['nom'] ? htmlspecialchars($info_user['nom']) : 'Non renseigné';
$prenom_affiche = $info_user['prenom'] ? htmlspecialchars($info_user['prenom']) : 'Non renseigné';
$email_affiche = $info_user['email'] ? htmlspecialchars($info_user['email']) : 'Non renseigné';

$stmt_ruches = $pdo->prepare("SELECT COUNT(*) FROM ruche WHERE id_admin = :id");
$stmt_ruches->execute(['id' => $id_utilisateur]);
$nb_ruches = $stmt_ruches->fetchColumn();

// Lecture de la config capteurs
$seuil_froid = 34.0; $seuil_chaud = 35.5; $notif_push = true; $sensibilite_gps = 'Moyenne';
if (file_exists($fichier_config)) {
    $config_actuelle = json_decode(file_get_contents($fichier_config), true);
    if(isset($config_actuelle['seuil_froid'])) $seuil_froid = floatval($config_actuelle['seuil_froid']);
    if(isset($config_actuelle['seuil_chaud'])) $seuil_chaud = floatval($config_actuelle['seuil_chaud']);
    if(isset($config_actuelle['notif_push'])) $notif_push = $config_actuelle['notif_push'];
    if(isset($config_actuelle['sensibilite_gps'])) $sensibilite_gps = $config_actuelle['sensibilite_gps'];
}

$nom_user_session = isset($_SESSION['nom_admin']) ? $_SESSION['nom_admin'] : 'Utilisateur';
$initiale = strtoupper(substr($nom_user_session, 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEE WEB // PARAMÈTRES</title>
    <link rel="stylesheet" href="style_app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .setting-card { background: var(--panel-glass); border: 1px solid var(--border-glass); border-radius: 12px; padding: 25px; backdrop-filter: blur(10px); }
        .setting-card h3 { color: var(--neon-blue); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-top: 0; font-size: 1.1rem; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; color: var(--text-dim); margin-bottom: 8px; font-size: 0.85rem; font-family: var(--font-tech); }
        input[type="range"] { width: 100%; appearance: none; background: rgba(0,0,0,0.5); height: 8px; border-radius: 5px; outline: none; border: 1px solid rgba(255,255,255,0.1); }
        input[type="range"]::-webkit-slider-thumb { appearance: none; width: 20px; height: 20px; border-radius: 50%; background: var(--neon-blue); cursor: pointer; box-shadow: 0 0 10px var(--neon-blue); }
        input[id="seuil_froid"]::-webkit-slider-thumb { background: #0ea5e9; box-shadow: 0 0 10px #0ea5e9; }
        input[id="seuil_chaud"]::-webkit-slider-thumb { background: #ef4444; box-shadow: 0 0 10px #ef4444; }
        .cyber-input { width: 100%; background: rgba(0,0,0,0.5); color: white; border: 1px solid var(--neon-blue); padding: 10px; border-radius: 6px; outline: none; margin-bottom: 15px; font-family: var(--font-tech);}
        .cyber-input:focus { border-color: #38bdf8; box-shadow: 0 0 8px rgba(14, 165, 233, 0.4); }
        .cyber-input[readonly] { border-color: rgba(255,255,255,0.1); color: var(--text-dim); cursor: not-allowed; }
        .toggle-pwd { position: absolute; right: 12px; top: 38px; cursor: pointer; color: var(--text-dim); transition: 0.3s; }
        .toggle-pwd:hover { color: var(--neon-blue); }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.1); transition: .4s; border-radius: 34px; border: 1px solid rgba(255,255,255,0.2); }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--neon-blue); box-shadow: 0 0 15px rgba(14, 165, 233, 0.5); border-color: var(--neon-blue); }
        input:checked + .slider:before { transform: translateX(24px); }
        .toast { position: fixed; bottom: 30px; right: 30px; padding: 15px 25px; border-radius: 8px; display: none; z-index: 1000; font-weight: bold; font-family: var(--font-tech); animation: slideInUp 0.3s ease-out; }
        .toast-success { background: rgba(16, 185, 129, 0.9); border: 1px solid #10b981; color: white; box-shadow: 0 0 20px rgba(16, 185, 129, 0.4); }
        .toast-error { background: rgba(239, 68, 68, 0.9); border: 1px solid #ef4444; color: white; box-shadow: 0 0 20px rgba(239, 68, 68, 0.4); }
        .profile-banner { background: var(--panel-glass); border: 1px solid var(--border-glass); border-left: 4px solid var(--neon-blue); border-radius: 12px; padding: 20px 25px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .profile-avatar { width: 60px; height: 60px; background: var(--neon-blue); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; font-weight: bold; color: #020617; box-shadow: 0 0 15px rgba(14, 165, 233, 0.4); }
        .profile-info { display: flex; align-items: center; gap: 20px; }
        .stat-badge-mini { background: rgba(14, 165, 233, 0.1); border: 1px solid var(--neon-blue); border-radius: 8px; padding: 10px 20px; text-align: center; display: flex; align-items: center; gap: 15px;}
        .stat-badge-mini i { font-size: 1.5rem; color: var(--neon-gold); }
        .stat-badge-mini .num { font-size: 1.5rem; font-weight: bold; color: #fff; line-height: 1;}
        .stat-badge-mini .lbl { font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; }
        .profile-full-card { grid-column: 1 / -1; margin-top: 10px; }
        .profile-inner-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .btn-update { width: 100%; background: transparent; border: 1px solid var(--neon-blue); color: var(--neon-blue); padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-family: var(--font-tech); }
        .btn-update:hover { background: rgba(14, 165, 233, 0.1); box-shadow: 0 0 10px rgba(14, 165, 233, 0.3); }
        .btn-danger { border-color: #ef4444; color: #ef4444; }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.1); box-shadow: 0 0 10px rgba(239, 68, 68, 0.3); }

        @keyframes slideInUp { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } .profile-inner-grid { grid-template-columns: 1fr; } .profile-banner { flex-direction: column; align-items: flex-start; gap: 15px;} }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand"><i class="fas fa-hexagon-nodes"></i> BEE_WEB</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="nav-item"><i class="fas fa-th-large"></i> DASHBOARD</a>
                <a href="carte.php" class="nav-item"><i class="fas fa-globe-americas"></i> SATELLITE</a>
                <a href="monitoring.php" class="nav-item"><i class="fas fa-wave-square"></i> MONITORING AI</a>
                <a href="historique.php" class="nav-item"><i class="fas fa-history"></i> ARCHIVES</a>
                <a href="parametres.php" class="nav-item active"><i class="fas fa-cogs"></i> SYSTÈME</a>
                <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 20px; border-top: 1px solid #ef4444; padding-top: 15px;"><i class="fas fa-power-off"></i> DÉCONNEXION</a>
            </nav>
            <div class="sys-footer">
                <div style="margin-bottom: 5px;">
                    UTILISATEUR: <span style="color: #10b981; font-weight: bold; font-size: 1.1em;"><?php echo htmlspecialchars($nom_user_session); ?></span>
                </div>
                STATUS: <span style="color: #10b981;">ONLINE</span><br>
                ENCRYPTION: AES-256
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h2 style="margin:0;">PARAMÈTRES <b>SYSTÈME</b></h2>
                    <p class="page-subtitle" style="margin-top:5px;">CONFIGURATION ET COMPTE UTILISATEUR</p>
                </div>
            </header>

            <div class="profile-banner">
                <div class="profile-info">
                    <div class="profile-avatar"><?php echo $initiale; ?></div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: #fff;"><?php echo htmlspecialchars(ucfirst($nom_user_session)); ?></h3>
                        <div style="font-size: 0.8rem; color: #10b981; margin-top: 5px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> COMPTE SÉCURISÉ (Connecté)
                        </div>
                    </div>
                </div>
                
                <div class="stat-badge-mini">
                    <i class="fas fa-hexagon-nodes"></i>
                    <div>
                        <div class="num"><?php echo sprintf("%02d", $nb_ruches); ?></div>
                        <div class="lbl">Ruches Actives</div>
                    </div>
                </div>
            </div>

            <form id="autoSaveForm">
                <div class="settings-grid">
                    <div class="setting-card">
                        <h3><i class="fas fa-temperature-half"></i> GESTION DES ALERTES THERMIQUES</h3>
                        <div class="form-group" style="margin-top: 20px;">
                            <label>Seuil d'Alerte : Hypothermie (Arrêt travail)</label>
                            <div style="display:flex; justify-content:space-between; margin-bottom:10px; color:#0ea5e9; font-weight:bold;">
                                <span><i class="fas fa-snowflake"></i> Froid</span><span id="val_froid"><?php echo $seuil_froid; ?>°C</span>
                            </div>
                            <input type="range" name="seuil_froid" id="seuil_froid" class="auto-trigger" min="20" max="34.5" step="0.1" value="<?php echo $seuil_froid; ?>" oninput="document.getElementById('val_froid').innerText = this.value + '°C'">
                        </div>
                        <div class="form-group" style="margin-top: 30px;">
                            <label>Seuil d'Alerte : Surchauffe (Risque de fuite)</label>
                            <div style="display:flex; justify-content:space-between; margin-bottom:10px; color:#ef4444; font-weight:bold;">
                                <span><i class="fas fa-fire"></i> Chaud</span><span id="val_chaud"><?php echo $seuil_chaud; ?>°C</span>
                            </div>
                            <input type="range" name="seuil_chaud" id="seuil_chaud" class="auto-trigger" min="34.5" max="40" step="0.1" value="<?php echo $seuil_chaud; ?>" oninput="document.getElementById('val_chaud').innerText = this.value + '°C'">
                        </div>
                    </div>

                    <div class="setting-card">
                        <h3><i class="fas fa-satellite-dish"></i> SYSTÈME & GPS</h3>
                        <div class="form-group" style="margin-top: 20px; display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                            <div><label style="margin:0; color:#fff;">Notifications Push</label><span style="font-size:0.75rem; color:var(--text-dim);">Recevoir les alertes d'urgence.</span></div>
                            <label class="switch"><input type="checkbox" name="notif_push" class="auto-trigger" <?php echo $notif_push ? 'checked' : ''; ?>><span class="slider"></span></label>
                        </div>
                        <div class="form-group" style="margin-top: 20px;">
                            <label>Sensibilité Antivol (Capteur GPS)</label>
                            <select name="sensibilite_gps" class="cyber-input auto-trigger">
                                <option value="Haute" <?php echo ($sensibilite_gps=='Haute')?'selected':''; ?>>Haute (Alerte dès 1m de déplacement)</option>
                                <option value="Moyenne" <?php echo ($sensibilite_gps=='Moyenne')?'selected':''; ?>>Moyenne (Alerte à 5m)</option>
                                <option value="Basse" <?php echo ($sensibilite_gps=='Basse')?'selected':''; ?>>Basse (Alerte à 20m)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <div class="settings-grid">
                <div class="setting-card profile-full-card">
                    <h3><i class="fas fa-user-shield"></i> PROFIL & SÉCURITÉ</h3>
                    
                    <div class="profile-inner-grid">
                        <div>
                            <div class="form-group">
                                <label>Identifiant de connexion</label>
                                <input type="text" class="cyber-input" value="<?php echo htmlspecialchars($info_user['identifiant']); ?>" readonly>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <div class="form-group" style="flex:1;">
                                    <label>Prénom</label>
                                    <input type="text" class="cyber-input" value="<?php echo $prenom_affiche; ?>" readonly>
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Nom</label>
                                    <input type="text" class="cyber-input" value="<?php echo $nom_affiche; ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Adresse E-mail</label>
                                <input type="email" class="cyber-input" value="<?php echo $email_affiche; ?>" readonly>
                            </div>
                        </div>

                        <div>
                            <div id="pwd-step-1" style="margin-top: 25px; padding-top: 20px;">
                                <button type="button" class="btn-update" onclick="demanderCodeOTP()" id="btn-otp"><i class="fas fa-envelope"></i> DEMANDER CODE DE SÉCURITÉ</button>
                                <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 10px; text-align: center;">Un code de vérification vous sera envoyé par e-mail.</p>
                            </div>

                            <div id="pwd-step-2" style="display: none;">
                                <form id="verifyOtpForm">
                                    <input type="hidden" name="action" value="verify_otp_and_update">
                                    
                                    <div class="form-group">
                                        <label style="color: #10b981;"><i class="fas fa-check-circle"></i> Code de vérification (reçu par e-mail)</label>
                                        <input type="text" name="otp_code" class="cyber-input" required placeholder="Ex: 123456" maxlength="6" style="border-color: #10b981; font-weight: bold; letter-spacing: 2px;">
                                        <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 5px;">Ce code expire dans 10 minutes.</p>
                                    </div>

                                    <div class="form-group">
                                        <label>Nouveau mot de passe</label>
                                        <input type="password" name="new_password" id="new_password" class="cyber-input" required placeholder="••••••••">
                                        <i class="fas fa-eye toggle-pwd" onclick="toggleVisibility('new_password', this)"></i>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                                        <button type="submit" class="btn-update" style="flex: 2;"><i class="fas fa-check"></i> VALIDER</button>
                                        <button type="button" class="btn-update btn-danger" style="flex: 1;" onclick="annulerOTP()">ANNULER</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <div id="toast-box" class="toast"><i id="toast-icon" class="fas fa-check-circle"></i> <span id="toast-msg">MODIFICATIONS SAUVEGARDÉES</span></div>

    <script>
        function toggleVisibility(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

        const toast = document.getElementById('toast-box');
        const toastMsg = document.getElementById('toast-msg');
        const toastIcon = document.getElementById('toast-icon');
        let timeoutId;

        function showToast(message, type = 'success') {
            toastMsg.innerText = message;
            toast.className = 'toast toast-' + type;
            toastIcon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            toast.style.display = 'block';
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => { toast.style.display = 'none'; }, 5000);
        }

        // --- 1. DEMANDER LE CODE PAR MAIL ---
        function demanderCodeOTP() {
            const btn = document.getElementById('btn-otp');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVOI EN COURS...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'request_otp');

            fetch('parametres.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = '<i class="fas fa-envelope"></i> DEMANDER CODE DE SÉCURITÉ';
                btn.disabled = false;

                if(data.status === 'success') {
                    showToast(data.msg, 'success');
                    document.getElementById('pwd-step-1').style.display = 'none';
                    document.getElementById('pwd-step-2').style.display = 'block';
                } else {
                    showToast(data.msg, 'error');
                }
            });
        }

        // --- 2. ANNULER LA PROCÉDURE ---
        function annulerOTP() {
            document.getElementById('verifyOtpForm').reset();
            document.getElementById('pwd-step-2').style.display = 'none';
            document.getElementById('pwd-step-1').style.display = 'block';
            document.getElementById('new_password').type = 'password';
            document.querySelector('.toggle-pwd').classList.remove('fa-eye-slash');
            document.querySelector('.toggle-pwd').classList.add('fa-eye');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // AUTO-SAVE DES CAPTEURS
            const formConfig = document.getElementById('autoSaveForm');
            const inputs = formConfig.querySelectorAll('.auto-trigger');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    fetch('parametres.php', {
                        method: 'POST',
                        body: new FormData(formConfig),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => { if(data.status === 'success') showToast(data.msg, 'success'); });
                });
            });

            // VÉRIFICATION DU CODE ET MISE À JOUR DU MDP
            const verifyForm = document.getElementById('verifyOtpForm');
            verifyForm.addEventListener('submit', (e) => {
                e.preventDefault();
                fetch('parametres.php', {
                    method: 'POST',
                    body: new FormData(verifyForm),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success') {
                        showToast(data.msg, 'success');
                        annulerOTP(); // Réinitialise l'interface
                    } else {
                        showToast(data.msg, 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>