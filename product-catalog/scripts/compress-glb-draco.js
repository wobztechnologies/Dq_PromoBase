#!/usr/bin/env node

/**
 * Script pour compresser un fichier GLB avec Draco
 * Usage: node scripts/compress-glb-draco.js <input-file> <output-file>
 */

import { existsSync, statSync } from 'fs';
import { NodeIO } from '@gltf-transform/core';
import { KHRDracoMeshCompression } from '@gltf-transform/extensions';
import { dedup, resample, simplify } from '@gltf-transform/functions';
import draco3d from 'draco3dgltf';

const [inputPath, outputPath] = process.argv.slice(2);

if (!inputPath || !outputPath) {
    console.error('Usage: node scripts/compress-glb-draco.js <input-file> <output-file>');
    process.exit(1);
}

async function compressGLB() {
    try {
        console.log(`Compression Draco du fichier: ${inputPath}`);
        
        // Vérifier que le fichier d'entrée existe
        if (!existsSync(inputPath)) {
            throw new Error(`Le fichier d'entrée n'existe pas: ${inputPath}`);
        }
        
        const inputStats = statSync(inputPath);
        console.log(`Taille du fichier original: ${(inputStats.size / 1024).toFixed(2)} KB`);
        
        // Initialiser les modules Draco
        const decoderModule = await draco3d.createDecoderModule();
        const encoderModule = await draco3d.createEncoderModule();
        
        // Configurer NodeIO avec l'extension Draco
        const io = new NodeIO()
            .registerExtensions([KHRDracoMeshCompression])
            .registerDependencies({
                'draco3d.decoder': decoderModule,
                'draco3d.encoder': encoderModule,
            });
        
        // Lire le fichier GLB
        const document = await io.read(inputPath);
        
        // Appliquer les transformations
        await document.transform(
            // Supprimer les doublons
            dedup(),
            // Rééchantillonner les textures si nécessaire
            resample(),
            // Simplifier la géométrie (optionnel, peut réduire la qualité)
            // simplify({ ratio: 0.5 }), // Décommenter pour simplifier
        );
        
        // Activer l'extension Draco
        const dracoExtension = document.createExtension(KHRDracoMeshCompression);
        
        // Configurer la compression Draco
        if (dracoExtension) {
            // Configuration Draco :
            // - compressionLevel: 1-10 (10 = compression max, plus lent)
            // - quantizePosition: bits de quantification pour les positions (8-16)
            // - quantizeNormal: bits de quantification pour les normales (8-16)
            // - quantizeColor: bits de quantification pour les couleurs (8-16)
            // - quantizeTexcoord: bits de quantification pour les UV (8-16)
            dracoExtension.setRequired(true);
            dracoExtension.setEncoderOptions({
                compressionLevel: 7, // Bon compromis vitesse/compression
                quantizePosition: 14, // Haute précision pour les positions
                quantizeNormal: 10, // Précision moyenne pour les normales
                quantizeColor: 8, // Précision basse pour les couleurs (souvent suffisant)
                quantizeTexcoord: 12, // Précision moyenne pour les UV
            });
        }
        
        // Écrire le fichier compressé
        await io.write(outputPath, document);
        
        const outputStats = statSync(outputPath);
        const compressionRatio = ((1 - outputStats.size / inputStats.size) * 100).toFixed(2);
        
        console.log(`Fichier compressé sauvegardé: ${outputPath}`);
        console.log(`Taille du fichier compressé: ${(outputStats.size / 1024).toFixed(2)} KB`);
        console.log(`Ratio de compression: ${compressionRatio}%`);
        
        process.exit(0);
    } catch (error) {
        console.error('Erreur lors de la compression:', error.message);
        console.error(error.stack);
        process.exit(1);
    }
}

compressGLB();
