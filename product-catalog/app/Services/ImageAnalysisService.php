<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Datasets\Unlabeled;

class ImageAnalysisService
{
    private ImageManager $imageManager;
    private string $positionModelPath;
    private string $backgroundModelPath;
    private string $productOnlyModelPath;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->positionModelPath = storage_path('app/models/position-classifier.rbx');
        $this->backgroundModelPath = storage_path('app/models/background-classifier.rbx');
        $this->productOnlyModelPath = storage_path('app/models/product-only-classifier.rbx');
    }

    /**
     * Analyser une image et retourner les prédictions
     */
    public function analyzeImage(string $s3Url): array
    {
        try {
            // Télécharger l'image depuis S3
            $imageContent = Storage::disk('s3')->get($s3Url);
            if (!$imageContent) {
                throw new \Exception('Impossible de télécharger l\'image depuis S3');
            }

            // Charger l'image avec Intervention Image
            $image = $this->imageManager->read($imageContent);

            return [
                'position' => $this->detectPosition($image, $s3Url),
                'neutral_background' => $this->detectNeutralBackground($image),
                'product_only' => $this->detectProductOnly($image),
                'dominant_color' => $this->detectDominantColor($image),
            ];
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'analyse de l\'image: ' . $e->getMessage());
            return [
                'position' => null,
                'neutral_background' => false,
                'product_only' => false,
                'dominant_color' => null,
            ];
        }
    }

    /**
     * Détecter la position (Front, Back, Left, Right, Top, Bottom)
     * Utilise un modèle RubixML si disponible, sinon retourne null
     */
    private function detectPosition($image, string $s3Url): ?string
    {
        if (!file_exists($this->positionModelPath)) {
            Log::info('Modèle de position non trouvé, retourne null');
            return null;
        }

        try {
            $model = PersistentModel::load(new Filesystem($this->positionModelPath));
            
            // Extraire les features de l'image
            $features = $this->extractImageFeatures($image);
            
            // Créer un dataset Unlabeled pour RubixML
            $dataset = new Unlabeled([$features]);
            $prediction = $model->predict($dataset);
            $predictedPosition = $prediction[0] ?? null;
            
            // Mapper les anciennes positions vers "Side" si nécessaire
            if (in_array($predictedPosition, ['Left', 'Right', 'Lateral Left', 'Lateral Right'])) {
                $predictedPosition = 'Side';
            }
            
            Log::info("Prédiction de position pour {$s3Url}: {$predictedPosition}");
            
            return $predictedPosition;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la prédiction de position: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Détecter si l'image contient seulement le produit (product only) ou une mise en situation
     */
    private function detectProductOnly($image): bool
    {
        if (!file_exists($this->productOnlyModelPath)) {
            Log::info('Modèle product-only non trouvé, utilisation de l\'heuristique');
            // Heuristique simple : analyser la complexité de l'image
            return $this->heuristicProductOnly($image);
        }

        try {
            $model = PersistentModel::load(new Filesystem($this->productOnlyModelPath));
            
            // Extraire les features de l'image
            $features = $this->extractImageFeatures($image);
            
            // Créer un dataset Unlabeled pour RubixML
            $dataset = new Unlabeled([$features]);
            $prediction = $model->predict($dataset);
            $result = $prediction[0] ?? 'false';
            
            return $result === 'true' || $result === true;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la prédiction product-only: ' . $e->getMessage());
            return $this->heuristicProductOnly($image);
        }
    }

    /**
     * Heuristique simple pour détecter si l'image contient seulement le produit
     * Analyse la complexité et la distribution des couleurs
     */
    private function heuristicProductOnly($image): bool
    {
        try {
            $width = $image->width();
            $height = $image->height();
            
            if ($width < 10 || $height < 10) {
                return false;
            }
            
            // Redimensionner pour accélérer
            $resized = $image->scale(width: 200);
            $width = $resized->width();
            $height = $resized->height();
            
            // Analyser la variance des couleurs dans différentes zones
            $centerColors = [];
            $edgeColors = [];
            
            $margin = (int)($width * 0.2); // 20% de marge
            
            // Zone centrale (probablement le produit)
            for ($x = $margin; $x < $width - $margin; $x += 10) {
                for ($y = $margin; $y < $height - $margin; $y += 10) {
                    try {
                        $color = $resized->pickColor($x, $y);
                        if ($color) {
                            $rgb = $color->toArray();
                            $centerColors[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                        }
                    } catch (\Exception $e) {
                        // Ignorer
                    }
                }
            }
            
            // Bords (probablement le fond ou l'environnement)
            for ($x = 0; $x < $width; $x += 10) {
                try {
                    $color = $resized->pickColor($x, 0);
                    if ($color) {
                        $rgb = $color->toArray();
                        $edgeColors[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
                try {
                    $color = $resized->pickColor($x, $height - 1);
                    if ($color) {
                        $rgb = $color->toArray();
                        $edgeColors[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
            }
            
            for ($y = 0; $y < $height; $y += 10) {
                try {
                    $color = $resized->pickColor(0, $y);
                    if ($color) {
                        $rgb = $color->toArray();
                        $edgeColors[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
                try {
                    $color = $resized->pickColor($width - 1, $y);
                    if ($color) {
                        $rgb = $color->toArray();
                        $edgeColors[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
            }
            
            if (empty($centerColors) || empty($edgeColors)) {
                return false;
            }
            
            // Calculer la variance des couleurs
            $centerVariance = $this->calculateColorVariance($centerColors);
            $edgeVariance = $this->calculateColorVariance($edgeColors);
            
            // Si la variance centrale est élevée (produit complexe) et la variance des bords est faible (fond uniforme),
            // c'est probablement product-only
            // Si la variance des bords est élevée, c'est probablement une mise en situation
            return $centerVariance > 1000 && $edgeVariance < 800;
        } catch (\Exception $e) {
            Log::error('Erreur dans l\'heuristique product-only: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Détecter si l'image a un fond neutre
     */
    private function detectNeutralBackground($image): bool
    {
        // Analyser les bords de l'image pour détecter un fond uniforme
        $width = $image->width();
        $height = $image->height();
        
        if ($width < 10 || $height < 10) {
            return false;
        }
        
        // Échantillonner les pixels des bords
        $edgePixels = [];
        $sampleSize = min(50, max(10, (int)($width / 20)));
        
        // Top edge
        for ($x = 0; $x < $width; $x += max(1, (int)($width / $sampleSize))) {
            try {
                $color = $image->pickColor($x, 0);
                if ($color) {
                    $rgb = $color->toArray();
                    $edgePixels[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs de pickColor
            }
        }
        
        // Bottom edge
        for ($x = 0; $x < $width; $x += max(1, (int)($width / $sampleSize))) {
            try {
                $color = $image->pickColor($x, $height - 1);
                if ($color) {
                    $rgb = $color->toArray();
                    $edgePixels[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs
            }
        }
        
        // Left edge
        for ($y = 0; $y < $height; $y += max(1, (int)($height / $sampleSize))) {
            try {
                $color = $image->pickColor(0, $y);
                if ($color) {
                    $rgb = $color->toArray();
                    $edgePixels[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs
            }
        }
        
        // Right edge
        for ($y = 0; $y < $height; $y += max(1, (int)($height / $sampleSize))) {
            try {
                $color = $image->pickColor($width - 1, $y);
                if ($color) {
                    $rgb = $color->toArray();
                    $edgePixels[] = [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs
            }
        }
        
        if (empty($edgePixels)) {
            return false;
        }
        
        // Calculer la variance des couleurs des bords
        $variance = $this->calculateColorVariance($edgePixels);
        
        // Seuil à ajuster selon vos besoins (variance faible = fond neutre)
        return $variance < 500;
    }

    /**
     * Détecter la couleur dominante du vêtement
     * Échantillonne 30% de la surface centrale de l'image
     */
    private function detectDominantColor($image): ?string
    {
        try {
            // Redimensionner pour accélérer le traitement
            $resized = $image->scale(width: 150);
            $width = $resized->width();
            $height = $resized->height();
            
            Log::info("Détection couleur - Image redimensionnée: {$width}x{$height}");
            
            if ($width < 20 || $height < 20) {
                Log::warning("Image trop petite pour la détection de couleur: {$width}x{$height}");
                return null;
            }
            
            // Calculer une zone centrale représentant 30% de l'image
            // Centrée sur le milieu de l'image
            $centerX = (int)($width / 2);
            $centerY = (int)($height / 2);
            
            // Zone de 30% de la largeur et hauteur
            $zoneWidth = (int)($width * 0.3);
            $zoneHeight = (int)($height * 0.3);
            
            // Coordonnées de la zone centrale
            $startX = max(0, $centerX - (int)($zoneWidth / 2));
            $endX = min($width, $centerX + (int)($zoneWidth / 2));
            $startY = max(0, $centerY - (int)($zoneHeight / 2));
            $endY = min($height, $centerY + (int)($zoneHeight / 2));
            
            Log::info("Zone d'échantillonnage centrale (30%): X[{$startX}-{$endX}] Y[{$startY}-{$endY}] - Taille: " . ($endX - $startX) . "x" . ($endY - $startY));
            
            // Extraire les couleurs de la zone centrale
            $colors = [];
            $blackPixels = 0;
            $whitePixels = 0;
            
            for ($x = $startX; $x < $endX; $x += 2) {
                for ($y = $startY; $y < $endY; $y += 2) {
                    try {
                        $color = $resized->pickColor($x, $y);
                        // pickColor retourne un objet Color - utiliser toArray()
                        if ($color) {
                            $rgb = $color->toArray(); // Retourne [R, G, B, Alpha]
                            $r = (int)$rgb[0];
                            $g = (int)$rgb[1];
                            $b = (int)$rgb[2];
                            
                            // Calculer la luminosité
                            $luminance = ($r + $g + $b) / 3;
                            
                            // Filtrer uniquement les pixels purement noirs ou purement blancs
                            // Seuils plus permissifs pour inclure les gris
                            if ($luminance < 10) {
                                $blackPixels++;
                                continue; // Ignorer uniquement le noir pur
                            }
                            if ($luminance > 245) {
                                $whitePixels++;
                                continue; // Ignorer uniquement le blanc pur
                            }
                            
                            $colors[] = [$r, $g, $b];
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de pickColor
                    }
                }
            }
            
            Log::info("Pixels filtrés: {$blackPixels} noirs, {$whitePixels} blancs, " . count($colors) . " couleurs valides");
            
            if (empty($colors)) {
                Log::warning('Aucune couleur extraite pour la détection de couleur dominante');
                return null;
            }
            
            Log::info("Extraction de " . count($colors) . " couleurs pour analyse");
            
            // Trouver la couleur dominante
            $dominantColor = $this->findDominantColor($colors);
            
            // Convertir RGB en hex
            $hex = $this->rgbToHex($dominantColor);
            Log::info("Couleur dominante calculée: {$hex}");
            
            return $hex;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la détection de couleur: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraire les features d'une image pour RubixML
     * DOIT correspondre EXACTEMENT à la méthode utilisée lors de l'entraînement
     */
    private function extractImageFeatures($image): array
    {
        // Redimensionner à 112x112 pour réduire la mémoire (au lieu de 224x224)
        // Cela divise par 4 la taille des features
        $resized = $image->scale(width: 112, height: 112);
        $features = [];
        
        // Échantillonner tous les 2 pixels pour réduire encore la mémoire
        // IMPORTANT : cela doit correspondre EXACTEMENT à l'entraînement
        for ($y = 0; $y < 112; $y += 2) {
            for ($x = 0; $x < 112; $x += 2) {
                try {
                    $color = $resized->pickColor($x, $y);
                    if ($color) {
                        $rgb = $color->toArray(); // Retourne [R, G, B, Alpha]
                        $features[] = (float)$rgb[0]; // R
                        $features[] = (float)$rgb[1]; // G
                        $features[] = (float)$rgb[2]; // B
                    } else {
                        $features[] = 0.0;
                        $features[] = 0.0;
                        $features[] = 0.0;
                    }
                } catch (\Exception $e) {
                    $features[] = 0.0;
                    $features[] = 0.0;
                    $features[] = 0.0;
                }
            }
        }
        
        return $features;
    }

    /**
     * Calculer la variance des couleurs
     */
    private function calculateColorVariance(array $pixels): float
    {
        if (empty($pixels)) {
            return 0;
        }
        
        $rValues = [];
        $gValues = [];
        $bValues = [];
        
        foreach ($pixels as $pixel) {
            if (is_array($pixel) && count($pixel) >= 3) {
                $rValues[] = (float)($pixel[0] ?? 0);
                $gValues[] = (float)($pixel[1] ?? 0);
                $bValues[] = (float)($pixel[2] ?? 0);
            }
        }
        
        if (empty($rValues)) {
            return 0;
        }
        
        $rVariance = $this->variance($rValues);
        $gVariance = $this->variance($gValues);
        $bVariance = $this->variance($bValues);
        
        return ($rVariance + $gVariance + $bVariance) / 3;
    }

    private function variance(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($values);
    }

    /**
     * Trouver la couleur dominante (algorithme simplifié avec clustering plus fin)
     */
    private function findDominantColor(array $colors): array
    {
        // Grouper les couleurs similaires
        $colorGroups = [];
        
        foreach ($colors as $color) {
            if (!is_array($color) || count($color) < 3) {
                continue;
            }
            
            $r = (int)($color[0] ?? 0);
            $g = (int)($color[1] ?? 0);
            $b = (int)($color[2] ?? 0);
            
            // Quantifier les couleurs avec une granularité plus fine (pas de 16 au lieu de 32)
            // Cela permet de mieux distinguer les nuances (gris vs noir, gris clair vs gris foncé)
            $r = (int)($r / 16) * 16;
            $g = (int)($g / 16) * 16;
            $b = (int)($b / 16) * 16;
            
            $key = "$r,$g,$b";
            
            if (!isset($colorGroups[$key])) {
                $colorGroups[$key] = [
                    'count' => 0,
                    'r' => $r,
                    'g' => $g,
                    'b' => $b,
                    'r_sum' => 0,
                    'g_sum' => 0,
                    'b_sum' => 0,
                ];
            }
            
            $colorGroups[$key]['count']++;
            $colorGroups[$key]['r_sum'] += $color[0] ?? 0;
            $colorGroups[$key]['g_sum'] += $color[1] ?? 0;
            $colorGroups[$key]['b_sum'] += $color[2] ?? 0;
        }
        
        // Trouver le groupe le plus fréquent
        $maxCount = 0;
        $dominant = [128, 128, 128]; // Gris par défaut
        
        foreach ($colorGroups as $group) {
            if ($group['count'] > $maxCount) {
                $maxCount = $group['count'];
                // Utiliser la moyenne des couleurs du groupe
                $dominant = [
                    (int)($group['r_sum'] / $group['count']),
                    (int)($group['g_sum'] / $group['count']),
                    (int)($group['b_sum'] / $group['count']),
                ];
            }
        }
        
        Log::info("Couleur dominante brute trouvée: R{$dominant[0]} G{$dominant[1]} B{$dominant[2]} (sur {$maxCount} pixels)");
        
        return $dominant;
    }

    private function rgbToHex(array $rgb): string
    {
        $r = min(255, max(0, (int)($rgb[0] ?? 0)));
        $g = min(255, max(0, (int)($rgb[1] ?? 0)));
        $b = min(255, max(0, (int)($rgb[2] ?? 0)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

