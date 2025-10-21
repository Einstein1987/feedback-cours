# 🔒 Sécurité de l'Application Feedback Cours

## 📋 Vue d'ensemble

Cette application a été conçue avec la sécurité comme priorité, en respectant les bonnes pratiques de développement web et les recommandations de l'OWASP.

## ✅ Mesures de Sécurité Implémentées

### 🛡️ **1. Protection Backend (PHP)**

#### Rate Limiting
```php
✅ Limitation à 10 requêtes par minute par IP
✅ Fenêtre glissante de 5 minutes pour connexion admin
✅ Protection contre brute force
```

**Fichiers concernés :**
- `config.php` : Fonction `checkRateLimit()`
- `save.php` : 10 votes max/minute
- `check_admin.php` : 5 tentatives max/5min

#### Validation des Entrées
```php
✅ Sanitization de toutes les entrées utilisateur
✅ Fonction sanitizeInput() avec htmlspecialchars()
✅ Validation stricte des types (liked/learned)
✅ Validation des valeurs (0-3 uniquement)
✅ Protection contre injection SQL (aucune base SQL)
```

**Fichiers concernés :**
- `config.php` : Fonction `sanitizeInput()`
- `save.php` : Validation type et valeur
- Tous les fichiers PHP : Utilisation systématique

#### Code Admin Sécurisé
```php
✅ Hachage bcrypt (PASSWORD_BCRYPT)
✅ Jamais stocké en clair
✅ Vérification avec password_verify()
✅ Délai 0.5s en cas d'échec (anti brute force)
```

**Fichiers concernés :**
- `config.php` : Fonctions `hashAdminCode()` et `verifyAdminCode()`
- `check_admin.php` : Vérification sécurisée
- `change_code.php` : Changement sécurisé

#### Protection CSRF
```php
✅ Token CSRF pour actions sensibles
✅ Génération : generateCSRFToken()
✅ Vérification : verifyCSRFToken()
✅ Validation hash_equals() (timing-safe)
```

**Fichiers concernés :**
- `config.php` : Fonctions CSRF

#### Headers de Sécurité
```php
✅ X-Content-Type-Options: nosniff
✅ X-Frame-Options: DENY
✅ X-XSS-Protection: 1; mode=block
✅ Referrer-Policy: strict-origin-when-cross-origin
```

**Fichiers concernés :**
- `config.php` : Headers envoyés automatiquement

#### Sessions Sécurisées
```php
✅ session_start() avec configuration sécurisée
✅ Expiration 30 minutes pour admin
✅ Vérification temporelle
✅ Nettoyage automatique sessions expirées
```

**Fichiers concernés :**
- `config.php` : Gestion sessions
- Tous fichiers admin : Vérification expiration

---

### 🌐 **2. Protection Frontend (JavaScript)**

#### Validation Côté Client
```javascript
✅ Vérification format code admin (4 chiffres)
✅ Rate limiting cookie (20 secondes entre votes)
✅ Validation session active avant vote
✅ Échappement des données affichées
```

**Fichiers concernés :**
- `scripts.js` : Toutes les fonctions de validation

#### Cookies Sécurisés
```javascript
✅ SameSite=Strict (protection CSRF)
✅ Expiration automatique
✅ Path=/ (portée limitée)
```

**Fichiers concernés :**
- `scripts.js` : Fonction `setCookie()`

---

### 📊 **3. Protection des Données**

#### Anonymat Total
```
✅ Aucune donnée personnelle collectée
✅ Pas de nom, prénom, email
✅ Pas d'adresse IP stockée
✅ Seulement : type, valeur, timestamp, session
```

#### Isolation des Sessions
```
✅ Chaque session isolée
✅ Statistiques séparées
✅ Reset par session (pas global par défaut)
✅ Export filtré par session
```

#### Sécurité des Fichiers
```
✅ Dossier data/ protégé
✅ Permissions 750 (rwxr-x---)
✅ Fichiers 640 (rw-r-----)
✅ Pas d'accès direct via URL
```

**Configuration recommandée :**
```
chmod 750 data/
chmod 640 data/*.csv
chmod 640 data/*.txt
chmod 640 data/*.json
```

---

### 💻 **4. Qualité du Code**

#### Architecture
```
✅ Séparation HTML/CSS/JS
✅ Pas de code inline
✅ Principe de responsabilité unique
✅ Réutilisabilité maximale
```

#### Standards
```
✅ HTML5 valide
✅ CSS3 moderne
✅ JavaScript ES6+
✅ PHP 7.4+ compatible
```

#### Documentation
```
✅ Commentaires clairs
✅ Noms de fonctions explicites
✅ README.md complet
✅ SECURITY.md (ce fichier)
```

---

## 🔍 Tests de Sécurité Recommandés

### ✅ Tests Effectués Manuellement

- [x] Test injection XSS (échappement OK)
- [x] Test rate limiting (fonctionne)
- [x] Test validation entrées (rejette invalides)
- [x] Test code admin (bcrypt OK)
- [x] Test sessions (isolation OK)

### 🔎 Tests Recommandés par la DSI

#### Test 1 : Injection
```bash
# Tenter injection dans feedback
curl -X POST https://votre-site.com/save.php \
  -d "type=<script>alert('xss')</script>&value=3&session=test"

Résultat attendu : Données échappées, pas d'exécution
```

#### Test 2 : Rate Limiting
```bash
# Envoyer 15 requêtes rapidement
for i in {1..15}; do
  curl -X POST https://votre-site.com/save.php \
    -d "type=liked&value=3&session=test"
done

Résultat attendu : 10 OK, 5 erreurs 429 (Too Many Requests)
```

#### Test 3 : Code Admin Brute Force
```bash
# Tenter 10 codes différents
for i in {0000..0009}; do
  curl -X POST https://votre-site.com/check_admin.php \
    -d "code=$i"
done

Résultat attendu : Blocage après 5 tentatives
```

#### Test 4 : Permissions Fichiers
```bash
# Tenter accès direct aux données
curl https://votre-site.com/data/feedback.csv

Résultat attendu : 403 Forbidden ou 404 Not Found
```

#### Test 5 : Headers Sécurité
```bash
# Vérifier headers HTTP
curl -I https://votre-site.com/stats.php

Résultat attendu :
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
```

---

## 🚨 Vulnérabilités Connues et Acceptées

### 1. Pas de HTTPS Natif
**Risque :** Données en clair sur le réseau  
**Mitigation :** InfinityFree limite, recommandation upgrade  
**Impact :** Faible (pas de données sensibles)

### 2. Stockage Fichier CSV
**Risque :** Pas de base de données structurée  
**Mitigation :** Verrouillage fichier (flock), permissions strictes  
**Impact :** Faible (volume de données réduit)

### 3. Code Admin 4 Chiffres
**Risque :** 10 000 combinaisons possibles  
**Mitigation :** Rate limiting strict (5 tentatives/5min)  
**Impact :** Très faible (brute force = 33h minimum)

---

## 📋 Checklist Audit DSI

### Configuration Serveur
```
□ PHP 7.4+ installé
□ allow_url_fopen = Off
□ display_errors = Off (production)
□ expose_php = Off
□ Permissions fichiers correctes (750/640)
```

### Fichiers Sensibles
```
□ data/admin_hash.txt non accessible via URL
□ data/feedback.csv non accessible via URL
□ data/sessions.json non accessible via URL
□ config.php contient date_default_timezone_set()
```

### Tests Fonctionnels
```
□ Vote élève fonctionne
□ Rate limiting bloque après 10 votes/min
□ Admin peut se connecter
□ Code admin incorrect bloqué après 5 tentatives
□ Export CSV fonctionne
□ Graphiques s'affichent
□ Reset par session fonctionne
```

## ✅ Conformité

### RGPD
```
✅ Pas de données personnelles collectées
✅ Anonymat total des réponses
✅ Pas de cookies de tracking
✅ Export de données possible
✅ Droit à l'oubli (reset par session)
```

### Éducation Nationale
```
✅ Pas de données élèves nominatives
✅ Usage pédagogique uniquement
✅ Hébergement externe sécurisé
✅ Accès restreint (code admin)
```

---

## 🎯 Conclusion

Cette application a été développée avec un souci constant de la sécurité, en suivant les meilleures pratiques de l'industrie. Toutes les vulnérabilités connues sont documentées et mitigées. L'application est prête pour un usage en production dans un contexte éducatif.

**Dernière mise à jour :** Octobre 2025
