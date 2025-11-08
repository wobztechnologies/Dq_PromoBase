# Guide d'utilisation de l'analyse automatique d'images avec RubixML

## 📁 Structure des dossiers d'entraînement

Les dossiers suivants ont été créés pour stocker vos images d'entraînement :

```
storage/app/training/images/
├── position/
│   ├── Front/         # Images de produits vus de face
│   ├── Back/          # Images de produits vus de derrière
│   ├── Left/          # Images de produits vus de gauche
│   ├── Right/         # Images de produits vus de droite
│   ├── LateralLeft/   # Images de produits vus de côté gauche (latéral gauche)
│   ├── LateralRight/  # Images de produits vus de côté droit (latéral droit)
│   ├── Top/           # Images de produits vus du dessus
│   ├── Bottom/        # Images de produits vus du dessous
│   └── PartZoom/      # Images zoomées sur une partie spécifique du produit
└── background/
    ├── neutral/        # Images avec fond neutre (blanc, gris uni)
    └── non-neutral/   # Images avec fond coloré ou complexe
└── product-only/
    ├── product-only/   # Images contenant seulement le vêtement
    └── situational/    # Images avec mise en situation (personne, environnement)
```

## 🚀 Fonctionnement

### 1. Préparation des données d'entraînement

1. **Placez vos images d'entraînement** dans les dossiers appropriés selon leur catégorie
2. **Formats acceptés** : JPG, JPEG, PNG, WebP
3. **Recommandations** :
   - Minimum 50 images par catégorie pour un entraînement basique
   - Idéalement 100+ images par catégorie pour une meilleure précision
   - Varier les types de produits, angles, éclairages
   - Assurez-vous que les images sont bien étiquetées

### 2. Entraînement des modèles

#### Modèle de classification de position

```bash
php artisan ml:train-position
```

Cette commande :
- Lit toutes les images des dossiers `position/*`
- Extrait les features (vecteurs de pixels)
- Entraîne un modèle K-Nearest Neighbors (KNN)
- Teste le modèle et affiche la précision
- Sauvegarde le modèle dans `storage/app/models/position-classifier.rbx`

#### Modèle de classification de fond neutre

```bash
php artisan ml:train-background
```

Cette commande :
- Lit toutes les images des dossiers `background/*`
- Analyse les bords des images pour détecter les fonds neutres
- Entraîne un modèle KNN
- Teste le modèle et affiche la précision
- Sauvegarde le modèle dans `storage/app/models/background-classifier.rbx`

#### Modèle product-only

```bash
php artisan ml:train-product-only
```

Cette commande :
- Lit toutes les images des dossiers `product-only/*`
- Extrait les features (vecteurs de pixels)
- Entraîne un modèle K-Nearest Neighbors (KNN)
- Teste le modèle et affiche la précision
- Sauvegarde le modèle dans `storage/app/models/product-only-classifier.rbx`

### 3. Utilisation automatique

Une fois les modèles entraînés, **l'analyse est automatique** lors de l'upload d'une image :

1. **Upload d'image** : Quand vous uploadez une image via Filament
2. **Analyse en arrière-plan** : Le système analyse automatiquement :
   - **Position** : Front, Back, Left, Right, Top, Bottom (si le modèle existe)
   - **Fond neutre** : Détection automatique basée sur la variance des bords
   - **Product only** : Détection si l'image contient seulement le vêtement ou une mise en situation (si le modèle existe)
   - **Couleur dominante** : Extraction de la couleur principale du vêtement (algorithme sans ML)
3. **Mise à jour automatique** : Les champs `position`, `neutral_background` et `product_only` sont mis à jour automatiquement

## 🔧 Détails techniques

### Service d'analyse

Le service `ImageAnalysisService` est responsable de :
- Télécharger l'image depuis S3
- Extraire les features pour RubixML
- Utiliser les modèles entraînés pour prédire la position
- Analyser les bords pour détecter le fond neutre
- Utiliser les modèles entraînés pour détecter si l'image est "product only" ou "situational"
- Extraire la couleur dominante avec un algorithme de clustering

### Intégration dans ProductImage

Le modèle `ProductImage` déclenche automatiquement l'analyse :
- Lors de la création d'une nouvelle image
- Lors de la modification de l'image (changement de `s3_url`)
- L'analyse s'exécute en arrière-plan via une queue pour ne pas ralentir l'upload

### Détection de couleur dominante

L'algorithme :
1. Redimensionne l'image à 200px de largeur
2. Ignore les bords (10% de marge) pour éviter le fond
3. Quantifie les couleurs (groupement par similarité)
4. Retourne la couleur la plus fréquente en format hexadécimal

## 📊 Amélioration des modèles

### Réentraînement

Pour améliorer les modèles :
1. Ajoutez plus d'images d'entraînement dans les dossiers appropriés
2. Réexécutez les commandes d'entraînement
3. Les nouveaux modèles remplaceront les anciens

### Options de test

```bash
# Utiliser 30% des données pour le test (au lieu de 20% par défaut)
php artisan ml:train-position --test-ratio=0.3
php artisan ml:train-background --test-ratio=0.3
```

## ⚠️ Notes importantes

1. **Premier upload** : Si les modèles ne sont pas encore entraînés :
   - La position sera `null`
   - Le fond neutre sera détecté via l'algorithme heuristique
   - Product only sera détecté via l'algorithme heuristique (analyse de variance des couleurs)
2. **Performance** : L'analyse s'exécute en arrière-plan pour ne pas ralentir l'interface
3. **Erreurs** : Les erreurs d'analyse sont loggées mais n'empêchent pas l'upload de l'image
4. **Couleur dominante** : Toujours disponible, même sans modèle entraîné (algorithme simple)

## 🎯 Prochaines étapes possibles

- Associer automatiquement la couleur dominante détectée à une variante de couleur existante
- Créer une variante de couleur automatiquement si aucune correspondance n'est trouvée
- Améliorer la détection de position avec des modèles plus avancés (CNN, transfer learning)
- Ajouter d'autres détections (détection de logo, qualité d'image, etc.)

