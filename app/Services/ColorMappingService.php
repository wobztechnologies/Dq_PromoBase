<?php

namespace App\Services;

use App\Models\PrimaryColor;
use Illuminate\Support\Facades\Log;

class ColorMappingService
{
    /**
     * Trouver la couleur principale la plus proche d'une couleur hex
     */
    public function findClosestPrimaryColor(string $hexColor): ?PrimaryColor
    {
        // Convertir hex en RGB
        $rgb = $this->hexToRgb($hexColor);
        if (!$rgb) {
            return null;
        }

        // Récupérer toutes les couleurs principales
        $primaryColors = PrimaryColor::whereNull('parent_id')->get();

        if ($primaryColors->isEmpty()) {
            Log::warning('Aucune couleur principale trouvée dans la base de données');
            return null;
        }

        $closestColor = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($primaryColors as $color) {
            if (!$color->hex_code) {
                continue;
            }

            $colorRgb = $this->hexToRgb($color->hex_code);
            if (!$colorRgb) {
                continue;
            }

            // Calculer la distance dans l'espace HSV
            $distance = $this->colorDistance($rgb, $colorRgb);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestColor = $color;
            }
        }

        return $closestColor;
    }

    /**
     * Convertir une couleur hex en RGB
     */
    private function hexToRgb(string $hex): ?array
    {
        // Nettoyer le format (#FFFFFF ou FFFFFF)
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }

        return [
            hexdec(substr($hex, 0, 2)), // R
            hexdec(substr($hex, 2, 2)), // G
            hexdec(substr($hex, 4, 2)), // B
        ];
    }

    /**
     * Calculer la distance entre deux couleurs en utilisant HSV
     * Plus adapté pour la perception humaine des couleurs
     * Permet de mieux gérer les variantes (claires, foncées, pastel, saturées)
     */
    private function colorDistance(array $rgb1, array $rgb2): float
    {
        $hsv1 = $this->rgbToHsv($rgb1);
        $hsv2 = $this->rgbToHsv($rgb2);
        
        // Pour les couleurs peu saturées (gris, blanc, noir), on privilégie la valeur (luminosité)
        $isLowSaturation1 = $hsv1[1] < 0.2;
        $isLowSaturation2 = $hsv2[1] < 0.2;
        
        if ($isLowSaturation1 && $isLowSaturation2) {
            // Pour les gris, comparer principalement la luminosité
            return abs($hsv1[2] - $hsv2[2]);
        }
        
        // Distance de teinte (Hue) - circulaire (0-360)
        $hDiff = abs($hsv1[0] - $hsv2[0]);
        if ($hDiff > 180) {
            $hDiff = 360 - $hDiff; // Distance la plus courte sur le cercle
        }
        
        // Normaliser les différences
        $hDiff = $hDiff / 180.0; // Normaliser entre 0 et 1
        $sDiff = abs($hsv1[1] - $hsv2[1]);
        $vDiff = abs($hsv1[2] - $hsv2[2]);
        
        // Poids pour la teinte (beaucoup plus important) et saturation/valeur
        // La teinte est 6x plus importante que saturation/valeur
        // Cela permet de mieux regrouper les variantes (claires, foncées, pastel)
        // Augmenté à 6 pour mieux distinguer les couleurs de teinte différente
        $hWeight = 6.0;
        $sWeight = 1.0;
        $vWeight = 1.0;
        
        return sqrt(
            ($hWeight * $hDiff * $hDiff) +
            ($sWeight * $sDiff * $sDiff) +
            ($vWeight * $vDiff * $vDiff)
        );
    }

    /**
     * Convertir RGB en HSV (Hue, Saturation, Value)
     * Plus adapté pour la perception humaine des couleurs
     */
    private function rgbToHsv(array $rgb): array
    {
        $r = $rgb[0] / 255.0;
        $g = $rgb[1] / 255.0;
        $b = $rgb[2] / 255.0;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        
        // Valeur (Value) - luminosité
        $v = $max;
        
        // Saturation
        $s = $max == 0 ? 0 : $delta / $max;
        
        // Teinte (Hue) - 0-360 degrés
        $h = 0;
        if ($delta != 0) {
            if ($max == $r) {
                $h = 60 * fmod((($g - $b) / $delta), 6);
            } elseif ($max == $g) {
                $h = 60 * ((($b - $r) / $delta) + 2);
            } else {
                $h = 60 * ((($r - $g) / $delta) + 4);
            }
        }
        
        // Normaliser la teinte entre 0 et 360
        if ($h < 0) {
            $h += 360;
        }
        
        return [$h, $s, $v];
    }
}


