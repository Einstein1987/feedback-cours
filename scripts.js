/* ===================================
   SCRIPTS COMMUNS - Application Feedback
   ================================== */

/* ===================================
   SECTION 1 : FONCTIONS INDEX.HTML
   ================================== */

let activeSession = null;

// Charger la session active
function loadActiveSession() {
    fetch('get_sessions.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.active_session) {
                activeSession = data.active_session;
                document.getElementById('sessionName').textContent = activeSession.name;
            } else {
                document.getElementById('sessionName').textContent = 'Aucune session';
                activeSession = null;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('sessionName').textContent = 'Erreur de chargement';
        });
}

// Gestion des cookies pour rate limiting
function setCookie(name, value, seconds) {
    const expires = new Date(Date.now() + seconds * 1000).toUTCString();
    document.cookie = `${name}=${value}; expires=${expires}; path=/; SameSite=Strict`;
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

function canVote() {
    return getCookie('lastVote') === null;
}

// Envoyer un feedback
function sendFeedback(type, value) {
    if (!activeSession) {
        alert('❌ Aucune session active. Veuillez contacter l\'administrateur.');
        return;
    }

    if (!canVote()) {
        alert("⏱️ Merci de patienter quelques secondes avant de voter à nouveau.");
        return;
    }

    // Animation sur le smiley sélectionné
    event.target.classList.add('selected');
    setTimeout(() => event.target.classList.remove('selected'), 500);

    fetch('save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}&session=${encodeURIComponent(activeSession.id)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setCookie('lastVote', '1', 20);
            showPopup();
        } else {
            alert('❌ ' + (data.message || 'Erreur lors de l\'enregistrement'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur de connexion');
    });
}

// Afficher le popup de confirmation
function showPopup() {
    const popup = document.getElementById('popup');
    popup.classList.add('show');
    setTimeout(() => {
        popup.classList.remove('show');
    }, 2500);
}

// Ouvrir le modal admin (index.html)
function openAdminModal() {
    document.getElementById('modalOverlay').classList.add('show');
    const adminCodeInput = document.getElementById('adminCode');
    if (adminCodeInput) {
        adminCodeInput.focus();
    }
}

// Fermer le modal (index.html)
function closeModal(event) {
    const modalOverlay = document.getElementById('modalOverlay');
    if (!event || event.target === modalOverlay) {
        modalOverlay.classList.remove('show');
        const adminCodeInput = document.getElementById('adminCode');
        if (adminCodeInput) {
            adminCodeInput.value = '';
        }
    }
}

// Vérifier le code admin
function checkAdminCode() {
    const code = document.getElementById('adminCode').value;
    
    fetch('check_admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `code=${encodeURIComponent(code)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'stats.html';
        } else {
            alert('❌ Code incorrect');
            document.getElementById('adminCode').value = '';
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur de connexion');
    });
}

// Initialisation des event listeners pour index.html
function initIndexEventListeners() {
    const adminCodeInput = document.getElementById('adminCode');
    
    // Gérer la touche Entrée dans le modal
    if (adminCodeInput) {
        adminCodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                checkAdminCode();
            }
        });
    }

    // Gérer la touche Échap pour fermer le modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modalOverlay = document.getElementById('modalOverlay');
            if (modalOverlay && modalOverlay.classList.contains('show')) {
                closeModal();
            }
        }
    });
}

/* ===================================
   SECTION 2 : FONCTIONS STATS.HTML
   ================================== */

let statsChart = null;
let timeChart = null;
let currentData = null;
let csrfToken = null; // Token CSRF pour les requêtes admin

function loadSessions() {
    fetch('get_sessions.php')
        .then(response => response.json())
        .then(data => {
            // Stocker le token CSRF
            if (data.csrf_token) {
                csrfToken = data.csrf_token;
            }
            
            const select = document.getElementById('sessionSelect');
            const sessionList = document.getElementById('sessionList');
            
            // Remplir le sélecteur
            select.innerHTML = '<option value="all">Toutes les sessions</option>';
            sessionList.innerHTML = '';
            
            data.sessions.forEach(session => {
                const option = document.createElement('option');
                option.value = session.id;
                option.textContent = session.name;
                select.appendChild(option);

                // Déterminer si c'est la session active
                const isActive = session.is_active === true;

                // Ajouter à la liste de gestion
                const item = document.createElement('div');
                item.className = 'session-item' + (isActive ? ' active' : '');
                item.innerHTML = `
                    <span>
                        <strong>${session.name}</strong>
                        ${isActive ? '<span class="session-badge">ACTIF</span>' : ''}
                        <br><small>${session.created_at}</small>
                    </span>
                    <div>
                        ${!isActive ? `<button class="btn btn-primary" style="padding: 8px 15px; font-size: 14px; margin-right: 5px;" onclick="setActiveSession('${session.id}')">⭐ Activer</button>` : ''}
                        <button class="btn btn-danger" style="padding: 8px 15px; font-size: 14px;" onclick="deleteSession('${session.id}')">Supprimer</button>
                    </div>
                `;
                sessionList.appendChild(item);
            });

            loadStats();
        });
}

function loadStats() {
    const session = document.getElementById('sessionSelect').value;
    
    // Mettre à jour les boutons selon la session sélectionnée
    updateControlButtons(session);
    
    fetch(`stats.php?session=${encodeURIComponent(session)}`)
        .then(response => response.json())
        .then(data => {
            currentData = data;
            updateUI(data);
            updateCharts(data);
        });
}

function updateControlButtons(session) {
    const controlButtons = document.getElementById('controlButtons');
    
    if (session === 'all') {
        // Si "Toutes les sessions" : seulement Export et Nouvelle session
        controlButtons.innerHTML = `
            <button class="btn btn-success" onclick="exportCSV()">📥 Exporter CSV</button>
            <button class="btn btn-primary" onclick="openSessionModal()">➕ Nouvelle Session</button>
        `;
    } else {
        // Si session spécifique : Export, Nouvelle session ET Réinitialiser
        controlButtons.innerHTML = `
            <button class="btn btn-success" onclick="exportCSV()">📥 Exporter CSV</button>
            <button class="btn btn-primary" onclick="openSessionModal()">➕ Nouvelle Session</button>
            <button class="btn btn-danger" onclick="openResetModal()">🗑️ Réinitialiser cette session</button>
        `;
    }
}

function updateUI(data) {
    document.getElementById('resetDate').textContent = `Dernière remise à zéro : ${data.lastResetDate}`;
    document.getElementById('totalResponses').textContent = `Total : ${data.totalResponses} réponses enregistrées`;
    document.getElementById('totalCount').textContent = data.totalResponses;

    // Calculer les moyennes
    const avgLiked = calculateAverage(data.counts.liked);
    const avgLearned = calculateAverage(data.counts.learned);
    const satisfaction = Math.round(((avgLiked + avgLearned) / 6) * 100);

    document.getElementById('avgLiked').textContent = avgLiked.toFixed(1);
    document.getElementById('avgLearned').textContent = avgLearned.toFixed(1);
    document.getElementById('satisfaction').textContent = satisfaction + '%';
}

function calculateAverage(counts) {
    const total = counts.reduce((sum, count, index) => sum + (count * index), 0);
    const totalCount = counts.reduce((sum, count) => sum + count, 0);
    return totalCount > 0 ? total / totalCount : 0;
}

function updateCharts(data) {
    // Graphique en barres
    const ctx1 = document.getElementById('statsChart').getContext('2d');
    if (statsChart) statsChart.destroy();
    
    statsChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: ['😟 Pas satisfait', '😐 Moyen', '🙂 Bien', '😃 Excellent'],
            datasets: [
                {
                    label: "J'ai aimé le cours",
                    data: data.counts.liked,
                    backgroundColor: 'rgba(74, 144, 226, 0.8)',
                    borderColor: 'rgba(74, 144, 226, 1)',
                    borderWidth: 2
                },
                {
                    label: "J'ai appris quelque chose",
                    data: data.counts.learned,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // Graphique temporel
    if (data.timeline) {
        const ctx2 = document.getElementById('timeChart').getContext('2d');
        if (timeChart) timeChart.destroy();

        timeChart = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: data.timeline.labels,
                datasets: [
                    {
                        label: "Moyenne 'J'ai aimé'",
                        data: data.timeline.liked,
                        borderColor: 'rgba(74, 144, 226, 1)',
                        backgroundColor: 'rgba(74, 144, 226, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: "Moyenne 'J'ai appris'",
                        data: data.timeline.learned,
                        borderColor: 'rgba(76, 175, 80, 1)',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 3
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }
}

function exportCSV() {
    const session = document.getElementById('sessionSelect').value;
    window.location.href = `export.php?session=${encodeURIComponent(session)}`;
}

function openResetModal() {
    const session = document.getElementById('sessionSelect').value;
    const sessionName = document.getElementById('sessionSelect').options[document.getElementById('sessionSelect').selectedIndex].text;
    
    document.getElementById('resetConfirmText').textContent = 
        `Êtes-vous sûr de vouloir réinitialiser les statistiques de "${sessionName}" ? Cette action est irréversible.`;
    
    document.getElementById('resetModal').classList.add('show');
}

function confirmResetStats() {
    const session = document.getElementById('sessionSelect').value;
    
    fetch('reset.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `session=${encodeURIComponent(session)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Statistiques réinitialisées');
            closeModalById('resetModal');
            loadStats();
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function setActiveSession(sessionId) {
    fetch('set_active_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `session_id=${encodeURIComponent(sessionId)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Session active mise à jour');
            loadSessions(); // Recharger pour afficher le nouveau badge
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function openSettingsModal() {
    document.getElementById('settingsModal').classList.add('show');
}

function openSessionModal() {
    document.getElementById('sessionModal').classList.add('show');
}

function closeModalById(modalId, event) {
    if (!event || event.target.id === modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
}

function changeAdminCode() {
    const newCode = document.getElementById('newAdminCode').value;
    const confirmCode = document.getElementById('confirmAdminCode').value;

    if (newCode !== confirmCode) {
        alert('❌ Les codes ne correspondent pas');
        return;
    }

    if (!/^\d{4}$/.test(newCode)) {
        alert('❌ Le code doit contenir exactement 4 chiffres');
        return;
    }

    fetch('change_code.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `code=${encodeURIComponent(newCode)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Code administrateur modifié avec succès');
            closeModalById('settingsModal');
            document.getElementById('newAdminCode').value = '';
            document.getElementById('confirmAdminCode').value = '';
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function createSession() {
    const name = document.getElementById('newSessionName').value.trim();

    if (!name) {
        alert('❌ Veuillez entrer un nom pour la session');
        return;
    }

    fetch('create_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `name=${encodeURIComponent(name)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Session créée avec succès');
            closeModalById('sessionModal');
            document.getElementById('newSessionName').value = '';
            loadSessions();
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function deleteSession(sessionId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette session et toutes ses données ?')) {
        return;
    }

    fetch('delete_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `session=${encodeURIComponent(sessionId)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Session supprimée');
            loadSessions();
        } else {
            alert('❌ ' + data.message);
        }
    });
}

/* ===================================
   SECTION 3 : INITIALISATION
   ================================== */

// Détecter la page et initialiser les fonctions appropriées
window.addEventListener('load', function() {
    // Vérifier si on est sur la page index.html
    if (document.getElementById('sessionName')) {
        // Page index.html
        initIndexEventListeners();
        loadActiveSession();
    }
    
    // Vérifier si on est sur la page stats.html
    if (document.getElementById('sessionList')) {
        // Page stats.html
        loadSessions();
    }
});
