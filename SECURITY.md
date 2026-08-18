# 🔒 Sécurité de Feedback cours

## Modèle d’utilisation

L’application fonctionne sur un iPad en libre accès dans une salle de classe. Le vote doit rester simple et anonyme. Les fonctions de statistiques, d’export et de gestion des sessions sont réservées à l’enseignant.

## Mesures mises en œuvre

### Administration

- Le code est stocké uniquement sous forme de hash produit par `password_hash()`.
- Aucun code par défaut n’est intégré au dépôt.
- Les nouvelles configurations demandent un code de 6 à 12 chiffres.
- Les anciens codes à 4 chiffres restent acceptés pour permettre leur migration.
- La session administrateur expire après 30 minutes d’inactivité sur toutes les API protégées.
- L’identifiant de session est renouvelé à la connexion et après un changement de code.
- Toutes les modifications utilisent un jeton CSRF.
- Une déconnexion explicite est disponible.

### Votes et données

- Les types de votes, valeurs et identifiants de session sont validés côté serveur.
- Le rate limiting est stocké côté serveur et ne dépend plus d’un cookie supprimable.
- L’identifiant réseau utilisé pour le rate limiting est transformé avec HMAC et conservé quelques minutes seulement.
- Les votes ne contiennent aucune identité d’élève.
- Les accès concurrents sont protégés par des verrous dédiés.
- Les remises à zéro et suppressions remplacent les fichiers de manière atomique.

### Navigateur et serveur

- Cookies `HttpOnly`, `SameSite=Strict` et `Secure` lorsqu’HTTPS est utilisé.
- Content Security Policy limitant scripts, styles, images et connexions.
- Protection contre l’intégration dans une iframe.
- Désactivation de l’indexation des répertoires et du service direct des fichiers de données.
- Chart.js est chargé depuis une version précisément fixée.

## Limites et exploitation

- Le serveur doit impérativement utiliser HTTPS. La redirection doit être activée depuis le panneau de l’hébergeur.
- La protection du dossier `data/` dépend de la prise en charge d’Apache et de `.htaccess`. Un test HTTP direct doit être effectué après chaque migration.
- Un code numérique reste moins robuste qu’une authentification longue. Le rate limiting réduit ce risque sans le supprimer entièrement.
- Le stockage fichier convient à un usage de classe et à un volume modéré. Il ne remplace pas une base transactionnelle pour un déploiement à grande échelle.
- Les sauvegardes et la durée de conservation restent sous la responsabilité de l’administrateur.

## Contrôles après déploiement

1. Vérifier que `https://votre-domaine/data/admin_hash.txt` et `feedback.csv` renvoient 403 ou 404.
2. Vérifier que l’administration refuse l’accès après 30 minutes d’inactivité.
3. Tester le changement de session depuis un autre onglet : l’iPad doit s’actualiser sous une minute.
4. Tester un vote, l’export CSV et une remise à zéro sur une session d’essai.
5. Vérifier les en-têtes HTTP avec les outils de développement du navigateur.

## Signalement

Ne pas publier de code administrateur, de hash ou de fichier du dossier `data/` dans une issue GitHub. Utiliser un canal privé pour tout signalement contenant des informations sensibles.

**Dernière mise à jour : août 2026.**
