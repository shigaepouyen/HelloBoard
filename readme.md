# HelloBoard 2

HelloBoard 2 est une application web PHP dediee au pilotage de campagnes HelloAsso pour les associations.
Elle combine un dashboard live, un module d'emargement, un module de mailing avec suivi de lecture, et un module satisfaction avec reporting.

## Sommaire

- Presentation
- Fonctionnalites
- Architecture
- Fonctionnement detaille
- Installation
- Configuration
- Exploitation quotidienne
- Securite et bonnes pratiques
- Depannage
- Licence

## Presentation

L'objectif de HelloBoard 2 est de transformer les donnees HelloAsso (inscriptions, commandes, dons, adhesions) en outils actionnables pour les equipes terrain:

- Suivi en temps reel des performances.
- Gestion de la presence (check-in / guestlist).
- Relances email personnalisees.
- Collecte et analyse de la satisfaction post-evenement.

Le projet privilegie la simplicite:

- PHP natif (sans framework).
- Stockage local JSON + SQLite.
- Interface admin centralisee.

## Fonctionnalites

### 1) Dashboard live

- KPIs: recettes, volume, dons, progression vs objectifs.
- Visualisations: timeline, heatmap, repartitions, vues adaptees au type de formulaire.
- Support de plusieurs types HelloAsso: `Event`, `Shop`, `Membership`, `Donation`, `Crowdfunding`, etc.
- Partage securise d'un board via `shareToken`.

### 2) Guestlist / emargement

- Pointage en temps reel.
- Synchronisation entre plusieurs postes.
- Export CSV.
- Impression optimisee.

### 3) Mailing

- Brouillons par campagne.
- Envoi test, envoi unitaire, envoi de masse.
- Pieces jointes par campagne.
- Historique d'envoi et de lecture.
- Pixel de tracking via `public/track.php`.

### 4) Satisfaction

- Envoi de questionnaires via token unique.
- Formulaire public dedie (`public/satisfaction.php`).
- Tableau de bord satisfaction global.
- Export CSV des retours.
- Analyse IA optionnelle des verbatims.

### 5) IA (optionnel)

- Generation de contenu email.
- Analyse de campagnes satisfaction.
- Integration Mistral via `mistralApiKey`.

## Architecture

```text
HelloBoard/
|-- config/
|   |-- campaigns/                  # Config boards (JSON)
|   |-- checkins/                   # Etats d'emargement
|   |-- mailing/                    # Historiques mailing + pieces jointes
|   |-- settings.json               # Configuration globale sensible
|   |-- satisfaction.db             # Base SQLite satisfaction
|   `-- .htaccess                   # Protection du dossier
|-- public/
|   |-- index.php                   # Supervision et affichage dashboard
|   |-- admin.php                   # Console d'administration
|   |-- api.php                     # Endpoint stats JSON
|   |-- satisfaction.php            # Formulaire satisfaction public
|   `-- track.php                   # Pixel tracking lecture
|-- src/Services/
|   |-- HelloAssoClient.php         # Appels API HelloAsso
|   |-- StatsEngine.php             # Calculs statistiques
|   |-- Storage.php                 # Persistance locale
|   |-- MailService.php             # SMTP + templates dynamiques
|   |-- SatisfactionService.php     # Gestion questionnaires/stats
|   `-- AiService.php               # Integration Mistral
|-- templates/
|   |-- dashboard.php
|   |-- guestlist.php
|   |-- mailing.php
|   |-- satisfaction.php
|   `-- satisfaction_global.php
`-- readme.md
```

## Fonctionnement detaille

### Cycle standard d'utilisation

1. Configurer les identifiants globaux dans l'admin (`action=settings`).
2. Scanner les formulaires HelloAsso.
3. Creer/editer un board (slug, regles, mappings, objectifs).
4. Ouvrir le dashboard et suivre la campagne.
5. Utiliser les modules selon le besoin:
- guestlist pour l'accueil / emargement
- mailing pour rappels et communication
- satisfaction pour feedback post-action

### Stockage des donnees

- `config/settings.json`: credentials et reglages globaux.
- `config/campaigns/*.json`: configuration metier par board.
- `config/checkins/*.json`: etat des check-ins.
- `config/mailing/*.json`: historique d'envois/lectures.
- `config/satisfaction.db`: questionnaires/reponses/statistiques.

## Installation

### Prerequis

- PHP 8.0 ou superieur.
- Extension PHP `curl`.
- Droits en ecriture pour le processus PHP sur:
- `config/`
- `logs/` (si debug active)

### Installation locale (rapide)

Depuis la racine du projet:

```bash
php -S localhost:8080 -t public
```

Puis ouvrir:

- `http://localhost:8080/admin.php` (configuration et pilotage)
- `http://localhost:8080/index.php` (supervision / dashboards)

### Installation serveur (Apache/Nginx)

- Pointer le `DocumentRoot` sur `public/`.
- Interdire l'acces HTTP direct au dossier `config/`.
- Verifier les droits d'ecriture sur `config/`.
- Utiliser HTTPS en production.

## Configuration

La configuration centrale est `config/settings.json`.

Champs principaux:

- HelloAsso: `clientId`, `clientSecret`, `orgSlug`
- SMTP: `smtpHost`, `smtpPort`, `smtpUser`, `smtpPass`, `smtpFromName`
- Admin: `adminPassword`
- IA: `mistralApiKey` (optionnel)
- Interface: `customLogo`, `debugMode`

Configuration par campagne:

- Creee depuis `admin.php` puis stockee dans `config/campaigns/<slug>.json`.
- Inclut slug, type de formulaire, objectifs, regles de mapping, token de partage.

## Exploitation quotidienne

- Ouvrir la supervision pour consulter l'etat de toutes les campagnes.
- Entrer dans une campagne pour visualiser les KPIs live.
- Lancer un rappel mailing avant l'evenement.
- Utiliser la guestlist le jour J.
- Envoyer un questionnaire satisfaction apres la campagne.
- Exporter les CSV pour archivage ou reporting.

## Securite et bonnes pratiques

- Ne jamais versionner `config/settings.json`.
- Conserver la protection du dossier `config/`.
- Definir un mot de passe admin robuste.
- Regenerer les `shareToken` si un lien public fuite.
- Desactiver le debug en production.
- Sauvegarder regulierement `config/` (incluant `satisfaction.db`).

## Depannage

- Si le dashboard ne charge pas: verifier `clientId/clientSecret/orgSlug`.
- Si le mailing echoue: verifier SMTP (hote, port, auth, TLS).
- Si le tracking n'apparait pas: verifier l'accessibilite de `track.php`.
- Si les check-ins ne se sauvegardent pas: verifier les droits sur `config/checkins/`.

## Licence

Copyright (c) 2025 Shigaepouyen - Jean-Christophe CAMuS - Tous droits reserves.

La redistribution ou l'utilisation commerciale de ce projet est interdite sans autorisation.
Voir le fichier `LICENSE.md` pour plus de details.
