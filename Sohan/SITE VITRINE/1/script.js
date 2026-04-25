// =========================================
// MENU BURGER ET NAVIGATION
// =========================================
const navSlide = () => {
    const burger = document.querySelector('.burger');
    const nav = document.querySelector('.nav-links');
    const navLinks = document.querySelectorAll('.nav-links li');

    burger.addEventListener('click', () => {
        // Toggle Nav
        nav.classList.toggle('nav-active');

        // Animate Links
        navLinks.forEach((link, index) => {
            if (link.style.animation) {
                link.style.animation = '';
            } else {
                link.style.animation = `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
            }
        });

        // Burger Animation
        burger.classList.toggle('toggle');
    });
}

navSlide();

// =========================================
// ANIMATION AU SCROLL (REVEAL)
// =========================================
window.addEventListener('scroll', reveal);

function reveal() {
    var reveals = document.querySelectorAll('.reveal');

    for (var i = 0; i < reveals.length; i++) {
        var windowheight = window.innerHeight;
        var revealtop = reveals[i].getBoundingClientRect().top;
        var revealpoint = 150;

        if (revealtop < windowheight - revealpoint) {
            reveals[i].classList.add('active');
        } else {
            reveals[i].classList.remove('active');
        }
    }
}

// =========================================
// CHATBOT I.A. MELAINA 2.0 LOGIQUE (CONNECTÉ À L'API)
// =========================================
document.addEventListener("DOMContentLoaded", () => {
    const toggleBtn = document.getElementById("chatbot-toggle");
    const windowChat = document.getElementById("chatbot-window");
    const closeBtn = document.getElementById("chatbot-close");
    const sendBtn = document.getElementById("chat-send");
    const inputChat = document.getElementById("chat-input");
    const messagesContainer = document.getElementById("chatbot-messages");

    let isBotTyping = false; // Empêche le spam

    // Ouvrir / Fermer la fenêtre
    if (toggleBtn && windowChat && closeBtn) {
        toggleBtn.addEventListener("click", () => {
            windowChat.classList.add("active");
            if(messagesContainer.children.length <= 1) {
                showQuickReplies(["Quel est le prix ?", "Comment ça marche ?", "En cas de vol ?", "Nous contacter"]);
            }
        });
        closeBtn.addEventListener("click", () => windowChat.classList.remove("active"));
    }

    // Ajouter un message texte
    function addMessage(text, sender) {
        const msgDiv = document.createElement("div");
        msgDiv.classList.add("message", sender === "user" ? "user-message" : "bot-message");
        msgDiv.innerHTML = text; // Permet d'afficher les balises HTML envoyées par le PHP
        messagesContainer.appendChild(msgDiv);
        scrollToBottom();
    }

    // Ajouter l'animation "En train d'écrire..."
    function showTypingIndicator() {
        const typingDiv = document.createElement("div");
        typingDiv.classList.add("message", "bot-message", "typing-indicator");
        typingDiv.id = "typing-indicator";
        typingDiv.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
        messagesContainer.appendChild(typingDiv);
        scrollToBottom();
    }

    // Supprimer l'animation "En train d'écrire..."
    function hideTypingIndicator() {
        const typingDiv = document.getElementById("typing-indicator");
        if (typingDiv) typingDiv.remove();
    }

    // Afficher des boutons cliquables (Suggestions)
    function showQuickReplies(options) {
        const repliesContainer = document.createElement("div");
        repliesContainer.classList.add("quick-replies");
        
        options.forEach(option => {
            const btn = document.createElement("button");
            btn.classList.add("quick-reply-btn");
            btn.textContent = option;
            btn.addEventListener("click", () => {
                repliesContainer.remove(); // Retire les boutons une fois cliqués
                processUserMessage(option);
            });
            repliesContainer.appendChild(btn);
        });
        
        messagesContainer.appendChild(repliesContainer);
        scrollToBottom();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Analyser et répondre à l'utilisateur via l'API PHP
    async function processUserMessage(userText) {
        if (isBotTyping || userText.trim() === "") return;

        // 1. Affiche le message de l'utilisateur
        addMessage(userText, "user");
        inputChat.value = "";
        isBotTyping = true; // Bloque les autres envois

        // 2. Retire les anciens boutons de suggestion s'il y en a encore
        const oldReplies = document.querySelectorAll('.quick-replies');
        oldReplies.forEach(el => el.remove());

        // 3. Affiche "MELAINA écrit..."
        showTypingIndicator();

        try {
            // 4. Appel au fichier PHP qui contient la clé API et le prompt
            const response = await fetch('api_bot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: userText })
            });

            const data = await response.json();
            
            // 5. Supprime l'animation "écrit..." et affiche la vraie réponse de Gemini
            hideTypingIndicator();
            addMessage(data.reply, "bot");

        } catch (error) {
            console.error("Erreur de connexion à l'IA :", error);
            hideTypingIndicator();
            addMessage("<b>Erreur de transmission.</b><br>Impossible de joindre le serveur principal. L'équipe d'ingénieurs est sur le coup.", "bot");
        } finally {
            isBotTyping = false; // Débloque le chat
        }
    }

    // Déclencheurs (Clic bouton ou touche Entrée)
    if (sendBtn) sendBtn.addEventListener("click", () => processUserMessage(inputChat.value));
    if (inputChat) {
        inputChat.addEventListener("keypress", (e) => {
            if (e.key === "Enter") processUserMessage(inputChat.value);
        });
    }
});