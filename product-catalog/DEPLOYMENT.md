# 🚀 Guide de Déploiement - Product Catalog

Ce guide explique comment déployer l'application Product Catalog sur **EasyPanel** depuis GitHub.

## 📋 Prérequis

- Compte EasyPanel avec un serveur configuré
- Repository GitHub avec le code source
- Services externes configurés :
  - Base de données PostgreSQL
  - Redis (optionnel, recommandé pour les queues)
  - MeiliSearch (pour la recherche)
  - Compte AWS S3 (pour le stockage des fichiers)

---

## 🏗️ Architecture de Déploiement

```
┌─────────────────────────────────────────────────────────────┐
│                    EasyPanel Server                          │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Product Catalog Container               │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │    │
│  │  │  Nginx   │  │ PHP-FPM  │  │  Queue Workers   │  │    │
│  │  │  :80     │  │  :9000   │  │  (Supervisor)    │  │    │
│  │  └──────────┘  └──────────┘  └──────────────────┘  │    │
│  └─────────────────────────────────────────────────────┘    │
│                              │                               │
│  ┌───────────┐  ┌───────────┐  ┌───────────────────────┐   │
│  │ PostgreSQL│  │   Redis   │  │     MeiliSearch       │   │
│  │  :5432    │  │   :6379   │  │       :7700           │   │
│  └───────────┘  └───────────┘  └───────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │    AWS S3       │
                    │  (File Storage) │
                    └─────────────────┘
```

---

## 🛠️ Configuration EasyPanel

### 1. Créer les Services Requis

#### PostgreSQL
1. Dans EasyPanel, créer un nouveau service **PostgreSQL**
2. Noter les informations de connexion :
   - Host: `postgres` (ou le nom du service)
   - Port: `5432`
   - Database: `product_catalog`
   - Username: `postgres`
   - Password: `<généré automatiquement>`

#### Redis
1. Créer un nouveau service **Redis**
2. Noter l'host: `redis`

#### MeiliSearch
1. Créer un nouveau service **MeiliSearch**
2. Définir une Master Key sécurisée
3. Noter l'host: `http://meilisearch:7700`

### 2. Créer l'Application Laravel

1. **Créer une nouvelle App** dans EasyPanel
2. **Source**: GitHub
3. **Repository**: Sélectionner votre repo
4. **Branch**: `main` (ou votre branche de production)
5. **Build**: Dockerfile
6. **Port**: `80`

### 3. Variables d'Environnement

Configurer les variables suivantes dans EasyPanel :

```env
# Application
APP_NAME="Product Catalog"
APP_ENV=production
APP_KEY=base64:VOTRE_CLE_GENEREE
APP_DEBUG=false
APP_URL=https://votre-domaine.com
APP_TIMEZONE=Europe/Paris

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=product_catalog
DB_USERNAME=postgres
DB_PASSWORD=VOTRE_MOT_DE_PASSE

# Redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=database

# Session & Cache
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis

# MeiliSearch
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=VOTRE_MASTER_KEY

# AWS S3
AWS_ACCESS_KEY_ID=VOTRE_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=VOTRE_SECRET_KEY
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=votre-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# Services IA (3D Generation)
FAL_API_KEY=VOTRE_FAL_KEY
MESHY_API_KEY=VOTRE_MESHY_KEY

# JWT (pour l'API externe)
JWT_SECRET=VOTRE_JWT_SECRET
JWT_TTL=60
JWT_REFRESH_TTL=20160

# Mail (optionnel)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@votre-domaine.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 4. Générer les Clés

```bash
# Générer APP_KEY (exécuter localement)
php artisan key:generate --show

# Générer JWT_SECRET (exécuter localement)
php artisan jwt:secret --show
```

---

## 📦 Structure des Fichiers Docker

```
docker/
├── entrypoint.sh       # Script de démarrage
├── nginx/
│   ├── nginx.conf      # Configuration Nginx principale
│   └── default.conf    # Configuration du virtual host
├── php/
│   ├── php.ini         # Configuration PHP
│   └── opcache.ini     # Configuration OPcache
└── supervisor/
    └── supervisord.conf # Configuration Supervisor
```

---

## 🔄 Processus de Déploiement

### Déploiement Automatique

1. **Push sur GitHub** → EasyPanel détecte le changement
2. **Build Docker** → Construction de l'image
3. **Démarrage** → Le conteneur exécute :
   - Migrations automatiques
   - Optimisation des caches
   - Génération de la doc API
   - Démarrage de Nginx, PHP-FPM et Workers

### Déploiement Manuel

Dans EasyPanel, cliquer sur **"Deploy"** pour forcer un redéploiement.

---

## 🔧 Commandes Utiles

### Accéder au Shell du Conteneur

Dans EasyPanel → App → Terminal :

```bash
# Vérifier le statut des services
supervisorctl status

# Relancer les workers de queue
supervisorctl restart queue-worker:*

# Exécuter des commandes Artisan
php artisan migrate:status
php artisan queue:work --once
php artisan cache:clear
```

### Logs

```bash
# Logs Laravel
tail -f /var/www/html/storage/logs/laravel.log

# Logs Queue Worker
tail -f /var/www/html/storage/logs/queue-worker.log

# Logs Scheduler
tail -f /var/www/html/storage/logs/scheduler.log
```

---

## 🔒 Sécurité

### Checklist Pré-Production

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Clés API sécurisées (non exposées)
- [ ] HTTPS activé (via EasyPanel/Traefik)
- [ ] Permissions de fichiers correctes
- [ ] Logs d'erreurs non exposés publiquement

### Headers de Sécurité

Les headers suivants sont configurés automatiquement via Nginx :
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`

---

## 📊 Monitoring

### Health Check

L'application expose un endpoint de santé :
```
GET /health → 200 OK
```

EasyPanel utilise ce endpoint pour vérifier l'état du conteneur.

### Métriques Recommandées

- CPU/RAM du conteneur
- Temps de réponse des requêtes
- Taille de la queue
- Erreurs 5xx

---

## 🐛 Dépannage

### Le conteneur ne démarre pas

1. Vérifier les logs de build dans EasyPanel
2. Vérifier que toutes les variables d'environnement sont définies
3. Vérifier la connexion à la base de données

### Erreurs 502 Bad Gateway

1. PHP-FPM n'est pas démarré
2. Vérifier les logs Supervisor : `supervisorctl status`

### Les jobs ne s'exécutent pas

1. Vérifier que les workers sont actifs : `supervisorctl status queue-worker:*`
2. Vérifier les logs : `/var/www/html/storage/logs/queue-worker.log`

### Problèmes de permissions

```bash
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
```

---

## 📚 Ressources

- [Documentation Laravel](https://laravel.com/docs)
- [Documentation Filament](https://filamentphp.com/docs)
- [Documentation EasyPanel](https://easypanel.io/docs)
- [API Documentation](/api/documentation) - Swagger UI

---

## 🆘 Support

En cas de problème :
1. Consulter les logs de l'application
2. Vérifier le statut des services dépendants
3. Contacter l'équipe de développement
