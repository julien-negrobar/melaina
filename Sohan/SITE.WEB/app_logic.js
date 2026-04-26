// app_logic.js (Exemple)

function enableEdition(id) {
    const input = document.getElementById(id);
    input.removeAttribute('readonly');
    input.classList.add('active-input');
    input.focus();
    input.onblur = () => {
        input.setAttribute('readonly', true);
        input.classList.remove('active-input');
        // Ici, tu pourrais envoyer la nouvelle valeur à un serveur
        console.log(`Nom de ruche "${id}" mis à jour en: ${input.value}`);
    };
}

// Pour le dashboard (webapp.html), simuler l'affichage des ruches actives
document.addEventListener('DOMContentLoaded', () => {
    const activeHivesSpan = document.getElementById('active-hives');
    if (activeHivesSpan) {
        // Tu peux rendre ceci dynamique en comptant les ruches de la table
        // Pour l'instant, c'est statique
        activeHivesSpan.textContent = document.querySelectorAll('#hive-table-body tr').length;
    }
});