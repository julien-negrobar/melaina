// ANIMATION AU SCROLL (REVEAL)
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
// CHATBOT I.A. MELAINA - VERSION AUTONOME (SÉCURITÉ EXAMEN)
// =========================================
document.addEventListener("DOMContentLoaded", () => {
    const toggleBtn = document.getElementById("chatbot-toggle");
    const windowChat = document.getElementById("chatbot-window");
    const closeBtn = document.getElementById("chatbot-close");
    const sendBtn = document.getElementById("chat-send");
    const inputChat = document.getElementById("chat-input");
    const messagesContainer = document.getElementById("chatbot-messages");

    // Ouvrir / Fermer la fenêtre (avec la croix)
    if (toggleBtn && windowChat && closeBtn) {
        toggleBtn.addEventListener("click", () => windowChat.classList.add("active"));
        closeBtn.addEventListener("click", () => windowChat.classList.remove("active"));
    }

    // Le cerveau local de l'IA (Fiable à 100% sans internet)
    const aiBrain = [
        {
            keywords: ["bonjour", "salut", "coucou", "hello"],
            answer: "Bonjour ! Je suis l'I.A. <b>MELAINA</b>. Je protège vos ruches. Que voulez-vous savoir ?"
        },
        {
            keywords: ["vol", "vole", "voler", "mouvement", "gps", "deplacer", "localisation"],
            answer: "🚨 <b>Anti-Vol GPS :</b> Si la ruche est déplacée, mon capteur détecte le mouvement et vous envoie la position exacte."
        },
        {
            keywords: ["temperature", "température", "chaud", "froid", "chaleur", "climat", "degre"],
            answer: "🌡️ <b>Température :</b> Je surveille la chaleur interne. Si on dépasse les <b>38°C</b>, je vous préviens pour sauver l'essaim."
        },
        {
            keywords: ["poids", "peser", "lourd", "miel", "recolte", "balance", "kilo"],
            answer: "⚖️ <b>Balance Connectée :</b> J'analyse le poids. Vous saurez exactement quand récolter votre miel sans déranger les abeilles."
        },
        {
            keywords: ["prix", "coute", "tarif", "euro", "combien"],
            answer: "💶 <b>Tarif :</b> BeeSecure est un prototype de BTS CIEL. Le coût matériel (Raspberry, capteurs) revient à environ <b>600€ par ruche</b>."
        },
        {
            keywords: ["contact", "mail", "telephone", "appeler", "joindre", "aide", "support"],
            answer: "📞 <b>Support :</b> Contactez-nous par mail à <b>melainaprojet@gmail.com</b> ou au <b>+596 696 66 15 45</b>."
        }
    ];

    // Ajouter un message dans le chat
    function addMessage(text, sender) {
        const msgDiv = document.createElement("div");
        msgDiv.classList.add("message", sender === "user" ? "user-message" : "bot-message");
        msgDiv.innerHTML = text; 
        messagesContainer.appendChild(msgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Nettoyer le texte (Enlever les accents pour éviter les erreurs)
    function removeAccents(str) {
        return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    // Gérer l'envoi
    function handleSend() {
        let userText = inputChat.value.trim();
        if (userText === "") return;

        // 1. Affiche la question de l'utilisateur
        addMessage(userText, "user");
        inputChat.value = "";

        // 2. Affiche "Analyse en cours..." temporairement pour faire illusion
        addMessage('<i class="fas fa-spinner fa-spin"></i> Analyse en cours...', "bot");
        const loadingElement = messagesContainer.lastChild;

        // 3. Nettoie la phrase (minuscules + sans accents)
        let cleanedText = removeAccents(userText.toLowerCase());
        
        // 4. L'IA "réfléchit" pendant 1 seconde
        setTimeout(() => {
            loadingElement.remove(); // Enlève le message de chargement
            
            let finalAnswer = "Je ne suis pas sûre de comprendre. Demandez-moi des infos sur le <b>vol</b>, la <b>température</b>, le <b>poids</b> ou le <b>prix</b>.";
            
            // Cherche la correspondance dans le cerveau
            for (let i = 0; i < aiBrain.length; i++) {
                let category = aiBrain[i];
                let found = category.keywords.some(keyword => cleanedText.includes(keyword));
                
                if (found) {
                    finalAnswer = category.answer;
                    break;
                }
            }
            
            // Affiche la vraie réponse
            addMessage(finalAnswer, "bot");
        }, 1000); // 1 seconde de réflexion
    }

    // Déclencheurs (Clic ou Entrée)
    if (sendBtn) sendBtn.addEventListener("click", handleSend);
    if (inputChat) {
        inputChat.addEventListener("keypress", (e) => {
            if (e.key === "Enter") handleSend();
        });
    }
});