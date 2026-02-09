# 🎭 HelloBoard - Dashboard Live HelloAsso

HelloBoard est une application PHP légère et puissante permettant de suivre en temps réel les inscriptions, les ventes et les recettes de vos campagnes HelloAsso.
Conçue spécifiquement pour les associations, elle transforme les données brutes de l'API en indicateurs visuels clairs et outils opérationnels (émargement, analyse financière).

---

## ✨ Fonctionnalités clés

### 📊 Dashboard Interactif
- **KPIs en temps réel** : Chiffre d'affaires, nombre d'inscrits, taux de générosité (dons).
- **Visualisations avancées** : Graphiques d'évolution (Timeline), répartition par catégories (Pie/Bar/Donut) et Heatmap de densité des inscriptions.
- **Projections (Pacing)** : Estimation de la date de fin d'objectif basée sur la vélocité des 7 derniers jours.
- **Marqueurs temporels** : Visualisez l'impact de vos actions de communication directement sur la courbe des ventes.

### 🛍️ Spécialisation "Boutique" (Shop)
- **Analyse de rentabilité** : Calcul automatique du bénéfice net, de la marge et du taux de contribution par produit.
- **Matrice de performance** : Graphique à bulles comparant le volume de ventes au bénéfice généré.
- **Suivi d'inventaire** : Alertes de stock et barres de progression pour chaque article.

### 📝 Liste d'Émargement (Guestlist)
- **Check-in synchronisé** : Émargez les participants depuis plusieurs appareils simultanément avec synchronisation en temps réel.
- **Sécurité anti-erreur** : Système de verrouillage lors du "dé-pointage" pour éviter les erreurs de manipulation.
- **Vue flexible** : Affichage individuel ou groupé par commande (idéal pour les distributions de produits).
- **Exports & Impression** : Export CSV complet et mode impression optimisé.

### ⚙️ Administration simplifiée
- **Scanner de campagnes** : Importation facile de vos formulaires HelloAsso (Événements, Boutiques, Adhésions, Dons, etc.).
- **Mapping intelligent** : Renommez les articles, regroupez-les et appliquez des transformations (REGEX, Majuscules, etc.).
- **Partage sécurisé** : Génération de liens publics avec jetons (tokens) pour partager le dashboard sans donner d'accès admin.

---

## 📁 Structure du projet

```text
/
├── config/             # Stockage des données (JSON)
│   ├── campaigns/      # Configurations spécifiques à chaque board
│   ├── checkins/       # États d'émargement synchronisés
│   └── settings.json   # Identifiants API HelloAsso (ignoré par Git)
├── public/             # Point d'entrée web
│   ├── admin.php       # Console d'administration et exports
│   ├── api.php         # Endpoint de données temps réel
│   ├── index.php       # Routeur et afficheur des boards
│   └── assets/         # Ressources statiques (images, logo)
├── src/
│   └── Services/       # Logique métier (PHP 8+)
│       ├── HelloAssoClient.php # Communication API
│       ├── StatsEngine.php     # Calculs statistiques et financiers
│       └── Storage.php         # Gestion de la persistance JSON
├── templates/          # Vues HTML/PHP
│   ├── dashboard.php   # Interface du tableau de bord
│   └── guestlist.php   # Interface d'émargement
└── .gitignore          # Protection des données sensibles
```

---

## 🚀 Installation

### Prérequis
- PHP **8.0+**
- Extension **cURL** activée
- Permissions en écriture sur le dossier `config/`

### Déploiement
1. Clonez ou copiez les fichiers sur votre serveur.
2. Assurez-vous que le dossier `config/` est accessible en écriture par le serveur web (CHMOD 755 ou 775).
3. Accédez à `admin.php` pour la configuration initiale.

---

## ⚙️ Configuration Avancée

### Transformations de valeur
Dans l'interface d'administration, vous pouvez transformer les réponses aux champs personnalisés :
- `FIRST_LETTER` : Garde le premier caractère (ex: "6ème A" -> "6").
- `UPPER` : Force la mise en majuscules.
- `REGEX:/pattern/` : Extrait une partie de la valeur via une expression régulière.

### Finances & Stock (Mode Boutique)
Pour les formulaires de type "Shop", vous pouvez renseigner :
- **Prix d'achat** : Pour le calcul du bénéfice net.
- **Stock** : Pour afficher les jauges de disponibilité.

---

## 🔐 Sécurité
- Les clés API sont stockées localement dans `config/settings.json` et ne doivent jamais être poussées sur un dépôt public.
- L'accès à la console admin peut être protégé par mot de passe via les réglages globaux.
- Les dashboards publics utilisent un `shareToken` unique pour empêcher l'accès non autorisé.

---

## 📄 Licence
Distribué sous licence MIT. Libre d'utilisation pour toutes les associations ! ❤️
