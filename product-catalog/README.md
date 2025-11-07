# Product Catalog - Documentation

Application Laravel 12 de gestion de catalogue de produits avec recherche avancée, administration via Filament, et API REST.

## 📋 Table des matières

- [Présentation](#présentation)
- [Technologies utilisées](#technologies-utilisées)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Structure de la base de données](#structure-de-la-base-de-données)
- [Utilisation](#utilisation)
- [API REST](#api-rest)
- [Administration Filament](#administration-filament)
- [Recherche avec Meilisearch](#recherche-avec-meilisearch)
- [Upload S3](#upload-s3)
- [Commandes utiles](#commandes-utiles)
- [Docker](#docker)

## 🎯 Présentation

Product Catalog est une application complète de gestion de catalogue de produits offrant :

- **Gestion de produits** : CRUD complet avec variantes de couleurs, images, et associations avec distributeurs
- **Catégories hiérarchiques** : Utilisation de ltree PostgreSQL pour des catégories imbriquées
- **Recherche avancée** : Intégration Meilisearch pour une recherche rapide et performante
- **API REST** : API complète avec authentification Sanctum
- **Interface d'administration** : Panel Filament pour la gestion des données
- **Upload sécurisé** : URLs pré-signées S3 pour l'upload d'images
- **Performance** : Octane avec Swoole pour des performances optimales

## 🛠 Technologies utilisées

- **Laravel 12.10** : Framework PHP
- **PostgreSQL 16** : Base de données avec extensions uuid-ossp et ltree
- **Redis 7** : Cache et sessions
- **Meilisearch** : Moteur de recherche
- **Filament 3** : Interface d'administration
- **Laravel Sanctum** : Authentification API
- **Laravel Scout** : Recherche full-text
- **Laravel Octane** : Serveur haute performance avec Swoole
- **AWS S3** : Stockage d'images et modèles 3D
- **Docker** : Containerisation

## 📦 Prérequis

- Docker et Docker Compose
- PHP 8.3+ (si utilisation sans Docker)
- Composer
- Node.js et npm (pour les assets Filament)

## 🚀 Installation

### 1. Cloner le projet

```bash
git clone <repository-url>
cd product-catalog
```

### 2. Installer les dépendances

```bash
composer install
npm install
```

### 3. Configuration de l'environnement

Copier le fichier `.env.example` vers `.env` et configurer les variables :

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configuration Docker

Lancer les services Docker :

```bash
docker-compose up -d --build
```

Cette commande démarre :
- PostgreSQL 16 sur le port 5432
- Redis 7 sur le port 6379
- Meilisearch sur le port 7700
- L'application Laravel sur le port 8000

### 5. Migrations et seeders

```bash
php artisan migrate --seed
```

### 6. Indexer les produits dans Meilisearch

```bash
php artisan scout:import "App\Models\Product"
```

### 7. Démarrer l'application

**Avec Octane (recommandé) :**

```bash
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
```

**Ou avec le serveur de développement :**

```bash
php artisan serve
```

L'application est accessible sur `http://localhost:8000`

## ⚙️ Configuration

### Variables d'environnement importantes

```env
# Base de données
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=products
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Meilisearch
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700

# AWS S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=https://your-bucket.s3.amazonaws.com

# Octane
OCTANE_SERVER=swoole
```

### Configuration S3

1. Créer un bucket S3 sur AWS
2. Configurer les credentials dans `.env`
3. Vérifier les permissions IAM pour l'upload

## 🗄 Structure de la base de données

### Tables principales

#### `categories`
- `id` (UUID) : Identifiant unique
- `name` (string) : Nom de la catégorie
- `path` (ltree) : Chemin hiérarchique (ex: "1.2.3")
- Index GIST sur `path` pour les requêtes hiérarchiques

#### `products`
- `id` (UUID) : Identifiant unique
- `sku` (string, unique) : Référence produit
- `name` (string) : Nom du produit
- `main_image_s3_url` (string, nullable) : URL de l'image principale
- `model_3d_s3_url` (string, nullable) : URL du modèle 3D
- `category_id` (UUID, FK) : Catégorie
- `manufacturer_id` (UUID, FK) : Fabricant

#### `product_color_variants`
- `id` (UUID) : Identifiant unique
- `product_id` (UUID, FK) : Produit parent
- `primary_color_id` (UUID, FK) : Couleur
- `sku` (string) : SKU de la variante

#### `product_distributors`
- `id` (UUID) : Identifiant unique
- `product_id` (UUID, FK) : Produit
- `distributor_id` (UUID, FK) : Distributeur
- `sku_distributor` (string) : SKU chez le distributeur

### Relations

```
Product
├── belongsTo Category
├── belongsTo Manufacturer
├── hasMany ProductColorVariant
│   └── belongsTo PrimaryColor
├── hasMany ProductDistributor
│   └── belongsTo Distributor
└── hasMany ProductImage
```

## 📖 Utilisation

### Interface d'administration Filament

Accéder à l'interface d'administration :

```
http://localhost:8000/admin
```

Créer un utilisateur administrateur :

```bash
php artisan make:filament-user
```

### Gestion des produits

1. **Créer un produit** : Admin → Products → Create
2. **Ajouter des variantes de couleur** : Onglet "Color Variants"
3. **Associer des distributeurs** : Onglet "Distributors"
4. **Filtres disponibles** :
   - Par catégorie
   - Par fabricant
   - Par présence de variantes couleur

### Gestion des catégories

Les catégories utilisent ltree pour la hiérarchie. Le champ `path` suit le format :
- `1` : Catégorie racine niveau 1
- `1.2` : Sous-catégorie de 1
- `1.2.3` : Sous-sous-catégorie

## 🔌 API REST

### Authentification

L'API utilise Laravel Sanctum. Obtenir un token :

```bash
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

Utiliser le token dans les requêtes :

```bash
Authorization: Bearer {token}
```

### Endpoints

#### Liste des produits

```http
GET /api/products
Authorization: Bearer {token}
```

Réponse paginée avec relations chargées.

#### Détails d'un produit

```http
GET /api/products/{id}
Authorization: Bearer {token}
```

Réponse :

```json
{
  "id": "uuid",
  "sku": "PROD-000001",
  "name": "Produit Test 1",
  "main_image": "https://...",
  "model_3d": "https://...",
  "category": {
    "id": "uuid",
    "name": "Catégorie"
  },
  "manufacturer": {
    "id": "uuid",
    "name": "Fabricant"
  },
  "variants": [
    {
      "id": "uuid",
      "sku": "PROD-000001-ROU",
      "color": {
        "id": "uuid",
        "name": "Rouge",
        "hex_code": "#FF0000"
      }
    }
  ],
  "distributors": [
    {
      "id": "uuid",
      "sku": "DIST-xxx-PROD-000001",
      "distributor": {
        "id": "uuid",
        "name": "Distributeur"
      }
    }
  ]
}
```

#### Recherche

```http
GET /api/search?q=produit&category=Electronique&color=Rouge&manufacturer=Apple
Authorization: Bearer {token}
```

Paramètres de recherche :
- `q` : Terme de recherche (requis)
- `category` : Filtrer par catégorie
- `color` : Filtrer par couleur
- `manufacturer` : Filtrer par fabricant

#### Upload pré-signé S3

```http
POST /api/upload/presigned-url
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": "uuid",
  "extension": "jpg"
}
```

Réponse :

```json
{
  "url": "https://s3.amazonaws.com/...",
  "path": "products/uuid/images/random.jpg"
}
```

## 🔍 Recherche avec Meilisearch

### Configuration

La recherche utilise Laravel Scout avec Meilisearch. Les produits sont indexés automatiquement lors de la création/modification.

### Champs indexés

- `sku` : Référence produit
- `name` : Nom du produit
- `category` : Nom de la catégorie
- `manufacturer` : Nom du fabricant
- `colors` : Tableau des couleurs disponibles

### Réindexation

```bash
# Réindexer tous les produits
php artisan scout:import "App\Models\Product"

# Réindexer un produit spécifique
php artisan scout:import "App\Models\Product" --id=uuid
```

## 📤 Upload S3

### Génération d'URL pré-signée

L'endpoint `/api/upload/presigned-url` génère une URL pré-signée valide 15 minutes pour uploader directement depuis le client vers S3.

### Workflow recommandé

1. Client demande une URL pré-signée
2. Serveur retourne l'URL et le chemin
3. Client upload directement vers S3
4. Client envoie le chemin au serveur pour l'associer au produit

### Exemple JavaScript

```javascript
// 1. Obtenir l'URL pré-signée
const response = await fetch('/api/upload/presigned-url', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    product_id: 'uuid',
    extension: 'jpg'
  })
});

const { url, path } = await response.json();

// 2. Upload vers S3
await fetch(url, {
  method: 'PUT',
  body: file,
  headers: {
    'Content-Type': 'image/jpeg'
  }
});

// 3. Associer au produit
await fetch(`/api/products/${productId}`, {
  method: 'PATCH',
  body: JSON.stringify({ main_image_s3_url: path })
});
```

## 🐳 Docker

### Services

- **app** : Application Laravel avec PHP 8.3-FPM et Swoole
- **postgres** : PostgreSQL 16
- **redis** : Redis 7
- **meilisearch** : Meilisearch latest

### Commandes Docker

```bash
# Démarrer les services
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Arrêter les services
docker-compose down

# Reconstruire les images
docker-compose up -d --build

# Accéder au conteneur
docker-compose exec app bash
```

### Volumes

- `postgres_data` : Données PostgreSQL persistantes

## 🛠 Commandes utiles

### Migrations

```bash
# Créer une migration
php artisan make:migration create_table_name

# Exécuter les migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Réinitialiser la base
php artisan migrate:fresh --seed
```

### Seeders

```bash
# Exécuter les seeders
php artisan db:seed

# Seeders spécifiques
php artisan db:seed --class=DatabaseSeeder
```

### Cache

```bash
# Vider le cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimiser
php artisan optimize
php artisan config:cache
php artisan route:cache
```

### Octane

```bash
# Démarrer Octane
php artisan octane:start --server=swoole

# Redémarrer les workers
php artisan octane:reload

# Arrêter Octane
php artisan octane:stop
```

### Scout

```bash
# Importer tous les modèles
php artisan scout:import "App\Models\Product"

# Flush l'index
php artisan scout:flush "App\Models\Product"
```

## 📁 Structure du projet

```
product-catalog/
├── app/
│   ├── Filament/
│   │   └── Resources/          # Ressources Filament
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/            # Contrôleurs API
│   │   └── Resources/         # API Resources
│   └── Models/                 # Modèles Eloquent
├── database/
│   ├── factories/              # Factories pour les seeders
│   ├── migrations/             # Migrations
│   └── seeders/                # Seeders
├── routes/
│   ├── api.php                 # Routes API
│   └── web.php                 # Routes web
├── config/                     # Fichiers de configuration
├── docker-compose.yml          # Configuration Docker
└── Dockerfile                  # Image Docker
```

## 🔒 Sécurité

- **Authentification** : Laravel Sanctum pour l'API
- **Validation** : Validation des données d'entrée
- **Lazy Loading** : Désactivé en production (AppServiceProvider)
- **CORS** : Configuré pour les requêtes cross-origin
- **S3** : URLs pré-signées avec expiration

## 🐛 Dépannage

### Erreur de connexion PostgreSQL

Vérifier que le service Docker est démarré :

```bash
docker-compose ps
docker-compose up -d postgres
```

### Erreur Meilisearch

Vérifier que Meilisearch est accessible :

```bash
curl http://localhost:7700/health
```

### Erreur S3

Vérifier les credentials AWS dans `.env` et les permissions IAM.

### Erreur Octane/Swoole

Vérifier que l'extension Swoole est installée :

```bash
php -m | grep swoole
```

Dans Docker, Swoole est déjà installé.

## 📝 Notes importantes

- Les UUID sont générés automatiquement via l'extension `uuid-ossp`
- Les catégories utilisent `ltree` pour la hiérarchie
- Les images sont stockées sur S3, pas en local
- La recherche est asynchrone via Meilisearch
- Octane améliore les performances mais nécessite Swoole

## 🤝 Contribution

1. Fork le projet
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence MIT.

## 👤 Auteur

Product Catalog - Application de gestion de catalogue

---

**Note** : Cette documentation est mise à jour régulièrement. Pour toute question, consulter les issues GitHub ou contacter l'équipe de développement.
