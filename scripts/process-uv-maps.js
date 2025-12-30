#!/usr/bin/env node

/**
 * Script pour créer des UV maps de personnalisation pour modèles 3D GLB
 * 
 * IMPORTANT: Ce script NE MODIFIE PAS les UV originales (TEXCOORD_0)
 * Il crée un layer UV séparé (TEXCOORD_1) pour la personnalisation Fabric.js
 * 
 * Usage: node scripts/process-uv-maps.js <input-file> <output-file> [options]
 * 
 * Options:
 *   --analyze-only        : Analyser les UV sans modifier le fichier
 *   --personalization-only: Créer uniquement UV2 pour personnalisation (défaut)
 *   --projection=TYPE     : Type de projection (cylindrical, planar, box, spherical)
 *   --preserve-uv2        : Ne pas écraser UV2 si déjà présent
 */

import { existsSync, readFileSync, writeFileSync, statSync } from 'fs';
import { NodeIO } from '@gltf-transform/core';
import { KHRDracoMeshCompression, KHRTextureTransform } from '@gltf-transform/extensions';
import draco3d from 'draco3dgltf';

// Parse arguments
const args = process.argv.slice(2);
const inputPath = args.find(arg => !arg.startsWith('--'));
const outputPath = args.find((arg, i) => !arg.startsWith('--') && i > args.indexOf(inputPath));
const analyzeOnly = args.includes('--analyze-only');
const preserveUV2 = args.includes('--preserve-uv2');
const projectionArg = args.find(arg => arg.startsWith('--projection='));
const autoDetect = !projectionArg || args.includes('--auto-detect');
let projectionType = projectionArg ? projectionArg.split('=')[1] : 'auto';

if (!inputPath) {
    console.error('Usage: node scripts/process-uv-maps.js <input-file> [output-file] [options]');
    console.error('');
    console.error('Options:');
    console.error('  --analyze-only         Analyser les UV sans modifier le fichier');
    console.error('  --projection=TYPE      Forcer un type de projection (désactive auto-détection)');
    console.error('                         Types: cylindrical, planar, box, spherical');
    console.error('  --auto-detect          Auto-détecter la projection (DÉFAUT)');
    console.error('  --preserve-uv2         Ne pas écraser UV2 si déjà présent');
    console.error('');
    console.error('Auto-détection basée sur la forme:');
    console.error('  - Hauteur dominante     → cylindrical (vêtements, bouteilles)');
    console.error('  - Très plat             → planar (posters, écrans)');
    console.error('  - Proportions cubiques  → box (boîtes, caisses)');
    console.error('  - Proportions sphériques → spherical (ballons, casques)');
    console.error('');
    console.error('Exemples:');
    console.error('  node scripts/process-uv-maps.js model.glb output.glb');
    console.error('  node scripts/process-uv-maps.js model.glb output.glb --projection=planar');
    console.error('  node scripts/process-uv-maps.js model.glb --analyze-only');
    process.exit(1);
}

/**
 * Classe pour créer des UV de personnalisation
 */
class UVPersonalizationProcessor {
    constructor() {
        this.io = null;
        this.document = null;
        this.meshes = [];
        this.analysis = {
            totalMeshes: 0,
            meshesWithUV0: 0,
            meshesWithUV1: 0,
            meshesWithTextures: 0,
            hasTextures: false,
            boundingBox: null,
        };
    }

    /**
     * Initialiser le processeur avec le document GLB
     */
    async init(filePath) {
        console.log(`\n📂 Chargement du fichier: ${filePath}`);
        
        if (!existsSync(filePath)) {
            throw new Error(`Le fichier n'existe pas: ${filePath}`);
        }

        const stats = statSync(filePath);
        console.log(`   Taille: ${(stats.size / 1024).toFixed(2)} KB`);

        // Initialiser les modules Draco pour lire les fichiers compressés
        const decoderModule = await draco3d.createDecoderModule();
        const encoderModule = await draco3d.createEncoderModule();

        this.io = new NodeIO()
            .registerExtensions([KHRDracoMeshCompression, KHRTextureTransform])
            .registerDependencies({
                'draco3d.decoder': decoderModule,
                'draco3d.encoder': encoderModule,
            });

        this.document = await this.io.read(filePath);
        this.meshes = this.document.getRoot().listMeshes();
        
        console.log(`   Meshes trouvés: ${this.meshes.length}`);
    }

    /**
     * Analyser le modèle
     */
    analyze() {
        console.log('\n🔍 Analyse du modèle...');
        
        this.analysis.totalMeshes = this.meshes.length;
        
        // Calculer la bounding box globale
        let minX = Infinity, maxX = -Infinity;
        let minY = Infinity, maxY = -Infinity;
        let minZ = Infinity, maxZ = -Infinity;

        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                const uv0Attr = primitive.getAttribute('TEXCOORD_0');
                const uv1Attr = primitive.getAttribute('TEXCOORD_1');
                const material = primitive.getMaterial();

                if (!positionAttr) continue;

                // Compter les UV
                if (uv0Attr) this.analysis.meshesWithUV0++;
                if (uv1Attr) this.analysis.meshesWithUV1++;

                // Vérifier les textures
                if (material) {
                    const baseColor = material.getBaseColorTexture();
                    const normal = material.getNormalTexture();
                    const metalRough = material.getMetallicRoughnessTexture();
                    
                    if (baseColor || normal || metalRough) {
                        this.analysis.meshesWithTextures++;
                        this.analysis.hasTextures = true;
                    }
                }

                // Calculer bounding box
                const positions = positionAttr.getArray();
                for (let i = 0; i < positions.length; i += 3) {
                    minX = Math.min(minX, positions[i]);
                    maxX = Math.max(maxX, positions[i]);
                    minY = Math.min(minY, positions[i + 1]);
                    maxY = Math.max(maxY, positions[i + 1]);
                    minZ = Math.min(minZ, positions[i + 2]);
                    maxZ = Math.max(maxZ, positions[i + 2]);
                }
            }
        }

        this.analysis.boundingBox = {
            min: { x: minX, y: minY, z: minZ },
            max: { x: maxX, y: maxY, z: maxZ },
            size: {
                x: maxX - minX,
                y: maxY - minY,
                z: maxZ - minZ,
            },
            center: {
                x: (minX + maxX) / 2,
                y: (minY + maxY) / 2,
                z: (minZ + maxZ) / 2,
            },
        };

        // Détecter la projection optimale
        this.analysis.detectedProjection = this.detectOptimalProjection();

        console.log('\n📊 Résultat de l\'analyse:');
        console.log(`   Total meshes: ${this.analysis.totalMeshes}`);
        console.log(`   Meshes avec UV0 (textures): ${this.analysis.meshesWithUV0}`);
        console.log(`   Meshes avec UV1 (perso): ${this.analysis.meshesWithUV1}`);
        console.log(`   Meshes avec textures: ${this.analysis.meshesWithTextures}`);
        console.log(`   Modèle texturé: ${this.analysis.hasTextures ? '✅ OUI' : '❌ NON'}`);
        console.log(`   Bounding box: ${this.analysis.boundingBox.size.x.toFixed(3)} x ${this.analysis.boundingBox.size.y.toFixed(3)} x ${this.analysis.boundingBox.size.z.toFixed(3)}`);
        console.log(`   🎯 Projection détectée: ${this.analysis.detectedProjection.type} (confiance: ${this.analysis.detectedProjection.confidence}%)`);
        console.log(`      Raison: ${this.analysis.detectedProjection.reason}`);

        return this.analysis;
    }

    /**
     * Détecter automatiquement la projection optimale basée sur la forme du modèle
     * 
     * Règles de détection:
     * - Cylindrique: Hauteur dominante (vêtements, bouteilles, mugs)
     * - Planaire: Très plat (posters, écrans, cartes)
     * - Box: Proportions cubiques (boîtes, caisses)
     * - Sphérique: Toutes dimensions proches et compact (ballons, casques)
     */
    detectOptimalProjection() {
        const bbox = this.analysis.boundingBox;
        const { x: width, y: height, z: depth } = bbox.size;
        
        // Éviter division par zéro
        const w = Math.max(width, 0.001);
        const h = Math.max(height, 0.001);
        const d = Math.max(depth, 0.001);
        
        // Calculer les ratios
        const heightToWidth = h / w;
        const heightToDepth = h / d;
        const widthToDepth = w / d;
        const depthToWidth = d / w;
        const depthToHeight = d / h;
        
        // Trouver la dimension dominante
        const maxDim = Math.max(w, h, d);
        const minDim = Math.min(w, h, d);
        const midDim = w + h + d - maxDim - minDim;
        
        // Ratio d'aplatissement (plus c'est bas, plus c'est plat)
        const flatnessRatio = minDim / maxDim;
        
        // Ratio de cubicité (plus c'est proche de 1, plus c'est cubique)
        const cubicityRatio = minDim / maxDim;
        
        // Ratio hauteur/base (pour cylindrique)
        const baseSize = Math.max(w, d);
        const heightRatio = h / baseSize;
        
        let type = 'cylindrical';
        let confidence = 70;
        let reason = '';
        
        // 1. Détection PLANAIRE : très plat (une dimension << autres)
        if (flatnessRatio < 0.15) {
            type = 'planar';
            confidence = 90;
            
            if (d < w && d < h) {
                reason = `Très plat en profondeur (Z=${d.toFixed(3)} << X=${w.toFixed(3)}, Y=${h.toFixed(3)})`;
            } else if (w < h && w < d) {
                reason = `Très plat en largeur (X=${w.toFixed(3)} << Y=${h.toFixed(3)}, Z=${d.toFixed(3)})`;
            } else {
                reason = `Très plat en hauteur (Y=${h.toFixed(3)} << X=${w.toFixed(3)}, Z=${d.toFixed(3)})`;
            }
        }
        // 2. Détection SPHÉRIQUE : toutes dimensions proches (ratio > 0.7)
        else if (cubicityRatio > 0.7 && flatnessRatio > 0.7) {
            // Vérifier si c'est vraiment sphérique ou cubique
            // Sphérique = proportions très proches
            const variance = Math.abs(w - h) + Math.abs(h - d) + Math.abs(w - d);
            const avgDim = (w + h + d) / 3;
            const normalizedVariance = variance / (3 * avgDim);
            
            if (normalizedVariance < 0.2) {
                type = 'spherical';
                confidence = 85;
                reason = `Proportions sphériques (variance: ${(normalizedVariance * 100).toFixed(1)}%, ratio: ${cubicityRatio.toFixed(2)})`;
            } else {
                type = 'box';
                confidence = 80;
                reason = `Proportions cubiques (X=${w.toFixed(3)}, Y=${h.toFixed(3)}, Z=${d.toFixed(3)})`;
            }
        }
        // 3. Détection BOX : proportions assez équilibrées mais pas sphériques
        else if (cubicityRatio > 0.5 && heightRatio < 1.5 && heightRatio > 0.67) {
            type = 'box';
            confidence = 75;
            reason = `Forme cubique/rectangulaire (ratio hauteur/base: ${heightRatio.toFixed(2)})`;
        }
        // 4. Détection CYLINDRIQUE : hauteur dominante (défaut pour vêtements)
        else {
            type = 'cylindrical';
            
            if (heightRatio > 1.5) {
                confidence = 90;
                reason = `Hauteur dominante (ratio: ${heightRatio.toFixed(2)}), idéal pour vêtements/bouteilles`;
            } else if (heightRatio > 1.0) {
                confidence = 80;
                reason = `Légèrement allongé verticalement (ratio: ${heightRatio.toFixed(2)})`;
            } else {
                confidence = 65;
                reason = `Forme générale, cylindrique par défaut (ratio: ${heightRatio.toFixed(2)})`;
            }
        }
        
        return {
            type,
            confidence,
            reason,
            metrics: {
                width: w,
                height: h,
                depth: d,
                heightRatio,
                flatnessRatio,
                cubicityRatio,
            },
        };
    }

    /**
     * Créer les UV de personnalisation (TEXCOORD_1)
     */
    async createPersonalizationUVs(projection = 'cylindrical') {
        console.log(`\n🎨 Création des UV de personnalisation (TEXCOORD_1)...`);
        console.log(`   Projection: ${projection}`);
        console.log(`   ⚠️  UV originales (TEXCOORD_0) préservées`);
        
        const bbox = this.analysis.boundingBox;
        let processedCount = 0;
        let skippedCount = 0;

        for (const mesh of this.meshes) {
            const meshName = mesh.getName() || 'sans nom';
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                const normalAttr = primitive.getAttribute('NORMAL');
                const existingUV1 = primitive.getAttribute('TEXCOORD_1');

                if (!positionAttr) continue;

                // Vérifier si UV1 existe déjà et si on doit le préserver
                if (existingUV1 && preserveUV2) {
                    console.log(`   ⏭️  ${meshName}: UV1 existant préservé`);
                    skippedCount++;
                    continue;
                }

                const positions = positionAttr.getArray();
                const normals = normalAttr?.getArray();
                const vertexCount = positionAttr.getCount();

                // Générer les UV selon le type de projection
                let uvs;
                switch (projection) {
                    case 'planar':
                        uvs = this.generatePlanarProjection(positions, normals, vertexCount, bbox);
                        break;
                    case 'box':
                        uvs = this.generateBoxProjection(positions, normals, vertexCount, bbox);
                        break;
                    case 'spherical':
                        uvs = this.generateSphericalProjection(positions, vertexCount, bbox);
                        break;
                    case 'cylindrical':
                    default:
                        uvs = this.generateCylindricalProjection(positions, vertexCount, bbox);
                        break;
                }

                // Créer l'accesseur UV1
                const uv1Accessor = this.document.createAccessor()
                    .setType('VEC2')
                    .setArray(uvs);

                primitive.setAttribute('TEXCOORD_1', uv1Accessor);
                processedCount++;
                
                console.log(`   ✅ ${meshName}: UV personnalisation créé (${vertexCount} vertices)`);
            }
        }

        console.log(`\n📈 Résumé:`);
        console.log(`   UV1 créés: ${processedCount}`);
        console.log(`   UV1 préservés: ${skippedCount}`);

        return { processed: processedCount, skipped: skippedCount };
    }

    /**
     * Projection cylindrique (idéale pour t-shirts, mugs)
     * Wrappe autour de l'axe Y
     */
    generateCylindricalProjection(positions, vertexCount, bbox) {
        const uvs = new Float32Array(vertexCount * 2);
        const centerX = bbox.center.x;
        const centerZ = bbox.center.z;
        const height = bbox.size.y || 1;
        const minY = bbox.min.y;

        for (let i = 0; i < vertexCount; i++) {
            const px = positions[i * 3] - centerX;
            const py = positions[i * 3 + 1];
            const pz = positions[i * 3 + 2] - centerZ;

            // Angle autour de l'axe Y (0 à 2π)
            let angle = Math.atan2(pz, px);
            if (angle < 0) angle += Math.PI * 2;

            // U = angle normalisé (0 à 1)
            // On décale pour que le "devant" soit au centre (U = 0.5)
            let u = angle / (Math.PI * 2);
            u = (u + 0.75) % 1.0; // Décalage pour centrer le devant

            // V = hauteur normalisée (0 à 1)
            const v = (py - minY) / height;

            uvs[i * 2] = Math.max(0, Math.min(1, u));
            uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
        }

        return uvs;
    }

    /**
     * Projection planaire (idéale pour surfaces plates, posters)
     * Projette sur le plan XY (face avant)
     */
    generatePlanarProjection(positions, normals, vertexCount, bbox) {
        const uvs = new Float32Array(vertexCount * 2);
        const sizeX = bbox.size.x || 1;
        const sizeY = bbox.size.y || 1;
        const minX = bbox.min.x;
        const minY = bbox.min.y;

        for (let i = 0; i < vertexCount; i++) {
            const px = positions[i * 3];
            const py = positions[i * 3 + 1];

            // Projection simple sur XY
            const u = (px - minX) / sizeX;
            const v = (py - minY) / sizeY;

            uvs[i * 2] = Math.max(0, Math.min(1, u));
            uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
        }

        return uvs;
    }

    /**
     * Projection box (idéale pour objets cubiques)
     * Projette sur la face la plus appropriée selon la normale
     */
    generateBoxProjection(positions, normals, vertexCount, bbox) {
        const uvs = new Float32Array(vertexCount * 2);
        const sizeX = bbox.size.x || 1;
        const sizeY = bbox.size.y || 1;
        const sizeZ = bbox.size.z || 1;
        const minX = bbox.min.x;
        const minY = bbox.min.y;
        const minZ = bbox.min.z;

        for (let i = 0; i < vertexCount; i++) {
            const px = positions[i * 3];
            const py = positions[i * 3 + 1];
            const pz = positions[i * 3 + 2];

            let u, v;

            if (normals) {
                const nx = Math.abs(normals[i * 3]);
                const ny = Math.abs(normals[i * 3 + 1]);
                const nz = Math.abs(normals[i * 3 + 2]);

                if (nx >= ny && nx >= nz) {
                    // Face X (gauche/droite)
                    u = (pz - minZ) / sizeZ;
                    v = (py - minY) / sizeY;
                } else if (ny >= nx && ny >= nz) {
                    // Face Y (haut/bas)
                    u = (px - minX) / sizeX;
                    v = (pz - minZ) / sizeZ;
                } else {
                    // Face Z (avant/arrière)
                    u = (px - minX) / sizeX;
                    v = (py - minY) / sizeY;
                }
            } else {
                // Sans normales, projection sur XY par défaut
                u = (px - minX) / sizeX;
                v = (py - minY) / sizeY;
            }

            uvs[i * 2] = Math.max(0, Math.min(1, u));
            uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
        }

        return uvs;
    }

    /**
     * Projection sphérique (idéale pour objets ronds)
     */
    generateSphericalProjection(positions, vertexCount, bbox) {
        const uvs = new Float32Array(vertexCount * 2);
        const centerX = bbox.center.x;
        const centerY = bbox.center.y;
        const centerZ = bbox.center.z;

        for (let i = 0; i < vertexCount; i++) {
            const px = positions[i * 3] - centerX;
            const py = positions[i * 3 + 1] - centerY;
            const pz = positions[i * 3 + 2] - centerZ;

            // Distance au centre
            const r = Math.sqrt(px * px + py * py + pz * pz) || 1;

            // Coordonnées sphériques
            const theta = Math.atan2(pz, px); // Angle horizontal
            const phi = Math.acos(py / r);    // Angle vertical

            // Normaliser en UV
            let u = (theta + Math.PI) / (2 * Math.PI);
            const v = phi / Math.PI;

            uvs[i * 2] = Math.max(0, Math.min(1, u));
            uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
        }

        return uvs;
    }

    /**
     * Ajouter les métadonnées "extras" pour identifier les UV de personnalisation
     * Ces métadonnées permettent à Fabric.js/Three.js de récupérer facilement l'UV
     */
    addCustomizationExtras(projection) {
        console.log('\n📋 Ajout des métadonnées customizationUV...');
        
        const root = this.document.getRoot();
        
        // Ajouter les extras au niveau de la scène (root)
        const existingExtras = root.getExtras() || {};
        root.setExtras({
            ...existingExtras,
            uvChannels: {
                TEXCOORD_0: 'originalTexture',
                TEXCOORD_1: 'customizationUV',
            },
            customizationUV: {
                channel: 1,
                attribute: 'TEXCOORD_1',
                threeJsAttribute: 'uv2',
                projection: projection,
                description: 'UV layer for Fabric.js personalization',
            },
            generator: 'DQ_PromoBase UV Processor',
            generatedAt: new Date().toISOString(),
        });
        
        // Ajouter les extras à chaque mesh qui a reçu l'UV de personnalisation
        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const uv1 = primitive.getAttribute('TEXCOORD_1');
                
                if (uv1) {
                    const meshExtras = mesh.getExtras() || {};
                    mesh.setExtras({
                        ...meshExtras,
                        hasCustomizationUV: true,
                        customizationUVChannel: 1,
                    });
                }
            }
        }
        
        console.log('   ✅ Métadonnées ajoutées');
        console.log('   - root.extras.customizationUV.channel = 1');
        console.log('   - root.extras.customizationUV.attribute = "TEXCOORD_1"');
        console.log('   - root.extras.customizationUV.threeJsAttribute = "uv2"');
    }

    /**
     * Sauvegarder le document modifié
     */
    async save(outputPath, projection) {
        // Ajouter les métadonnées avant sauvegarde
        this.addCustomizationExtras(projection);
        
        console.log(`\n💾 Sauvegarde vers: ${outputPath}`);
        await this.io.write(outputPath, this.document);
        
        const stats = statSync(outputPath);
        console.log(`   Taille finale: ${(stats.size / 1024).toFixed(2)} KB`);
        
        return true;
    }

    /**
     * Obtenir le résultat au format JSON
     */
    getResult(processed = false, processedCount = 0) {
        return {
            success: true,
            processed: processed,
            analysis: this.analysis,
            projection: projectionType,
            uv1Created: processedCount,
            uvOriginalPreserved: true,
        };
    }
}

/**
 * Fonction principale
 */
async function main() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('  UV Personnalisation Processor');
    console.log('  Crée TEXCOORD_1 pour Fabric.js - Préserve TEXCOORD_0');
    console.log('  🎯 Auto-détection de projection activée par défaut');
    console.log('═══════════════════════════════════════════════════════════════');
    
    const processor = new UVPersonalizationProcessor();
    
    try {
        // Charger le fichier
        await processor.init(inputPath);
        
        // Analyser le modèle
        const analysis = processor.analyze();
        
        // Mode analyse uniquement
        if (analyzeOnly) {
            console.log('\n📤 Résultat (JSON):');
            console.log(JSON.stringify(processor.getResult(false, 0), null, 2));
            process.exit(0);
        }
        
        // Déterminer la projection à utiliser
        let finalProjection = projectionType;
        
        if (autoDetect || projectionType === 'auto') {
            // Utiliser la projection auto-détectée
            finalProjection = analysis.detectedProjection.type;
            console.log(`\n🎯 Auto-détection: utilisation de "${finalProjection}" (confiance: ${analysis.detectedProjection.confidence}%)`);
        } else {
            console.log(`\n📐 Projection manuelle: "${finalProjection}"`);
        }
        
        // Créer les UV de personnalisation
        const result = await processor.createPersonalizationUVs(finalProjection);
        
        // Sauvegarder (avec métadonnées customizationUV)
        const finalOutputPath = outputPath || inputPath.replace('.glb', '-personalization.glb');
        await processor.save(finalOutputPath, finalProjection);
        
        console.log('\n✅ Traitement terminé avec succès!');
        console.log('   UV originales (TEXCOORD_0): ✅ Préservées');
        console.log('   UV personnalisation (TEXCOORD_1): ✅ Créées');
        console.log(`   Projection utilisée: ${finalProjection}`);
        if (autoDetect || projectionType === 'auto') {
            console.log(`   (auto-détectée avec ${analysis.detectedProjection.confidence}% de confiance)`);
        }
        
        // Afficher le résultat JSON
        console.log('\n📤 Résultat (JSON):');
        console.log(JSON.stringify({
            success: true,
            processed: result.processed > 0,
            analysis: analysis,
            projection: finalProjection,
            projectionAutoDetected: autoDetect || projectionType === 'auto',
            detectedProjection: analysis.detectedProjection,
            uv1Created: result.processed,
            uv1Skipped: result.skipped,
            uvOriginalPreserved: true,
            output: finalOutputPath,
        }, null, 2));
        
        process.exit(0);
    } catch (error) {
        console.error('\n❌ Erreur:', error.message);
        console.error(error.stack);
        
        console.log('\n📤 Résultat (JSON):');
        console.log(JSON.stringify({
            success: false,
            error: error.message,
        }, null, 2));
        
        process.exit(1);
    }
}

main();
