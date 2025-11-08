# Dossiers d'entraînement pour les modèles ML

## Structure des dossiers

### Position (Front, Back, Side, Top, Bottom, Part Zoom)
Placez vos images d'entraînement dans les dossiers suivants :
- `images/position/Front/` - Images de produits vus de face
- `images/position/Back/` - Images de produits vus de derrière
- `images/position/Side/` - Images de produits vus de côté (gauche, droite, latéral gauche, latéral droit)
- `images/position/Top/` - Images de produits vus du dessus
- `images/position/Bottom/` - Images de produits vus du dessous
- `images/position/PartZoom/` - Images zoomées sur une partie spécifique du produit

**Note** : Les anciens dossiers `Left`, `Right`, `LateralLeft`, et `LateralRight` sont automatiquement inclus dans `Side` lors de l'entraînement. Vous pouvez les déplacer manuellement dans `Side` ou utiliser la commande `php artisan ml:reorganize-training-folders`.

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

