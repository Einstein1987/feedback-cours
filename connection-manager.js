/**
 * connection-manager.js
 * Gestion de la connexion et du heartbeat pour evalcours
 * 
 * Fonctionnalités :
 * - Heartbeat automatique toutes les 2 minutes
 * - Reconnexion automatique en cas d'erreur
 * - Gestion robuste des erreurs réseau
 */

// Configuration
const HEARTBEAT_INTERVAL = 120000; // 2 minutes
const RETRY_DELAY = 1000; // 1 seconde avant de réessayer
const MAX_RETRIES = 2; // Nombre maximum de tentatives

// Variables globales
let heartbeatTimer = null;
let connectionStatus = true;

/**
 * Démarre le système de heartbeat
 */
function startHeartbeat() {
    // Arrêter le timer existant si présent
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer);
    }
    
    // Premier ping immédiat
    pingServer();
    
    // Puis ping régulier toutes les 2 minutes
    heartbeatTimer = setInterval(function() {
        pingServer();
    }, HEARTBEAT_INTERVAL);
    
    console.log('Heartbeat démarré (ping toutes les 2 minutes)');
}

/**
 * Ping le serveur pour maintenir la connexion active
 */
function pingServer() {
    fetch('ping.php', {
        method: 'GET',
        cache: 'no-cache'
    })
    .then(response => {
        if (!response.ok) throw new Error('Ping failed');
        return response.json();
    })
    .then(data => {
        if (!connectionStatus) {
            console.log('Connexion rétablie');
            connectionStatus = true;
            updateConnectionStatus(true);
        }
    })
    .catch(error => {
        console.log('Ping échoué:', error);
        if (connectionStatus) {
            connectionStatus = false;
            updateConnectionStatus(false);
        }
    });
}

/**
 * Met à jour l'indicateur visuel de statut de connexion (optionnel)
 */
function updateConnectionStatus(isConnected) {
    // Vous pouvez ajouter un indicateur visuel si souhaité
    // Par exemple, un petit point vert/rouge dans le coin
    const indicator = document.getElementById('connection-indicator');
    if (indicator) {
        indicator.style.backgroundColor = isConnected ? '#4CAF50' : '#f44336';
        indicator.title = isConnected ? 'Connecté' : 'Déconnecté';
    }
}

/**
 * Soumet un vote avec gestion d'erreur et reconnexion automatique
 * @param {number} like - Note pour "J'ai aimé le cours" (1-4)
 * @param {number} learn - Note pour "J'ai appris des choses" (1-4)
 * @param {string} csrfToken - Token CSRF
 * @param {Function} onSuccess - Callback en cas de succès
 * @param {Function} onError - Callback en cas d'erreur finale
 */
function submitVoteWithRetry(like, learn, csrfToken, onSuccess, onError) {
    attemptSubmitVote(like, learn, csrfToken, 0, onSuccess, onError);
}

/**
 * Tente de soumettre un vote (avec système de retry)
 * @param {number} like - Note pour "J'ai aimé le cours"
 * @param {number} learn - Note pour "J'ai appris des choses"
 * @param {string} csrfToken - Token CSRF
 * @param {number} attempt - Numéro de la tentative actuelle
 * @param {Function} onSuccess - Callback en cas de succès
 * @param {Function} onError - Callback en cas d'échec final
 */
function attemptSubmitVote(like, learn, csrfToken, attempt, onSuccess, onError) {
    console.log(`Tentative ${attempt + 1} d'envoi du vote`);
    
    fetch('submit_vote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `like=${like}&learn=${learn}&csrf_token=${csrfToken}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log('Vote enregistré avec succès');
            connectionStatus = true;
            updateConnectionStatus(true);
            if (onSuccess) onSuccess(data);
        } else {
            throw new Error(data.message || 'Erreur inconnue');
        }
    })
    .catch(error => {
        console.error(`Erreur lors de la tentative ${attempt + 1}:`, error);
        
        // Si on n'a pas atteint le nombre max de tentatives, réessayer
        if (attempt < MAX_RETRIES) {
            console.log(`Nouvelle tentative dans ${RETRY_DELAY}ms...`);
            setTimeout(() => {
                attemptSubmitVote(like, learn, csrfToken, attempt + 1, onSuccess, onError);
            }, RETRY_DELAY);
        } else {
            // Échec final après toutes les tentatives
            console.error('Échec définitif après', MAX_RETRIES + 1, 'tentatives');
            connectionStatus = false;
            updateConnectionStatus(false);
            if (onError) onError(error);
        }
    });
}

/**
 * Arrête le heartbeat (utile pour le nettoyage)
 */
function stopHeartbeat() {
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        console.log('Heartbeat arrêté');
    }
}

// Démarrage automatique du heartbeat quand la page est chargée
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startHeartbeat);
} else {
    startHeartbeat();
}

// Arrêt du heartbeat quand la page est quittée (nettoyage)
window.addEventListener('beforeunload', stopHeartbeat);

// Export des fonctions pour utilisation dans d'autres scripts
window.evalcoursConnection = {
    submitVote: submitVoteWithRetry,
    startHeartbeat: startHeartbeat,
    stopHeartbeat: stopHeartbeat,
    pingServer: pingServer
};