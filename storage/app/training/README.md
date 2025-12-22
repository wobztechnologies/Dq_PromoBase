# Dossiers d'entraînement pour les modèles ML

## Structure des dossiers

### Position (Back, Bottom, Front, PartZoom, Side, Top, Left, Right)
Placez vos images d'entraînement dans les dossiers suivants :
- `images/position/Back/` - Images de produits vus de derrière
- `images/position/Bottom/` - Images de produits vus du dessous
- `images/position/Front/` - Images de produits vus de face
- `images/position/PartZoom/` - Images zoomées sur une partie spécifique du produit
- `images/position/Side/` - Images de produits vus de côté (général)
- `images/position/Top/` - Images de produits vus du dessus
- `images/position/Left/` - Images de produits vus de côté gauche
- `images/position/Right/` - Images de produits vus de côté droit

**Note** : Les anciens dossiers `LateralLeft` et `LateralRight` sont automatiquement inclus lors de l'entraînement pour assurer la compatibilité. Les images de `LateralLeft` seront utilisées pour entraîner `Left`, et les images de `LateralRight` seront utilisées pour entraîner `Right`. Vous pouvez les réorganiser manuellement ou utiliser la commande `php artisan ml:reorganize-training-folders`.

### Background (Neutral / Non-neutral)
Placez vos images d'entraînement dans les dossiers suivants :
- `images/background/neutral/` - Images avec fond neutre (blanc, gris, etc.)
- `images/background/non-neutral/` - Images avec fond coloré ou complexe

### Product Only (Vêtement seul / Mise en situation)
Placez vos images d'entraînement dans les dossiers suivants :
- `images/product-only/product-only/` - Images contenant seulement le vêtement (pas de personne, pas d'environnement complexe)
- `images/product-only/situational/` - Images avec mise en situation (personne qui porte le vêtement, environnement, etc.)

## Formats d'images acceptés
- JPG / JPEG
- PNG
- WebP

## Entraînement des modèles

### Modèle de position
```bash
php artisan ml:train-position
```

### Modèle de fond neutre
```bash
php artisan ml:train-background
```

### Modèle product-only
```bash
php artisan ml:train-product-only
```

## Recommandations

- **Minimum 50 images par catégorie** pour un bon entraînement
- **Idéalement 100+ images par catégorie** pour une meilleure précision
- Varier les types de produits, angles, éclairages
- Assurez-vous que les images sont bien étiquetées

## Note

Les modèles entraînés seront sauvegardés dans `storage/app/models/` :
- `position-classifier.rbx` - Modèle de classification de position
- `background-classifier.rbx` - Modèle de classification de fond neutre
- `product-only-classifier.rbx` - Modèle de classification product-only (vêtement seul vs mise en situation)

