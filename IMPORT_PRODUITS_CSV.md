# Guide d'import CSV des produits

Ce document explique comment importer des produits via CSV et comment les variantes (couleur et taille) sont créées automatiquement.

## Table des matières

1. [Format CSV](#format-csv)
2. [Colonnes du CSV](#colonnes-du-csv)
3. [Création automatique des variantes](#création-automatique-des-variantes)
4. [Cas d'usage](#cas-dusage)
5. [Stratégies d'import](#stratégies-dimport)
6. [Exemples pratiques](#exemples-pratiques)

---

## Format CSV

### Colonnes obligatoires

- `sku` : Identifiant unique du produit (ex: `PROD-001`)
- `name` : Nom du produit
- `category_name` : Nom de la catégorie (doit exister dans le système)
- `manufacturer_name` : Nom du fabricant (doit exister dans le système)

### Colonnes optionnelles

- `primary_color_name` : Nom de la couleur principale
- `color_name` : Nom de la couleur fabricant
- `variant_sku` : SKU personnalisé pour la variante couleur (si non fourni, auto-généré)
- `size_name` : Nom de la taille (doit exister dans le système)
- `size_sku` : SKU personnalisé pour la variante taille (si non fourni, auto-généré)
- `distributor_name` : Nom du distributeur (requis en mode distributeur)
- `sku_distributor` : SKU du distributeur (requis en mode distributeur)
- `image_1_url` à `image_8_url` : URLs des images (jusqu'à 8 images)

---

## Colonnes du CSV

### Structure complète

```csv
sku,name,category_name,manufacturer_name,primary_color_name,color_name,variant_sku,size_name,size_sku,distributor_name,sku_distributor,image_1_url,image_2_url,...,image_8_url
```

### Exemple minimal

```csv
sku,name,category_name,manufacturer_name
PROD-001,Chaussures de sport,Chaussures,Nike
```

### Exemple complet

```csv
sku,name,category_name,manufacturer_name,primary_color_name,color_name,variant_sku,size_name,size_sku,image_1_url,image_2_url
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge,Rouge Hawaii,PROD-001-RH,42,PROD-001-RH-42,https://example.com/front.jpg,https://example.com/back.jpg
```

> **Note :** Les colonnes `variant_sku` et `size_sku` sont optionnelles. Si elles ne sont pas fournies, le système génère automatiquement les SKU.

---

## Création automatique des variantes

Le système crée automatiquement les variantes selon les colonnes fournies dans le CSV.

### 1. Produit de base

**Création automatique :**
- Si le SKU n'existe pas, un nouveau produit est créé
- Le produit est associé à la catégorie et au fabricant spécifiés

**Exemple :**
```csv
sku,name,category_name,manufacturer_name
PROD-001,Chaussures de sport,Chaussures,Nike
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ❌ Aucune variante de couleur
- ❌ Aucune variante de taille

---

### 2. Produit avec couleur principale uniquement

**Création automatique :**
- Si `primary_color_name` est fourni sans `color_name`, la couleur principale est associée directement au produit
- Le produit devient un "produit simple" avec une couleur principale

**Exemple :**
```csv
sku,name,category_name,manufacturer_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ✅ Couleur principale associée : `Rouge`
- ❌ Aucune variante de couleur créée
- ❌ Aucune variante de taille

---

### 3. Produit avec couleur fabricant (variante de couleur)

**Création automatique :**
- Si `primary_color_name` ET `color_name` sont fournis, une variante de couleur est créée
- Le SKU de la variante est généré automatiquement : `{sku}_{slug_color_name}`
- La variante est liée au produit et à la couleur fabricant

**Exemple :**
```csv
sku,name,category_name,manufacturer_name,color_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,Rouge
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ✅ Variante de couleur créée : `PROD-001_rouge-hawaii`
- ✅ Couleur fabricant associée : `Rouge Hawaii` (liée à la couleur principale `Rouge`)
- ❌ Aucune variante de taille

**Note importante :** La couleur fabricant doit :
- Exister dans le système
- Être liée à la couleur principale spécifiée
- Appartenir au fabricant du produit

---

### 4. Produit avec variante de taille

**Création automatique :**
- Si `size_name` est fourni, une variante de taille est créée
- Le SKU de la variante de taille est généré automatiquement : `{sku_color_variant}_{slug_size_name}` ou `{sku}_{slug_size_name}` si pas de variante de couleur
- La variante de taille est liée à la variante de couleur (si elle existe) ou directement au produit

**Exemple avec variante de couleur :**
```csv
sku,name,category_name,manufacturer_name,color_name,size_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,42,Rouge
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ✅ Variante de couleur créée : `PROD-001_rouge-hawaii`
- ✅ Variante de taille créée : `PROD-001_rouge-hawaii_42`
- ✅ Taille associée : `42`

**Exemple sans variante de couleur :**
```csv
sku,name,category_name,manufacturer_name,size_name
PROD-001,Chaussures de sport,Chaussures,Nike,42
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ✅ Variante de taille créée : `PROD-001_42`
- ✅ Taille associée : `42`

---

### 5. Produit avec plusieurs variantes (multi-lignes)

**Création automatique :**
- Chaque ligne du CSV crée une combinaison unique de variantes
- Le produit de base est créé une seule fois (basé sur le SKU)
- Chaque ligne crée une nouvelle variante de couleur et/ou de taille

**Exemple :**
```csv
sku,name,category_name,manufacturer_name,color_name,size_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,42,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,43,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,42,Bleu
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,43,Bleu
```

**Résultat :**
- ✅ Produit créé : `PROD-001` (une seule fois)
- ✅ Variante de couleur : `PROD-001_rouge-hawaii`
  - ✅ Variante de taille : `PROD-001_rouge-hawaii_42`
  - ✅ Variante de taille : `PROD-001_rouge-hawaii_43`
- ✅ Variante de couleur : `PROD-001_bleu-ocean`
  - ✅ Variante de taille : `PROD-001_bleu-ocean_42`
  - ✅ Variante de taille : `PROD-001_bleu-ocean_43`

---

### 6. Images associées

**Création automatique :**
- Les images sont téléchargées depuis les URLs fournies (`image_1_url` à `image_8_url`)
- Les images sont stockées dans S3
- Les images sont associées au produit
- Si une variante de couleur existe, les images sont également associées à cette variante

**Exemple :**
```csv
sku,name,category_name,manufacturer_name,color_name,primary_color_name,image_1_url,image_2_url
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,Rouge,https://example.com/front.jpg,https://example.com/back.jpg
```

**Résultat :**
- ✅ Produit créé : `PROD-001`
- ✅ Variante de couleur créée : `PROD-001_rouge-hawaii`
- ✅ Image 1 téléchargée et associée (position: `front`)
- ✅ Image 2 téléchargée et associée (position: `back`)
- ✅ Images associées au produit ET à la variante de couleur

**Positions des images :**
- `image_1_url` → position `front` (image par défaut)
- `image_2_url` → position `back`
- `image_3_url` → position `left`
- `image_4_url` → position `right`
- `image_5_url` → position `top`
- `image_6_url` → position `bottom`
- `image_7_url` → position `detail`
- `image_8_url` → position `detail`

---

## Cas d'usage

### Cas 1 : Produit simple sans variantes

```csv
sku,name,category_name,manufacturer_name
PROD-001,Chaussures de sport,Chaussures,Nike
```

**Résultat :** Produit créé sans variantes.

---

### Cas 2 : Produit simple avec couleur principale

```csv
sku,name,category_name,manufacturer_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge
```

**Résultat :** Produit créé avec couleur principale associée directement.

---

### Cas 3 : Produit avec une seule variante de couleur

```csv
sku,name,category_name,manufacturer_name,color_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,Rouge
```

**Résultat :** Produit créé avec une variante de couleur.

---

### Cas 4 : Produit avec plusieurs variantes de couleur

```csv
sku,name,category_name,manufacturer_name,color_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,Bleu
PROD-001,Chaussures de sport,Chaussures,Nike,Vert Forêt,Vert
```

**Résultat :** Produit créé avec 3 variantes de couleur.

---

### Cas 5 : Produit avec variantes de couleur et de taille

```csv
sku,name,category_name,manufacturer_name,color_name,size_name,primary_color_name
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,42,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,43,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,44,Rouge
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,42,Bleu
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,43,Bleu
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,44,Bleu
```

**Résultat :** Produit créé avec 2 variantes de couleur et 3 variantes de taille pour chaque couleur (6 variantes de taille au total).

---

## Stratégies d'import

### Stratégie : `create_update` (par défaut)

**Comportement :**
- ✅ Crée les produits s'ils n'existent pas
- ✅ Met à jour les produits existants
- ✅ Crée les variantes manquantes
- ✅ Met à jour les variantes existantes

**Utilisation :** Recommandée pour la plupart des cas d'usage.

---

### Stratégie : `update_only`

**Comportement :**
- ❌ Ne crée pas de nouveaux produits
- ✅ Met à jour uniquement les produits existants
- ❌ Ne crée pas de nouvelles variantes
- ✅ Met à jour uniquement les variantes existantes

**Utilisation :** Pour mettre à jour uniquement des produits existants.

---

## Règles de génération des SKU

### SKU de la variante de couleur

**Format :** `{sku_produit}_{slug_color_name}`

**Exemples :**
- Produit `PROD-001` + couleur `Rouge Hawaii` → `PROD-001_rouge-hawaii`
- Produit `CHAU-123` + couleur `Bleu Océan` → `CHAU-123_bleu-ocean`

---

### SKU de la variante de taille

**Format :** `{sku_variante_couleur}_{slug_size_name}` ou `{sku_produit}_{slug_size_name}`

**Exemples :**
- Variante couleur `PROD-001_rouge-hawaii` + taille `42` → `PROD-001_rouge-hawaii_42`
- Produit `PROD-001` (sans variante couleur) + taille `42` → `PROD-001_42`

---

## Validation et erreurs

### Erreurs courantes

1. **Catégorie non trouvée**
   - Erreur : `Catégorie 'X' non trouvée`
   - Solution : Créer la catégorie avant l'import

2. **Fabricant non trouvé**
   - Erreur : `Fabricant 'X' non trouvé`
   - Solution : Créer le fabricant avant l'import

3. **Couleur principale non trouvée**
   - Erreur : `Couleur principale 'X' non trouvée`
   - Solution : Créer la couleur principale avant l'import

4. **Couleur fabricant non trouvée**
   - Erreur : `Couleur fabricant 'X' non trouvée pour la couleur principale 'Y' et le fabricant 'Z'`
   - Solution : Créer la couleur fabricant liée à la couleur principale et au fabricant

5. **Taille non trouvée**
   - Erreur : `Taille 'X' non trouvée`
   - Solution : Créer la taille avant l'import

---

## Exemples pratiques

### Exemple complet : Produit avec toutes les variantes

```csv
sku,name,category_name,manufacturer_name,color_name,size_name,primary_color_name,image_1_url,image_2_url
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,42,Rouge,https://example.com/front.jpg,https://example.com/back.jpg
PROD-001,Chaussures de sport,Chaussures,Nike,Rouge Hawaii,43,Rouge,https://example.com/front.jpg,https://example.com/back.jpg
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,42,Bleu,https://example.com/front-blue.jpg,https://example.com/back-blue.jpg
PROD-001,Chaussures de sport,Chaussures,Nike,Bleu Océan,43,Bleu,https://example.com/front-blue.jpg,https://example.com/back-blue.jpg
```

**Résultat attendu :**

1. **Produit :** `PROD-001`
   - Nom : `Chaussures de sport`
   - Catégorie : `Chaussures`
   - Fabricant : `Nike`

2. **Variante de couleur 1 :** `PROD-001_rouge-hawaii`
   - Couleur principale : `Rouge`
   - Couleur fabricant : `Rouge Hawaii`
   - **Variante de taille 1 :** `PROD-001_rouge-hawaii_42`
   - **Variante de taille 2 :** `PROD-001_rouge-hawaii_43`

3. **Variante de couleur 2 :** `PROD-001_bleu-ocean`
   - Couleur principale : `Bleu`
   - Couleur fabricant : `Bleu Océan`
   - **Variante de taille 1 :** `PROD-001_bleu-ocean_42`
   - **Variante de taille 2 :** `PROD-001_bleu-ocean_43`

4. **Images :**
   - Images associées à chaque variante de couleur selon les URLs fournies

---

## Notes importantes

1. **Ordre des colonnes :** L'ordre des colonnes dans le CSV n'a pas d'importance, seule la présence des en-têtes compte.

2. **Déduplication :** Le système évite de créer des doublons. Si une variante existe déjà (même SKU, même couleur, même taille), elle n'est pas recréée.

3. **Transactions :** Chaque ligne est traitée dans une transaction. Si une erreur survient, seule cette ligne échoue, les autres continuent.

4. **Images :** Les images doivent être accessibles publiquement via URL. Le système les télécharge et les stocke dans S3.

5. **Compatibilité :** Si seulement `color_name` est fourni (sans `primary_color_name`), le système cherche la couleur directement. Cette méthode est maintenue pour compatibilité avec d'anciens imports.

---

## Conclusion

Le système d'import CSV permet de créer automatiquement des produits et leurs variantes de manière efficace. En suivant ce guide, vous pouvez importer des produits simples ou complexes avec toutes leurs variantes en une seule opération.
