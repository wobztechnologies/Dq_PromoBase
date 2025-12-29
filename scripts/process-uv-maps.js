#!/usr/bin/env node

/**
 * Script pour traiter et corriger les UV maps d'un modèle 3D GLB
 * 
 * Utilise xatlas-three pour générer des UV maps propres et cohérentes.
 * Fallback sur une projection simple si la fragmentation persiste.
 * 
 * Usage: node scripts/process-uv-maps.js <input-file> <output-file> [--analyze-only]
 * 
 * Options:
 *   --analyze-only: Analyser les UV sans modifier le fichier
 *   --force-unwrap: Forcer le re-unwrap même si les UV semblent corrects
 *   --resolution=N: Résolution de l'atlas UV (défaut: 1024)
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
const forceUnwrap = args.includes('--force-unwrap');
const resolutionArg = args.find(arg => arg.startsWith('--resolution='));
const resolution = resolutionArg ? parseInt(resolutionArg.split('=')[1]) : 1024;

if (!inputPath) {
    console.error('Usage: node scripts/process-uv-maps.js <input-file> [output-file] [options]');
    console.error('Options:');
    console.error('  --analyze-only: Analyser les UV sans modifier le fichier');
    console.error('  --force-unwrap: Forcer le re-unwrap même si les UV semblent corrects');
    console.error('  --resolution=N: Résolution de l\'atlas UV (défaut: 1024)');
    process.exit(1);
}

// Configuration xatlas (paramètres optimisés pour minimiser la fragmentation)
const XATLAS_OPTIONS = {
    maxIterations: 4,
    normalDeviationWeight: 2.0,
    straightnessWeight: 6.0,
    useInputMeshUvs: false,  // Ignorer les UV existantes pour repartir de zéro
    resolution: resolution,
    padding: 2,
    rotateCharts: true,
    bruteForce: false,  // Plus rapide
    texelsPerUnit: 0,   // Auto
};

// Seuil pour détecter une fragmentation excessive (nombre d'îles UV)
const MAX_UV_ISLANDS_THRESHOLD = 10;

/**
 * Classe pour analyser et corriger les UV maps
 */
class UVMapProcessor {
    constructor() {
        this.io = null;
        this.document = null;
        this.meshes = [];
        this.uvAnalysis = {
            totalMeshes: 0,
            meshesWithUV: 0,
            meshesWithoutUV: 0,
            estimatedIslands: 0,
            fragmentationScore: 0,
            needsProcessing: false,
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
     * Analyser les UV maps de tous les meshes
     */
    analyzeUVMaps() {
        console.log('\n🔍 Analyse des UV maps...');
        
        this.uvAnalysis.totalMeshes = this.meshes.length;
        let totalVertices = 0;
        let totalUVDiscontinuities = 0;

        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                const uvAttr = primitive.getAttribute('TEXCOORD_0');
                const indices = primitive.getIndices();

                if (!positionAttr) continue;

                const vertexCount = positionAttr.getCount();
                totalVertices += vertexCount;

                if (uvAttr) {
                    this.uvAnalysis.meshesWithUV++;
                    
                    // Estimer la fragmentation en comptant les discontinuités UV
                    const uvArray = uvAttr.getArray();
                    const discontinuities = this.countUVDiscontinuities(uvArray, indices?.getArray(), vertexCount);
                    totalUVDiscontinuities += discontinuities;
                    
                    console.log(`   Mesh: ${mesh.getName() || 'sans nom'}`);
                    console.log(`      Vertices: ${vertexCount}, UV discontinuités: ${discontinuities}`);
                } else {
                    this.uvAnalysis.meshesWithoutUV++;
                    console.log(`   Mesh: ${mesh.getName() || 'sans nom'} - ⚠️ Pas de UV`);
                }
            }
        }

        // Estimer le nombre d'îles UV basé sur les discontinuités
        // Une discontinuité = potentiellement une nouvelle île
        this.uvAnalysis.estimatedIslands = Math.max(1, Math.floor(totalUVDiscontinuities / 100) + 1);
        
        // Score de fragmentation (0-100, plus c'est haut, plus c'est fragmenté)
        this.uvAnalysis.fragmentationScore = Math.min(100, 
            (totalUVDiscontinuities / Math.max(1, totalVertices)) * 1000
        );

        // Déterminer si le traitement est nécessaire
        this.uvAnalysis.needsProcessing = 
            forceUnwrap ||
            this.uvAnalysis.meshesWithoutUV > 0 ||
            this.uvAnalysis.estimatedIslands > MAX_UV_ISLANDS_THRESHOLD ||
            this.uvAnalysis.fragmentationScore > 30;

        console.log('\n📊 Résultat de l\'analyse:');
        console.log(`   Total meshes: ${this.uvAnalysis.totalMeshes}`);
        console.log(`   Meshes avec UV: ${this.uvAnalysis.meshesWithUV}`);
        console.log(`   Meshes sans UV: ${this.uvAnalysis.meshesWithoutUV}`);
        console.log(`   Îles UV estimées: ${this.uvAnalysis.estimatedIslands}`);
        console.log(`   Score de fragmentation: ${this.uvAnalysis.fragmentationScore.toFixed(1)}%`);
        console.log(`   Traitement nécessaire: ${this.uvAnalysis.needsProcessing ? '✅ OUI' : '❌ NON'}`);

        return this.uvAnalysis;
    }

    /**
     * Compter les discontinuités UV (heuristique simple)
     */
    countUVDiscontinuities(uvArray, indices, vertexCount) {
        if (!uvArray || uvArray.length < 2) return 0;
        
        let discontinuities = 0;
        const uvSet = new Set();
        
        // Parcourir les UV et compter les valeurs uniques
        for (let i = 0; i < uvArray.length; i += 2) {
            const u = Math.round(uvArray[i] * 1000) / 1000;
            const v = Math.round(uvArray[i + 1] * 1000) / 1000;
            const key = `${u},${v}`;
            
            if (!uvSet.has(key)) {
                uvSet.add(key);
            }
        }
        
        // Les discontinuités sont approximées par le ratio UV uniques / vertices
        // Plus ce ratio est élevé, plus il y a de coutures
        const uniqueUVs = uvSet.size;
        discontinuities = Math.max(0, uniqueUVs - vertexCount * 0.8);
        
        return discontinuities;
    }

    /**
     * Appliquer le traitement UV avec xatlas (via Three.js si disponible)
     * Fallback sur une projection simple si xatlas n'est pas disponible
     */
    async processUVMaps() {
        console.log('\n⚙️ Traitement des UV maps...');
        
        // Essayer d'utiliser xatlas-three via un processus dynamique
        // Note: xatlas-three nécessite un contexte WebGL, donc on utilise un fallback
        
        let processed = false;
        
        try {
            // Tentative avec xatlas-three (nécessite three.js et WebGL)
            processed = await this.tryXatlasUnwrap();
        } catch (error) {
            console.log(`   ⚠️ xatlas non disponible: ${error.message}`);
            console.log('   📐 Utilisation du fallback avec projection simple...');
        }
        
        if (!processed) {
            // Fallback: Projection simple (box/cylindrical)
            await this.applySimpleProjection();
        }

        return true;
    }

    /**
     * Tenter un unwrap avec xatlas-three
     * Note: Nécessite que xatlas-three soit installé et configuré
     */
    async tryXatlasUnwrap() {
        // Vérifier si xatlas-three est disponible
        let UVUnwrapper;
        try {
            const xatlasModule = await import('xatlas-three');
            UVUnwrapper = xatlasModule.UVUnwrapper;
        } catch (e) {
            throw new Error('xatlas-three non installé');
        }

        console.log('   🔄 Unwrap avec xatlas-three...');
        
        // Pour chaque mesh, appliquer xatlas
        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                const normalAttr = primitive.getAttribute('NORMAL');
                const indices = primitive.getIndices();
                
                if (!positionAttr) continue;

                // Préparer les données pour xatlas
                const positions = positionAttr.getArray();
                const normals = normalAttr?.getArray();
                const indexArray = indices?.getArray();

                // Créer l'unwrapper xatlas
                const unwrapper = new UVUnwrapper(XATLAS_OPTIONS);
                await unwrapper.loadLibrary();

                // Créer une geometry compatible (format Three.js)
                const geometry = {
                    attributes: {
                        position: { array: positions, itemSize: 3 },
                        normal: normals ? { array: normals, itemSize: 3 } : null,
                    },
                    index: indexArray ? { array: indexArray } : null,
                };

                // Appliquer l'unwrap
                const result = await unwrapper.unwrap(geometry);
                
                if (result && result.uv) {
                    // Créer le nouvel attribut UV
                    const uvAccessor = this.document.createAccessor()
                        .setType('VEC2')
                        .setArray(new Float32Array(result.uv));
                    
                    // Sauvegarder les anciennes UV en uv2 si existantes
                    const existingUV = primitive.getAttribute('TEXCOORD_0');
                    if (existingUV) {
                        primitive.setAttribute('TEXCOORD_1', existingUV);
                    }
                    
                    // Appliquer les nouvelles UV
                    primitive.setAttribute('TEXCOORD_0', uvAccessor);
                    
                    console.log(`      ✅ UV régénérées pour ${mesh.getName() || 'mesh'}`);
                }
            }
        }

        return true;
    }

    /**
     * Fallback: Appliquer une projection UV simple (box mapping)
     */
    async applySimpleProjection() {
        console.log('   📦 Application de la projection box mapping...');
        
        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                const normalAttr = primitive.getAttribute('NORMAL');
                
                if (!positionAttr) continue;

                const positions = positionAttr.getArray();
                const normals = normalAttr?.getArray();
                const vertexCount = positionAttr.getCount();
                
                // Calculer la bounding box pour normaliser
                let minX = Infinity, maxX = -Infinity;
                let minY = Infinity, maxY = -Infinity;
                let minZ = Infinity, maxZ = -Infinity;
                
                for (let i = 0; i < positions.length; i += 3) {
                    minX = Math.min(minX, positions[i]);
                    maxX = Math.max(maxX, positions[i]);
                    minY = Math.min(minY, positions[i + 1]);
                    maxY = Math.max(maxY, positions[i + 1]);
                    minZ = Math.min(minZ, positions[i + 2]);
                    maxZ = Math.max(maxZ, positions[i + 2]);
                }
                
                const sizeX = maxX - minX || 1;
                const sizeY = maxY - minY || 1;
                const sizeZ = maxZ - minZ || 1;
                
                // Générer les UV avec box mapping
                const uvs = new Float32Array(vertexCount * 2);
                
                for (let i = 0; i < vertexCount; i++) {
                    const px = positions[i * 3];
                    const py = positions[i * 3 + 1];
                    const pz = positions[i * 3 + 2];
                    
                    let u, v;
                    
                    if (normals) {
                        // Utiliser la normale pour déterminer la face dominante
                        const nx = Math.abs(normals[i * 3]);
                        const ny = Math.abs(normals[i * 3 + 1]);
                        const nz = Math.abs(normals[i * 3 + 2]);
                        
                        if (nx >= ny && nx >= nz) {
                            // Face X dominante
                            u = (pz - minZ) / sizeZ;
                            v = (py - minY) / sizeY;
                        } else if (ny >= nx && ny >= nz) {
                            // Face Y dominante
                            u = (px - minX) / sizeX;
                            v = (pz - minZ) / sizeZ;
                        } else {
                            // Face Z dominante
                            u = (px - minX) / sizeX;
                            v = (py - minY) / sizeY;
                        }
                    } else {
                        // Sans normales, projection simple sur XY
                        u = (px - minX) / sizeX;
                        v = (py - minY) / sizeY;
                    }
                    
                    uvs[i * 2] = Math.max(0, Math.min(1, u));
                    uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
                }
                
                // Sauvegarder les anciennes UV en TEXCOORD_1 (uv2)
                const existingUV = primitive.getAttribute('TEXCOORD_0');
                if (existingUV) {
                    primitive.setAttribute('TEXCOORD_1', existingUV);
                    console.log(`      📋 Anciennes UV sauvegardées en uv2`);
                }
                
                // Créer et appliquer les nouvelles UV
                const uvAccessor = this.document.createAccessor()
                    .setType('VEC2')
                    .setArray(uvs);
                
                primitive.setAttribute('TEXCOORD_0', uvAccessor);
                
                console.log(`      ✅ UV projetées pour ${mesh.getName() || 'mesh'} (${vertexCount} vertices)`);
            }
        }
        
        return true;
    }

    /**
     * Créer un second layer UV (uv2) avec projection cylindrique pour personnalisation
     */
    async createCustomizationUVLayer() {
        console.log('\n🎨 Création du layer UV2 pour personnalisation...');
        
        for (const mesh of this.meshes) {
            const primitives = mesh.listPrimitives();
            
            for (const primitive of primitives) {
                const positionAttr = primitive.getAttribute('POSITION');
                
                if (!positionAttr) continue;

                const positions = positionAttr.getArray();
                const vertexCount = positionAttr.getCount();
                
                // Calculer le centre et les dimensions
                let centerX = 0, centerY = 0, centerZ = 0;
                let minY = Infinity, maxY = -Infinity;
                let maxRadius = 0;
                
                for (let i = 0; i < positions.length; i += 3) {
                    centerX += positions[i];
                    centerY += positions[i + 1];
                    centerZ += positions[i + 2];
                    minY = Math.min(minY, positions[i + 1]);
                    maxY = Math.max(maxY, positions[i + 1]);
                }
                
                centerX /= vertexCount;
                centerY /= vertexCount;
                centerZ /= vertexCount;
                
                for (let i = 0; i < positions.length; i += 3) {
                    const dx = positions[i] - centerX;
                    const dz = positions[i + 2] - centerZ;
                    maxRadius = Math.max(maxRadius, Math.sqrt(dx * dx + dz * dz));
                }
                
                const height = maxY - minY || 1;
                
                // Générer les UV avec projection cylindrique
                const uvs = new Float32Array(vertexCount * 2);
                
                for (let i = 0; i < vertexCount; i++) {
                    const px = positions[i * 3] - centerX;
                    const py = positions[i * 3 + 1];
                    const pz = positions[i * 3 + 2] - centerZ;
                    
                    // Angle autour de l'axe Y
                    let angle = Math.atan2(pz, px);
                    if (angle < 0) angle += Math.PI * 2;
                    
                    const u = angle / (Math.PI * 2);
                    const v = (py - minY) / height;
                    
                    uvs[i * 2] = Math.max(0, Math.min(1, u));
                    uvs[i * 2 + 1] = Math.max(0, Math.min(1, v));
                }
                
                // Créer l'accesseur pour uv2
                const uv2Accessor = this.document.createAccessor()
                    .setType('VEC2')
                    .setArray(uvs);
                
                // Vérifier si TEXCOORD_1 existe déjà
                const existingUV2 = primitive.getAttribute('TEXCOORD_1');
                if (!existingUV2) {
                    primitive.setAttribute('TEXCOORD_1', uv2Accessor);
                    console.log(`      ✅ UV2 cylindrique créé pour ${mesh.getName() || 'mesh'}`);
                } else {
                    console.log(`      ℹ️ UV2 déjà présent pour ${mesh.getName() || 'mesh'}`);
                }
            }
        }
        
        return true;
    }

    /**
     * Sauvegarder le document modifié
     */
    async save(outputPath) {
        console.log(`\n💾 Sauvegarde vers: ${outputPath}`);
        await this.io.write(outputPath, this.document);
        
        const stats = statSync(outputPath);
        console.log(`   Taille finale: ${(stats.size / 1024).toFixed(2)} KB`);
        
        return true;
    }

    /**
     * Obtenir le résultat de l'analyse au format JSON
     */
    getAnalysisResult() {
        return {
            success: true,
            analysis: this.uvAnalysis,
            options: XATLAS_OPTIONS,
        };
    }
}

/**
 * Fonction principale
 */
async function main() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('  UV Map Processor - Correction automatique des UV fragmentées');
    console.log('═══════════════════════════════════════════════════════════════');
    
    const processor = new UVMapProcessor();
    
    try {
        // Charger le fichier
        await processor.init(inputPath);
        
        // Analyser les UV
        const analysis = processor.analyzeUVMaps();
        
        // Mode analyse uniquement
        if (analyzeOnly) {
            console.log('\n📤 Résultat (JSON):');
            console.log(JSON.stringify(processor.getAnalysisResult(), null, 2));
            process.exit(0);
        }
        
        // Traiter si nécessaire
        if (analysis.needsProcessing) {
            await processor.processUVMaps();
            
            // Créer un layer UV2 pour personnalisation si fragmentation détectée
            if (analysis.estimatedIslands > MAX_UV_ISLANDS_THRESHOLD) {
                await processor.createCustomizationUVLayer();
            }
            
            // Sauvegarder
            const finalOutputPath = outputPath || inputPath.replace('.glb', '-uv-processed.glb');
            await processor.save(finalOutputPath);
            
            console.log('\n✅ Traitement terminé avec succès!');
        } else {
            console.log('\n✅ Les UV maps sont correctes, aucun traitement nécessaire.');
            
            // Si un fichier de sortie est spécifié, copier le fichier
            if (outputPath && outputPath !== inputPath) {
                const content = readFileSync(inputPath);
                writeFileSync(outputPath, content);
                console.log(`   Fichier copié vers: ${outputPath}`);
            }
        }
        
        // Afficher le résultat JSON pour parsing par PHP
        console.log('\n📤 Résultat (JSON):');
        console.log(JSON.stringify({
            success: true,
            processed: analysis.needsProcessing,
            analysis: analysis,
            output: outputPath || inputPath,
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

