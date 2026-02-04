# 🎭 HelloBoard - Dashboard Live HelloAsso

HelloBoard est une application PHP légère permettant de suivre en temps réel les inscriptions et les recettes de vos campagnes HelloAsso.  
Conçue spécifiquement pour les associations (APEL, clubs sportifs, etc.), elle transforme des données brutes en indicateurs visuels clairs, sans usine à gaz.

---

## 🚀 Fonctionnalités

- **Suivi en temps réel**
  - KPIs des recettes totales
  - Dons isolés
  - Nombre de participant·es

- **Gestion des objectifs**
  - Définition d’un objectif financier
  - Comparaison avec l’année N-1 par campagne

- **Moteur de règles dynamique**
  - Classement des items HelloAsso en **Billets** ou **Options**
  - 🚫 *Ignorer* : masquage des champs techniques ou inutiles
  - 🔄 *Transformations* : nettoyage et normalisation des données

- **Interface intuitive**
  - Drag & Drop pour l’ordre d’affichage des blocs
  - Interface d’administration simple et rapide
  - Design sombre premium (Glassmorphism)
  - Optimisé grand écran et mobile

---

## 📁 Structure du projet

```
/
├── config/             # Stockage des paramètres et boards (JSON)
│   ├── campaigns/      # Un fichier .json par board configuré
│   └── settings.json   # Identifiants API HelloAsso (ignoré par Git)
├── public/             # Point d'entrée web
│   ├── admin.php       # Interface d'administration
│   ├── api.php         # Endpoint de données pour le dashboard
│   ├── index.php       # Routeur / afficheur des boards
│   └── assets/         # Images et styles (logo, etc.)
├── src/
│   └── Services/       # Logique métier
│       ├── HelloAssoClient.php # Communication API
│       ├── StatsEngine.php     # Calculs et transformations
│       └── Storage.php         # Gestion des fichiers JSON
├── templates/          # Vues HTML / PHP
│   └── dashboard.php   # Rendu visuel du tableau de bord
└── .gitignore          # Protection des données sensibles
```

---

## 🛠️ Installation

### Prérequis
- Serveur Web (Apache, Nginx…)
- PHP **7.4+**
- Extension **cURL** activée

### Déploiement
1. Copier l’ensemble des fichiers sur votre serveur
2. Vérifier les permissions :
   - `config/`
   - `config/campaigns/`  
   (CHMOD 755 ou 777 selon l’hébergeur)

### Configuration initiale
1. Accéder à `https://votre-site.com/admin.php`
2. Renseigner :
   - Client ID HelloAsso
   - Secret
   - Slug de l’association
3. Cliquer sur **Scanner** pour détecter les formulaires

---

## ⚙️ Configuration avancée

### Transformations de valeur

Dans la colonne **Transform** de l’interface d’administration :

- `FIRST_LETTER`  
  Garde uniquement le premier caractère  
  Exemple : `6ème A` → `6`

- `UPPER`  
  Force la valeur en majuscules

- `REGEX:votre_pattern`  
  Applique une expression régulière PHP  
  Exemple :  
  ```
  REGEX:/(.*)\s/
  ```
  → conserve tout avant le premier espace

### Ordre des blocs

- Utiliser la poignée Drag & Drop à gauche de chaque ligne
- L’ordre des lignes = ordre d’affichage sur le dashboard

Simple. Visuel. Sans surprises.

---

## 🔒 Sécurité

- Le fichier `.gitignore` protège vos clés API
- **Ne jamais supprimer** :
  ```
  config/settings.json
  ```
  d’un dépôt public (GitHub, GitLab…)

---

👥 Crédits

Développé par JC.

## 📄 Licence

Ce projet est distribué sous la licence MIT.

Permission est accordée, à titre gratuit, à toute personne obtenant une copie de ce logiciel et des fichiers de documentation associés, d'utiliser le logiciel sans restriction, y compris, sans s'y limiter, les droits d'utiliser, de copier, de modifier, de fusionner, de publier, de distribuer, de sous-licencier et/ou de vendre des copies du logiciel.

## 🔒 Sécurité

Le fichier .gitignore protège vos clés API. Ne poussez jamais votre fichier config/settings.json.

HelloBoard - Développé pour simplifier la vie des associations.

## ❤️ À propos

HelloBoard est pensé pour les associations qui veulent :
- comprendre rapidement leurs chiffres
- afficher leurs résultats fièrement
- arrêter de bidouiller des exports Excel à 23h

HelloBoard - Développé pour simplifier la vie des associations.