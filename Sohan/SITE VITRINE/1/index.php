<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeeSecure - L'Apiculture du Futur</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav id="navbar">
        <div class="logo">
            <i class="fas fa-hexagon-nodes pulse-icon"></i> BeeSecure
        </div>
        <ul class="nav-links">
            <li><a href="#home">Accueil</a></li>
            <li><a href="#features">Tech</a></li>
            <li><a href="#demo">Démo</a></li>
            <li><a href="http://beeapp.madebylucas.fr/index.php" class="nav-cta"><i class="fas fa-rocket"></i> Lancer l'App</a></li>
        </ul>
        <div class="burger">
            <div class="line1"></div>
            <div class="line2"></div>
            <div class="line3"></div>
        </div>
    </nav>

    <header id="home">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <div class="badge-new">VERSION 9.0 DISPONIBLE</div>
            <h1 class="glitch-text">PROTÉGEZ VOTRE <br><span class="gradient-text">OR JAUNE</span></h1>
            <p>La première solution de surveillance apicole utilisant l'IA prédictive et la sécurisation LoRaWAN.</p>
            <div class="hero-buttons">
                <a href="http://beeapp.madebylucas.fr/index.php" class="btn-primary">
                    Accéder au Cockpit <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#features" class="btn-outline">
                    En savoir plus
                </a>
            </div>
            
            <div class="floating-stats">
                <div class="stat-bubble">
                    <i class="fas fa-shield-alt"></i> 100% Sécurisé
                </div>
                <div class="stat-bubble">
                    <i class="fas fa-bolt"></i> Temps Réel
                </div>
            </div>
        </div>
    </header>

    <section id="features">
        <div class="container">
            <h2 class="section-title">TECHNOLOGIE <span class="highlight">MILITAIRE</span></h2>
            <div class="cards-grid">
                <div class="holo-card reveal">
                    <div class="card-content">
                        <div class="icon-box blue"><i class="fas fa-lock"></i></div>
                        <h3>Chiffrement AES-256</h3>
                        <p>Vos données transitent dans un tunnel crypté inviolable. Personne ne touche à vos ruches.</p>
                    </div>
                </div>
                <div class="holo-card reveal">
                    <div class="card-content">
                        <div class="icon-box gold"><i class="fas fa-brain"></i></div>
                        <h3>I.A. Melaina</h3>
                        <p>Notre intelligence artificielle analyse le comportement des abeilles pour prédire les essaimages.</p>
                    </div>
                </div>
                <div class="holo-card reveal">
                    <div class="card-content">
                        <div class="icon-box red"><i class="fas fa-satellite"></i></div>
                        <h3>Anti-Vol GPS</h3>
                        <p>Géolocalisation précise à 2 mètres près avec alertes de mouvement instantanées.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="demo" class="demo-section">
        <div class="container split-layout">
            <div class="demo-text reveal">
                <h2>Toute votre exploitation <br>dans votre poche.</h2>
                <ul class="check-list">
                    <li><i class="fas fa-check-circle"></i> Notifications Push en cas de danger</li>
                    <li><i class="fas fa-check-circle"></i> Historique météo et production</li>
                    <li><i class="fas fa-check-circle"></i> Compatible iOS & Android</li>
                </ul>
               <a href="https://melaina.madebylucas.fr/melaina.apk" class="btn-store" download>
                    <i class="fab fa-google-play"></i>
                    <div>
                        <span>DISPONIBLE SUR</span>
                        <b>Google Play</b>
                    </div>
                </a>
            </div>
            <div class="demo-phone reveal">
                <div class="phone-frame">
                    <div class="phone-screen">
                        <div class="screen-header">
                            <span>BeeSecure</span>
                            <i class="fas fa-wifi"></i>
                        </div>
                        <div class="screen-content">
                            <div class="screen-card alert">
                                <i class="fas fa-exclamation-triangle"></i> ALERTE VOL
                            </div>
                            <div class="screen-card">
                                <span>Température</span>
                                <b>34.5°C</b>
                            </div>
                            <div class="screen-graph"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<section id="contact" class="contact-section">
        <div class="container">
            <h2 class="section-title">CENTRE DE <span class="highlight">SUPPORT</span></h2>
            
            <div class="contact-grid">
                <div class="contact-info reveal">
                    <h3><i class="fas fa-headset"></i> Canal Direct</h3>
                    <p>Une anomalie détectée ? Nos ingénieurs interviennent sous 24h.</p>
                    
                    <div class="info-item">
                        <div class="icon-circle"><i class="fas fa-envelope"></i></div>
                        <div>
                            <span>Email Prioritaire</span>
                            <a href="mailto:melainaproject@gmail.com">melainaproject@gmail.com</a>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="icon-circle"><i class="fas fa-phone-alt"></i></div>
                        <div>
                            <span>Ligne Urgence</span>
                            <a href="tel:+596696123456" style="color: inherit; text-decoration: none;">06 96 12 34 56</a>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="icon-circle"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <span>QG Opérationnel</span>
                            <p>Lycée Joseph Gaillard</p>
                        </div>
                    </div>
                </div>

                <form class="cyber-form reveal">
                    <div class="form-group">
                        <label>IDENTIFIANT / NOM</label>
                        <input type="text" placeholder="Entrez votre nom..." required>
                    </div>
                    <div class="form-group">
                        <label>EMAIL DE RÉPONSE</label>
                        <input type="email" placeholder="nom@exemple.com" required>
                    </div>
                    <div class="form-group">
                        <label>RAPPORT D'INCIDENT</label>
                        <textarea rows="5" placeholder="Décrivez le problème..." required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        ENVOYER TRANSMISSION <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-logo">BeeSecure</div>
            <p>Projet BTS CIEL - Innovation Apicole</p>
            </div>
        <div class="copyright">
            &copy; 2026 BeeSecure System. All rights reserved.
        </div>
    </footer>

    <script src="script.js"></script>
</body>

 


    <div class="chatbot-container">
        <div class="chatbot-toggle" id="chatbot-toggle">
            <i class="fas fa-robot"></i>
        </div>
        
        <div class="chatbot-window" id="chatbot-window">
            <div class="chatbot-header">
                <div><i class="fas fa-brain"></i> I.A. MELAINA</div>
                <button id="chatbot-close"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="chatbot-messages" id="chatbot-messages">
                <div class="message bot-message">Bonjour ! Je suis l'Intelligence Artificielle MELAINA. Avez-vous des questions sur notre système de surveillance apicole ?</div>
            </div>
            
            <div class="chatbot-input">
                <input type="text" id="chat-input" placeholder="Posez votre question...">
                <button id="chat-send"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>