# 🚀 Lutice - Portail Web & Orchestration

![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=for-the-badge&logo=symfony&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)

Ce dépôt contient le **Pôle 1** du projet LUTICE (Hackathon 2026). Cette plateforme automatise l'onboarding des clients souhaitant déployer une instance Greenlight sur l'infrastructure Scaleway.

## 📋 Fonctionnalités Clés

### Portail Client
- **Catalogue d'offres (F-PORT-01)** : Présentation des forfaits Start-2 et Start-3.
- **Souscription (F-PORT-02 & 03)** : Formulaire avec validation Regex et gestion de l'unicité des sous-domaines.
- **Paiement (F-PORT-04)** : Simulation de transaction via l'API Stripe.
- **Suivi (F-PORT-05)** : Interface temps réel (polling) du statut de déploiement.

### Orchestration & Sécurité
- **Identifiants (NF-SEC)** : Utilisation d'UUID v4 et masquage des secrets via variables d'environnement.
- **Dashboard Admin** : Interface protégée par authentification HTTP Basic.
- **API** : Endpoints dédiés pour la communication avec les scripts Ansible (Pôle 3).

---

## 🛠️ Installation

### Prérequis
- PHP 8.1+
- Composer & Symfony CLI
- Serveur MySQL/MariaDB

### Procédure
1. **Clonage**
   ```bash
   git clone [https://github.com/votre-organisation/lutice-portal.git](https://github.com/votre-organisation/lutice-portal.git)
   cd lutice-portal
    DépendancesBashcomposer install
    ConfigurationCréez un fichier .env.local :Extrait de codeDATABASE_URL="mysql://user:pass@127.0.0.1:3306/hackaton_db"
    ADMIN_USERNAME="admin"
    ADMIN_PASSWORD="password"
    Base de donnéesBashphp bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    🔌 API Inter-Pôles (Contrat d'interface)MéthodeEndpointDescriptionGET/api/config/{id}Récupère la configuration clientPOST/api/callback/deploy-ready/{id}Notifie la fin du déploiementPOST/api/callback/deploy-error/{id}Remonte une erreur de provisionnement👥 Équipe Projet[Ton Prénom Nom] - Responsable Pôle 1 (Développement Symfony & API)Hackathon STS CNAM 2026 - Projet LUTICE*
---

### Pourquoi ça sera propre maintenant ?
- Les blocs de code sont entourés de trois backticks (\` \` \`), ce qui créera des encadrés gris.
- Le tableau utilise des barres verticales (`|`) pour être parfaitement aligné sur GitHub.
- Les titres utilisent des `#` pour la hiérarchie.

**Une fois que c'est collé, enregistre et regarde le résultat sur ton interface Git ou GitHub. Est-ce que tu veux qu'on prépare maintenant la fonction pour lancer le script Ansible du Pôle 3 ?**
