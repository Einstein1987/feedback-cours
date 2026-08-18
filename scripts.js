'use strict';

const appState = {
    activeSession: null,
    csrfToken: null,
    statsChart: null,
    timeChart: null,
    votePending: false,
    lastFocusedElement: null,
};

function encodeForm(data) {
    return new URLSearchParams(data).toString();
}

async function apiRequest(url, options = {}, adminRequest = false) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options,
    });

    let data;
    try {
        data = await response.json();
    } catch (error) {
        throw new Error('Réponse du serveur illisible.');
    }

    if (response.status === 401 && adminRequest) {
        alert('Ta session administrateur a expiré. Reconnecte-toi.');
        window.location.href = 'index.html';
        throw new Error('Session administrateur expirée.');
    }

    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Une erreur est survenue.');
    }

    return data;
}

function showMessage(message, isError = false) {
    const popup = document.getElementById('popup');
    if (!popup) {
        if (isError) {
            alert(message);
        }
        return;
    }

    popup.textContent = message;
    popup.classList.toggle('error', isError);
    popup.classList.add('show');
    window.setTimeout(() => popup.classList.remove('show'), 2500);
}

function setVotingDisabled(disabled) {
    document.querySelectorAll('[data-feedback-type]').forEach((button) => {
        button.disabled = disabled;
    });
}

async function loadActiveSession() {
    const sessionName = document.getElementById('sessionName');
    try {
        const data = await apiRequest('get_sessions.php');
        appState.activeSession = data.active_session || null;
        sessionName.textContent = appState.activeSession ? appState.activeSession.name : 'Aucune session active';
        setVotingDisabled(!appState.activeSession);
    } catch (error) {
        sessionName.textContent = 'Connexion impossible';
        setVotingDisabled(true);
        console.error(error);
    }
}

async function sendFeedback(button) {
    if (appState.votePending || !appState.activeSession) {
        return;
    }

    appState.votePending = true;
    setVotingDisabled(true);
    button.classList.add('selected');

    try {
        await apiRequest('save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: encodeForm({
                type: button.dataset.feedbackType,
                value: button.dataset.value,
                session: appState.activeSession.id,
            }),
        });
        showMessage('✓ Réponse enregistrée !');
    } catch (error) {
        showMessage('❌ ' + error.message, true);
    } finally {
        window.setTimeout(() => {
            button.classList.remove('selected');
            appState.votePending = false;
            setVotingDisabled(!appState.activeSession);
        }, 700);
    }
}

function openModal(modalId, trigger) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    appState.lastFocusedElement = trigger || document.activeElement;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const firstInput = modal.querySelector('input, button, select');
    if (firstInput) {
        window.setTimeout(() => firstInput.focus(), 0);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    modal.querySelectorAll('input').forEach((input) => {
        input.value = '';
    });

    if (appState.lastFocusedElement) {
        appState.lastFocusedElement.focus();
        appState.lastFocusedElement = null;
    }
}

async function checkAdminCode(event) {
    event.preventDefault();
    const codeInput = document.getElementById('adminCode');

    try {
        await apiRequest('check_admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: encodeForm({code: codeInput.value}),
        });
        window.location.href = 'stats.html';
    } catch (error) {
        showMessage('❌ ' + error.message, true);
        codeInput.value = '';
        codeInput.focus();
    }
}

function initializeVotePage() {
    document.querySelectorAll('[data-feedback-type]').forEach((button) => {
        button.addEventListener('click', () => sendFeedback(button));
    });

    document.getElementById('openAdminButton').addEventListener('click', (event) => {
        openModal('adminModal', event.currentTarget);
    });
    document.getElementById('cancelAdminButton').addEventListener('click', () => closeModal('adminModal'));
    document.getElementById('adminForm').addEventListener('submit', checkAdminCode);

    loadActiveSession();
    window.setInterval(loadActiveSession, 60000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadActiveSession();
    });
}

function createButton(label, className, handler) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    button.addEventListener('click', handler);
    return button;
}

async function loadSessions(preferredSession = null) {
    const data = await apiRequest('get_sessions.php', {}, true);
    if (!data.is_admin) {
        window.location.href = 'index.html';
        return;
    }

    appState.csrfToken = data.csrf_token;
    const select = document.getElementById('sessionSelect');
    const sessionList = document.getElementById('sessionList');
    const previousValue = preferredSession || select.value || 'all';

    select.replaceChildren(new Option('Toutes les sessions', 'all'));
    sessionList.replaceChildren();

    data.sessions.forEach((session) => {
        select.add(new Option(session.name, session.id));
        sessionList.appendChild(buildSessionItem(session));
    });

    if ([...select.options].some((option) => option.value === previousValue)) {
        select.value = previousValue;
    }

    await loadStats();
}

function buildSessionItem(session) {
    const item = document.createElement('article');
    item.className = 'session-item' + (session.is_active ? ' active' : '');

    const details = document.createElement('div');
    const name = document.createElement('strong');
    name.textContent = session.name;
    details.appendChild(name);

    if (session.is_active) {
        const badge = document.createElement('span');
        badge.className = 'session-badge';
        badge.textContent = 'ACTIF';
        details.appendChild(badge);
    }

    const date = document.createElement('small');
    date.textContent = 'Créée le ' + session.created_at;
    details.appendChild(document.createElement('br'));
    details.appendChild(date);

    const actions = document.createElement('div');
    actions.className = 'session-actions';
    if (!session.is_active) {
        actions.appendChild(createButton('⭐ Activer', 'btn btn-primary btn-compact', () => setActiveSession(session.id)));
    }
    actions.appendChild(createButton('Supprimer', 'btn btn-danger btn-compact', () => deleteSession(session.id, session.name)));

    item.append(details, actions);
    return item;
}

async function loadStats() {
    const session = document.getElementById('sessionSelect').value;
    updateControlButtons(session);

    try {
        const data = await apiRequest('stats.php?session=' + encodeURIComponent(session), {}, true);
        updateStatsUI(data);
        updateCharts(data);
    } catch (error) {
        console.error(error);
    }
}

function updateControlButtons(session) {
    const controls = document.getElementById('controlButtons');
    controls.replaceChildren();

    controls.appendChild(createButton('📥 Exporter CSV', 'btn btn-success', exportCSV));
    controls.appendChild(createButton('➕ Nouvelle session', 'btn btn-primary', (event) => {
        openModal('sessionModal', event.currentTarget);
    }));

    if (session !== 'all') {
        controls.appendChild(createButton('🗑️ Réinitialiser cette session', 'btn btn-danger', openResetModal));
    }
}

function calculateAverage(counts) {
    const total = counts.reduce((sum, count, index) => sum + count * index, 0);
    const responses = counts.reduce((sum, count) => sum + count, 0);
    return responses ? total / responses : 0;
}

function updateStatsUI(data) {
    const avgLiked = calculateAverage(data.counts.liked);
    const avgLearned = calculateAverage(data.counts.learned);
    const satisfaction = Math.round(((avgLiked + avgLearned) / 6) * 100);

    document.getElementById('resetDate').textContent = 'Dernière remise à zéro : ' + data.lastResetDate;
    document.getElementById('totalResponses').textContent = 'Total : ' + data.totalResponses + ' réponses enregistrées';
    document.getElementById('totalCount').textContent = data.totalResponses;
    document.getElementById('avgLiked').textContent = avgLiked.toFixed(1);
    document.getElementById('avgLearned').textContent = avgLearned.toFixed(1);
    document.getElementById('satisfaction').textContent = satisfaction + ' %';
    document.getElementById('accessibleStatsSummary').textContent =
        'Moyenne appréciation : ' + avgLiked.toFixed(1) + ' sur 3. ' +
        'Moyenne apprentissage : ' + avgLearned.toFixed(1) + ' sur 3. ' +
        'Satisfaction : ' + satisfaction + ' %. ' +
        data.totalResponses + ' réponses au total.';
}

function updateCharts(data) {
    if (typeof Chart === 'undefined') {
        document.getElementById('accessibleStatsSummary').textContent += ' Les graphiques n’ont pas pu être chargés.';
        return;
    }

    if (appState.statsChart) appState.statsChart.destroy();
    appState.statsChart = new Chart(document.getElementById('statsChart'), {
        type: 'bar',
        data: {
            labels: ['😟 Pas satisfait', '😐 Moyen', '🙂 Bien', '😃 Excellent'],
            datasets: [
                {
                    label: 'J’ai aimé le cours',
                    data: data.counts.liked,
                    backgroundColor: 'rgba(74, 144, 226, 0.8)',
                },
                {
                    label: 'J’ai appris quelque chose',
                    data: data.counts.learned,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                },
            ],
        },
        options: {
            responsive: true,
            scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}},
        },
    });

    if (appState.timeChart) appState.timeChart.destroy();
    appState.timeChart = new Chart(document.getElementById('timeChart'), {
        type: 'line',
        data: {
            labels: data.timeline.labels,
            datasets: [
                {
                    label: 'Moyenne « J’ai aimé »',
                    data: data.timeline.liked,
                    borderColor: 'rgba(74, 144, 226, 1)',
                    backgroundColor: 'rgba(74, 144, 226, 0.1)',
                    tension: 0.3,
                    spanGaps: true,
                },
                {
                    label: 'Moyenne « J’ai appris »',
                    data: data.timeline.learned,
                    borderColor: 'rgba(76, 175, 80, 1)',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.3,
                    spanGaps: true,
                },
            ],
        },
        options: {
            responsive: true,
            scales: {y: {beginAtZero: true, max: 3}},
        },
    });
}

function exportCSV() {
    const session = document.getElementById('sessionSelect').value;
    window.location.href = 'export.php?session=' + encodeURIComponent(session);
}

function openResetModal(event) {
    const select = document.getElementById('sessionSelect');
    const name = select.options[select.selectedIndex].text;
    document.getElementById('resetConfirmText').textContent =
        'Réinitialiser définitivement les statistiques de « ' + name + ' » ?';
    openModal('resetModal', event.currentTarget);
}

async function confirmResetStats() {
    const session = document.getElementById('sessionSelect').value;
    try {
        await adminPost('reset.php', {session});
        closeModal('resetModal');
        await loadStats();
    } catch (error) {
        alert('❌ ' + error.message);
    }
}

async function adminPost(url, data = {}) {
    return apiRequest(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body: encodeForm({...data, csrf_token: appState.csrfToken}),
    }, true);
}

async function setActiveSession(sessionId) {
    try {
        await adminPost('set_active_session.php', {session_id: sessionId});
        await loadSessions(sessionId);
    } catch (error) {
        alert('❌ ' + error.message);
    }
}

async function createSession(event) {
    event.preventDefault();
    const input = document.getElementById('newSessionName');
    try {
        const data = await adminPost('create_session.php', {name: input.value.trim()});
        closeModal('sessionModal');
        await loadSessions(data.session.id);
    } catch (error) {
        alert('❌ ' + error.message);
    }
}

async function deleteSession(sessionId, sessionName) {
    if (!confirm('Supprimer « ' + sessionName + ' » et toutes ses données ?')) {
        return;
    }

    try {
        await adminPost('delete_session.php', {session: sessionId});
        await loadSessions();
    } catch (error) {
        alert('❌ ' + error.message);
    }
}

async function changeAdminCode(event) {
    event.preventDefault();
    const code = document.getElementById('newAdminCode').value;
    const confirmation = document.getElementById('confirmAdminCode').value;

    if (!/^\d{6,12}$/.test(code)) {
        alert('❌ Le nouveau code doit contenir entre 6 et 12 chiffres.');
        return;
    }
    if (code !== confirmation) {
        alert('❌ Les deux codes ne correspondent pas.');
        return;
    }

    try {
        const data = await adminPost('change_code.php', {code});
        appState.csrfToken = data.csrf_token;
        closeModal('settingsModal');
        alert('✓ Code administrateur modifié.');
    } catch (error) {
        alert('❌ ' + error.message);
    }
}

async function logout() {
    try {
        await adminPost('logout.php');
    } finally {
        window.location.href = 'index.html';
    }
}

function initializeStatsPage() {
    document.getElementById('backButton').addEventListener('click', () => {
        window.location.href = 'index.html';
    });
    document.getElementById('openSettingsButton').addEventListener('click', (event) => {
        openModal('settingsModal', event.currentTarget);
    });
    document.getElementById('logoutButton').addEventListener('click', logout);
    document.getElementById('sessionSelect').addEventListener('change', loadStats);
    document.getElementById('settingsForm').addEventListener('submit', changeAdminCode);
    document.getElementById('sessionForm').addEventListener('submit', createSession);
    document.getElementById('confirmResetButton').addEventListener('click', confirmResetStats);
    document.querySelectorAll('.modal-cancel').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.modal-overlay').id));
    });

    loadSessions().catch((error) => {
        console.error(error);
        window.location.href = 'index.html';
    });
}

document.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal(event.target.id);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const open = document.querySelector('.modal-overlay.show');
    if (open) closeModal(open.id);
});

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('home-page')) {
        initializeVotePage();
    }
    if (document.body.classList.contains('stats-page')) {
        initializeStatsPage();
    }
});
