<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Meilisearch\Exceptions\CommunicationException;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'manufacturer_id',
        'primary_color_id',
    ];

    protected static function booted(): void
    {
        // Désactiver l'indexation automatique de Scout pour éviter les erreurs si Meilisearch n'est pas disponible
        static::disableSearchSyncing();
        
        // Validation : un produit ne peut pas avoir à la fois une couleur principale et des variantes de couleur
        static::saving(function ($product) {
            // Si le produit a une couleur principale, il ne doit pas avoir de variantes de couleur
            if ($product->primary_color_id && $product->colorVariants()->count() > 0) {
                throw new \Exception('Un produit ne peut pas avoir à la fois une couleur principale et des variantes de couleur. Un produit est soit simple (avec couleur principale), soit variant (avec variantes de couleur).');
            }
        });
        
        // Après la sauvegarde, vérifier si des variantes de couleur ont été ajoutées
        static::saved(function ($product) {
            // Si des variantes de couleur existent, supprimer la couleur principale
            if ($product->colorVariants()->count() > 0 && $product->primary_color_id) {
                $product->primary_color_id = null;
                $product->saveQuietly(); // saveQuietly pour éviter les boucles infinies
            }
            
            // Indexer manuellement dans Meilisearch si disponible
            try {
                $product->searchable();
            } catch (CommunicationException $e) {
                // Meilisearch n'est pas accessible, ignorer silencieusement
                Log::debug('Meilisearch non disponible, indexation ignorée', [
                    'product_id' => $product->id,
                ]);
            } catch (\Exception $e) {
                // Autres erreurs, ignorer également pour ne pas bloquer la sauvegarde
                Log::debug('Erreur lors de l\'indexation dans Meilisearch', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
        
        // Après la suppression, supprimer de Meilisearch si disponible
        static::deleted(function ($product) {
            try {
                $product->unsearchable();
            } catch (\Exception $e) {
                // Ignorer les erreurs de connexion Meilisearch
                Log::debug('Meilisearch non disponible, suppression ignorée', [
                    'product_id' => $product->id,
                ]);
            }
        });
    }


    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function colorVariants(): HasMany
    {
        return $this->hasMany(ProductColorVariant::class);
    }

    /**
     * Couleur principale du produit (si définie directement sans variante)
     */
    public function primaryColor(): BelongsTo
    {
        return $this->belongsTo(PrimaryColor::class);
    }

    /**
     * Variantes de taille directement sur le produit (sans variante de couleur)
     * Uniquement pour les produits simples (avec couleur principale)
     */
    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProductSizeVariant::class)->whereNull('product_color_variant_id');
    }
    
    /**
     * Toutes les variantes de taille du produit (directes + celles des variantes de couleur)
     */
    public function allSizeVariants()
    {
        return ProductSizeVariant::where(function ($query) {
            // Variantes directement sur le produit
            $query->where('product_id', $this->id)
                  ->whereNull('product_color_variant_id');
        })->orWhereHas('colorVariant', function ($query) {
            // Variantes liées aux variantes de couleur du produit
            $query->where('product_id', $this->id);
        });
    }
    
    /**
     * Vérifier si le produit est simple (avec couleur principale) ou variant (avec variantes de couleur)
     * 
     * @return string 'simple' ou 'variant'
     */
    public function getTypeAttribute(): string
    {
        return $this->colorVariants()->count() > 0 ? 'variant' : 'simple';
    }
    
    /**
     * Vérifier si le produit est simple
     */
    public function isSimple(): bool
    {
        return $this->colorVariants()->count() === 0;
    }
    
    /**
     * Vérifier si le produit est variant
     */
    public function isVariant(): bool
    {
        return $this->colorVariants()->count() > 0;
    }

    public function distributors(): HasMany
    {
        return $this->hasMany(ProductDistributor::class);
    }

    /**
     * Prix et stock pour toutes les variations de ce produit
     */
    public function variantPrices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Image par défaut du produit
     */
    public function defaultImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_default', true);
    }

    /**
     * Modèles 3D du produit
     */
    public function models3d(): HasMany
    {
        return $this->hasMany(ProductModel3D::class);
    }

    /**
     * Modèle 3D par défaut du produit
     */
    public function defaultModel3D(): HasOne
    {
        return $this->hasOne(ProductModel3D::class)->where('is_default', true);
    }

    public function toSearchableArray(): array
    {
        $this->load(['category', 'manufacturer', 'colorVariants.primaryColor.parent']);
        
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'manufacturer' => $this->manufacturer?->name,
            'colors' => $this->colorVariants->map(function ($variant) {
                return $variant->primaryColor->full_name ?? $variant->primaryColor->name;
            })->filter()->toArray(),
        ];
    }

    /**
     * Générer le chemin de base pour les assets (images et 3D) selon la structure : /ManufacturerName/SKU/assets/
     */
    public function getAssetsBasePath(): string
    {
        $this->loadMissing('manufacturer');
        $manufacturer = $this->manufacturer;
        $manufacturerName = $manufacturer ? \Illuminate\Support\Str::slug($manufacturer->name) : 'unknown';
        
        return $manufacturerName . '/' . $this->sku . '/assets';
    }

    /**
     * Générer le nom de fichier pour un asset selon le format : SKU-variant(si existe)-numero.ext
     * 
     * @param string $extension Extension du fichier (ex: 'jpg', 'glb')
     * @param string|null $variantSku SKU de la variante si applicable
     * @param int|null $number Numéro séquentiel (auto-incrémenté si null)
     * @return string Nom de fichier complet
     */
    public function generateAssetFilename(string $extension, ?string $variantSku = null, ?int $number = null): string
    {
        $this->loadMissing('manufacturer');
        
        $filename = $this->sku;
        
        if ($variantSku) {
            // Si le variantSku contient déjà le SKU du produit (ex: PROD-000045-BLE),
            // extraire uniquement la partie variante (BLE)
            $variantPart = $variantSku;
            if (str_starts_with($variantSku, $this->sku . '-')) {
                $variantPart = substr($variantSku, strlen($this->sku) + 1);
            }
            $filename .= '-' . $variantPart;
        }
        
        if ($number === null) {
            // Auto-incrémenter le numéro en fonction des fichiers existants
            $basePath = $this->getAssetsBasePath();
            
            try {
                // Lister tous les fichiers dans le dossier assets
                $existingFiles = Storage::disk('s3')->files($basePath);
                
                // Filtrer les fichiers qui correspondent au pattern
                $pattern = $variantSku 
                    ? '/^' . preg_quote($this->sku, '/') . '-' . preg_quote($variantSku, '/') . '-(\d+)\.' . preg_quote($extension, '/') . '$/'
                    : '/^' . preg_quote($this->sku, '/') . '-(\d+)\.' . preg_quote($extension, '/') . '$/';
                
                $maxNumber = 0;
                foreach ($existingFiles as $file) {
                    $basename = basename($file);
                    if (preg_match($pattern, $basename, $matches)) {
                        $fileNumber = (int) $matches[1];
                        if ($fileNumber > $maxNumber) {
                            $maxNumber = $fileNumber;
                        }
                    }
                }
                
                $number = $maxNumber + 1;
            } catch (\Exception $e) {
                // En cas d'erreur, commencer à 1
                \Log::warning('Erreur lors du comptage des fichiers existants, utilisation du numéro 1', [
                    'product_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
                $number = 1;
            }
        }
        
        $filename .= '-' . $number . '.' . $extension;
        
        return $filename;
    }

    /**
     * Générer le chemin complet pour un asset
     */
    public function generateAssetPath(string $extension, ?string $variantSku = null, ?int $number = null): string
    {
        return $this->getAssetsBasePath() . '/' . $this->generateAssetFilename($extension, $variantSku, $number);
    }
}
