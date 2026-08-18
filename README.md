# 📊 Feedback cours

Application web de feedback anonyme conçue pour être affichée sur un iPad dans la salle de classe. Les élèves peuvent indiquer librement s’ils ont aimé le cours et s’ils pensent avoir appris quelque chose.

## Fonctionnalités

- vote anonyme et tactile avec quatre smileys ;
- gestion de plusieurs sessions de cours ;
- statistiques et graphiques réservés à l’administrateur ;
- export CSV ;
- interface adaptée à l’iPad, aux mobiles et au clavier ;
- stockage local dans des fichiers protégés, sans base de données.

## Prérequis

- serveur Apache avec PHP 7.4 ou plus récent ;
- HTTPS actif ;
- extensions PHP standard : JSON, session et password hashing ;
- droits d’écriture PHP sur le dossier `data/`.

## Installation

1. Copier le dépôt sur le serveur.
2. Créer le dossier `data/` s’il n’existe pas et lui attribuer des droits `750`.
3. Choisir un code administrateur de 6 à 12 chiffres.
4. Générer son hash sur une machine disposant de PHP :

   ```bash
   php -r "echo password_hash('VOTRE_CODE', PASSWORD_DEFAULT), PHP_EOL;"
   ```

5. Copier uniquement le résultat dans `data/admin_hash.txt`, puis attribuer au fichier des droits `640`.
6. Vérifier que l’URL utilise HTTPS et ouvrir `index.html`.

Une installation déjà configurée conserve son code existant, y compris s’il comporte quatre chiffres. Lors du prochain changement de code, six chiffres minimum seront demandés.

## Données et confidentialité

Les votes contiennent uniquement :

- la date et l’heure ;
- le type de question ;
- une valeur de 0 à 3 ;
- l’identifiant technique de la session de cours.

Aucun nom, prénom, adresse électronique ou adresse IP n’est enregistré avec les votes. Le rate limiting utilise temporairement un identifiant HMAC non réversible, automatiquement nettoyé.

## Sécurité

- code administrateur haché avec `password_hash()` ;
- expiration après 30 minutes d’inactivité et bouton de déconnexion ;
- cookies de session `HttpOnly`, `SameSite=Strict` et `Secure` sous HTTPS ;
- jetons CSRF pour toutes les modifications administratives ;
- limitation des votes et des tentatives de connexion côté serveur ;
- verrouillage des écritures pour éviter les pertes de données ;
- en-têtes CSP, anti-framing et politique de permissions ;
- fichiers `data/` bloqués par Apache.

Les hypothèses et limites restantes sont détaillées dans [SECURITY.md](SECURITY.md).

## Vérifications

Le workflow GitHub Actions contrôle :

- la syntaxe de tous les fichiers PHP ;
- la syntaxe JavaScript ;
- la structure HTML essentielle ;
- les principales fonctions de validation et de stockage.

## Licence

Ce projet est distribué sous [licence MIT](LICENSE).

## Auteur

Développé par **Jérémy VIOLETTE**, professeur de physique-chimie.
