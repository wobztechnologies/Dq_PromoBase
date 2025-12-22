# Configuration Railway - Guide de Déploiement

Ce document décrit la configuration du déploiement sur Railway selon les meilleures pratiques.

## Configuration du Port

Railway fournit dynamiquement la variable d'environnement `PORT`. La configuration utilise :
- **Nginx** : Le port est substitué au démarrage via `entrypoint.sh` (placeholder `__PORT__`)
- **Dockerfile** : `ENV PORT=8080` comme valeur par défaut
- **Healthcheck** : Utilise `${PORT:-8080}` pour compatibilité

## Healthcheck

Deux endpoints de healthcheck sont configurés :

1. **`/health`** : Route Laravel (utilisée par Railway via `railway.toml`)
   - Gérée par `routes/web.php`
   - Retourne `OK` avec code 200

2. **`/up.php`** : Script PHP simple (utilisé par Docker HEALTHCHECK)
   - Fichier : `public/up.php`
   - Bypasse Laravel pour une réponse rapide

## Configuration Nginx

### Proxy Headers (Railway)

Railway utilise un reverse proxy. La configuration Nginx :
- Trust tous les proxies (`set_real_ip_from 0.0.0.0/0`)
- Forward les headers `X-Forwarded-*` vers PHP-FPM
- Détecte automatiquement HTTPS via `X-Forwarded-Proto`

### Routes Spéciales

Les routes suivantes sont prioritaires pour PHP-FPM :
- `/docs` : Documentation Swagger/API
- `/livewire` : Routes Livewire
- `/admin` : Panel d'administration Filament

## Logs

Tous les logs sont configurés pour `stdout`/`stderr` pour être visibles dans Railway :

- **Supervisor** : `logfile=/dev/stdout`
- **PHP-FPM** : `error_log = /dev/stderr` (dans `php.ini`)
- **Nginx** : Logs vers `/var/log/nginx/` (peuvent être redirigés si nécessaire)
- **Laravel** : `storage/logs/laravel.log` (accessible via Railway)

## Variables d'Environnement Requises

### Obligatoires

```bash
# Application
APP_NAME="Dq_PromoBase"
APP_ENV=production
APP_KEY=base64:...  # Généré avec: php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-app.railway.app

# Database (PostgreSQL sur Railway)
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

# Cache & Session
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

### Optionnelles

```bash
# Redis (si utilisé)
REDIS_HOST=...
REDIS_PASSWORD=...
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="${APP_NAME}"

# Swagger
L5_SWAGGER_GENERATE_ALWAYS=true
```

## Processus Gérés par Supervisor

1. **init** : Initialisation Laravel (migrations, cache, storage link)
2. **nginx** : Serveur web
3. **php-fpm** : Processeur PHP
4. **queue-worker** : 2 workers pour les queues Laravel
5. **scheduler** : Planificateur de tâches Laravel

## Build Process

Le Dockerfile utilise un build multi-stage :

1. **composer-builder** : Installation des dépendances PHP
2. **node-builder** : Build des assets frontend (Vite)
3. **Production** : Image finale avec PHP-FPM, Nginx, Supervisor

## Optimisations

- **OPcache** : Activé pour PHP (production)
- **Route caching** : Activé après initialisation
- **Config caching** : Activé après initialisation
- **View caching** : Activé après initialisation
- **Static files** : Cache 30 jours dans Nginx

## Dépannage

### Erreur 500 sur `/index.php`

Vérifier :
1. Les logs Railway pour les erreurs PHP
2. Que `APP_KEY` est défini
3. Les permissions sur `storage/` et `bootstrap/cache/`
4. Que la base de données est accessible

### Healthcheck échoue

Vérifier :
1. Que le port est correct (variable `PORT`)
2. Que `/health` ou `/up.php` répondent
3. Les logs Supervisor pour les erreurs de démarrage

### Mixed Content (HTTP/HTTPS)

Vérifier :
1. `APP_URL` est en HTTPS
2. `URL::forceScheme('https')` dans `AppServiceProvider`
3. Les headers proxy dans Nginx sont corrects

## Références

- [Documentation Railway](https://docs.railway.com/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Nginx Configuration](https://nginx.org/en/docs/)

