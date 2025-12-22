# 🚀 Guide de Déploiement - Product Catalog

Ce guide explique comment déployer l'application Product Catalog sur **Railway** depuis GitHub.

## 📋 Prérequis

- Compte [Railway](https://railway.app)
- Repository GitHub avec le code source
- Services externes configurés :
  - Compte AWS S3 (pour le stockage des fichiers)
  - Clés API pour les services 3D (Fal, Meshy)

---

## 🏗️ Architecture de Déploiement

```
┌─────────────────────────────────────────────────────────────┐
│                      Railway Project                         │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │           Product Catalog (Docker)                   │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │    │
│  │  │  Nginx   │  │ PHP-FPM  │  │  Queue Workers   │  │    │
│  │  │  :$PORT  │  │  :9000   │  │  (Supervisor)    │  │    │
│  │  └──────────┘  └──────────┘  └──────────────────┘  │    │
│  └─────────────────────────────────────────────────────┘    │
│                              │                               │
│  ┌───────────┐  ┌───────────┐  ┌───────────────────────┐   │
│  │ PostgreSQL│  │   Redis   │  │     MeiliSearch       │   │
│  │  (Plugin) │  │  (Plugin) │  │      (Service)        │   │
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

## 🛠️ Configuration Railway

### Étape 1 : Créer un nouveau projet

1. Connectez-vous à [Railway](https://railway.app)
2. Cliquez sur **"New Project"**
3. Sélectionnez **"Deploy from GitHub repo"**
4. Autorisez Railway à accéder à votre repo
5. Sélectionnez le repository `product-catalog`

### Étape 2 : Ajouter les services de base de données

#### PostgreSQL
1. Dans votre projet, cliquez sur **"+ New"**
2. Sélectionnez **"Database"** → **"Add PostgreSQL"**
3. Railway crée automatiquement les variables :
   - `DATABASE_URL`
   - `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE`

#### Redis
1. Cliquez sur **"+ New"**
2. Sélectionnez **"Database"** → **"Add Redis"**
3. Railway crée automatiquement :
   - `REDIS_URL`

#### MeiliSearch (optionnel)
1. Cliquez sur **"+ New"** → **"Docker Image"**
2. Image : `getmeili/meilisearch:latest`
3. Ajoutez la variable : `MEILI_MASTER_KEY=votre_master_key`
4. Port : `7700`

### Étape 3 : Configurer les variables d'environnement

Cliquez sur votre service principal → **Variables** → **"Raw Editor"** et ajoutez :

```env
# Application
APP_NAME="Product Catalog"
APP_ENV=production
APP_KEY=base64:VOTRE_CLE_GENEREE
APP_DEBUG=false
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
APP_TIMEZONE=Europe/Paris

# Database (Railway les injecte automatiquement, mais on peut mapper)
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

# Redis (Railway injecte automatiquement)
REDIS_URL=${{Redis.REDIS_URL}}
REDIS_HOST=${{Redis.REDISHOST}}
REDIS_PORT=${{Redis.REDISPORT}}
REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

# Queue
QUEUE_CONNECTION=database

# Session & Cache
SESSION_DRIVER=redis
CACHE_STORE=redis

# MeiliSearch (si configuré)
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://${{MeiliSearch.RAILWAY_PRIVATE_DOMAIN}}:7700
MEILISEARCH_KEY=votre_master_key

# AWS S3
AWS_ACCESS_KEY_ID=votre_access_key
AWS_SECRET_ACCESS_KEY=votre_secret_key
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=votre-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# Services IA (3D Generation)
FAL_API_KEY=votre_fal_key
MESHY_API_KEY=votre_meshy_key

# JWT (pour l'API externe)
JWT_SECRET=votre_jwt_secret
JWT_TTL=60
JWT_REFRESH_TTL=20160

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error
```

### Étape 4 : Générer les clés secrètes

Exécutez ces commandes localement pour générer les clés :

```bash
# Générer APP_KEY
php artisan key:generate --show
# Résultat : base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Générer JWT_SECRET
php artisan jwt:secret --show
# Résultat : xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Étape 5 : Configurer le domaine

1. Allez dans **Settings** de votre service
2. Dans **Networking**, cliquez sur **"Generate Domain"**
3. Vous obtiendrez une URL du type : `product-catalog-production.up.railway.app`
4. Optionnel : Ajoutez un domaine personnalisé

---

## 📁 Fichiers de Configuration Railway

### `railway.toml`
```toml
[build]
builder = "dockerfile"
dockerfilePath = "Dockerfile"

[deploy]
healthcheckPath = "/health"
healthcheckTimeout = 300
restartPolicyType = "ON_FAILURE"
restartPolicyMaxRetries = 10
```

### Structure Docker
```
docker/
├── entrypoint.sh       # Script de démarrage
├── nginx/
│   ├── nginx.conf      # Configuration Nginx
│   └── default.conf    # Virtual host (port dynamique)
├── php/
│   ├── php.ini         # Configuration PHP
│   └── opcache.ini     # OPcache optimisé
└── supervisor/
    └── supervisord.conf # Nginx + PHP-FPM + Queue Workers
```

---

## 🔄 Processus de Déploiement

### Déploiement Automatique

1. **Push sur GitHub** → Railway détecte le changement
2. **Build Docker** → Construction de l'image
3. **Démarrage du conteneur** :
   - Substitution du PORT dynamique Railway
   - Attente de la connexion à la base de données
   - Exécution des migrations
   - Optimisation des caches Laravel
   - Démarrage de Supervisor (Nginx, PHP-FPM, Workers)

### Déploiement Manuel

Dans Railway → Service → **"Deploy"** → **"Trigger Deploy"**

---

## 🔧 Commandes Utiles

### Accéder au Shell

Railway ne permet pas d'accéder directement au shell du conteneur en cours d'exécution.  
Utilisez **Railway CLI** pour exécuter des commandes :

```bash
# Installer Railway CLI
npm install -g @railway/cli

# Se connecter
railway login

# Lier le projet
railway link

# Exécuter une commande
railway run php artisan migrate:status
railway run php artisan queue:work --once
railway run php artisan cache:clear
```

### Voir les logs

Dans Railway → Service → **"Logs"**

Ou via CLI :
```bash
railway logs
railway logs --follow
```

---

## 📊 Monitoring

### Health Check

L'application expose un endpoint :
```
GET /health → 200 OK
```

Railway utilise ce endpoint pour vérifier l'état du service.

### Métriques Railway

Railway fournit automatiquement :
- **CPU** et **RAM** usage
- **Network** I/O
- **Request** metrics
- **Deployment** history

---

## 💰 Coûts Estimés (Railway)

| Service | Estimation mensuelle |
|---------|---------------------|
| App (512MB RAM) | ~$5-10 |
| PostgreSQL | ~$5-10 |
| Redis | ~$5 |
| MeiliSearch | ~$5-10 |
| **Total** | **~$20-35/mois** |

Railway facture à l'usage avec un crédit gratuit de $5/mois.

---

## 🔒 Sécurité

### Checklist Pré-Production

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Clés API dans les variables Railway (jamais dans le code)
- [ ] HTTPS automatique via Railway
- [ ] Variables sensibles marquées comme "secret" dans Railway

### Variables Sensibles

Dans Railway, cliquez sur l'icône 🔒 à côté d'une variable pour la masquer :
- `APP_KEY`
- `DB_PASSWORD`
- `JWT_SECRET`
- `AWS_SECRET_ACCESS_KEY`
- `FAL_API_KEY`
- `MESHY_API_KEY`

---

## 🐛 Dépannage

### Le build échoue

1. Vérifiez les logs de build dans Railway
2. Assurez-vous que le Dockerfile est correct
3. Vérifiez que toutes les dépendances sont listées

### Erreur de connexion à la base de données

1. Vérifiez que PostgreSQL est bien démarré
2. Vérifiez les variables de connexion (utilisez les références `${{Postgres.XXX}}`)
3. L'entrypoint attend automatiquement la DB (30 tentatives)

### Les jobs de queue ne s'exécutent pas

1. Vérifiez que Supervisor est actif dans les logs
2. Vérifiez `QUEUE_CONNECTION=database`
3. Les workers sont gérés par Supervisor automatiquement

### Erreurs 502 / Service unavailable

1. Le conteneur est peut-être en cours de démarrage
2. Vérifiez le health check (`/health`)
3. Augmentez le `healthcheckTimeout` dans `railway.toml`

### Problèmes de mémoire

1. Augmentez la RAM dans Railway Settings
2. Optimisez les requêtes lourdes
3. Réduisez le nombre de workers de queue si nécessaire

---

## 🔗 Liens Utiles

- [Documentation Railway](https://docs.railway.app)
- [Railway CLI](https://docs.railway.app/develop/cli)
- [Railway Discord](https://discord.gg/railway)
- [Documentation Laravel](https://laravel.com/docs)
- [API Documentation](/api/documentation) - Swagger UI

---

## 📝 Commandes Artisan Importantes

```bash
# Migrations
railway run php artisan migrate --force
railway run php artisan migrate:status

# Cache
railway run php artisan config:cache
railway run php artisan route:cache
railway run php artisan view:cache
railway run php artisan optimize

# Queue
railway run php artisan queue:work --once
railway run php artisan queue:retry all

# Maintenance
railway run php artisan down --secret="votre-secret"
railway run php artisan up
```

---

## 🆘 Support

En cas de problème :
1. Consultez les logs dans Railway Dashboard
2. Vérifiez le statut des services (PostgreSQL, Redis)
3. Utilisez Railway CLI pour debug
4. Contactez le support Railway ou l'équipe de développement
