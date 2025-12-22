<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.169.0/examples/jsm/"
    }
}
</script>

<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

// Exposer globalement pour compatibilité
window.THREE = THREE;
window.OrbitControls = OrbitControls;
window.GLTFLoader = GLTFLoader;
window.DRACOLoader = DRACOLoader;
window.threeJSLoaded = true;

// Dispatcher un événement
window.dispatchEvent(new CustomEvent('threejs-loaded'));
</script>

<script>
// Script pour charger les modals Three.js dans Filament
(function() {
    function initModal(container) {
        const modalId = container.getAttribute('data-threejs-modal-id');
        const modelUrl = container.getAttribute('data-threejs-model-url');
        const containerEl = document.getElementById('threejs-container-' + modalId);
        const loading = document.getElementById('threejs-loading-' + modalId);
        
        if (!containerEl || !modelUrl || !loading) return;
        
        // Vérifier si déjà initialisé
        if (containerEl.dataset.initialized === 'true') return;
        containerEl.dataset.initialized = 'true';
        
        let scene, camera, renderer, controls, animationId;
        
        function loadModel() {
            if (typeof THREE === 'undefined') {
                setTimeout(loadModel, 100);
                return;
            }
            initScene();
        }
        
        function initScene() {
            // Scène avec background gris foncé #2d2d2d
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x2d2d2d);
            
            // Stocker la référence de la scène
            containerEl.dataset.sceneInitialized = 'true';
            if (!window.threeJSScenes) window.threeJSScenes = {};
            window.threeJSScenes[modalId] = scene;
            
            // Caméra
            camera = new THREE.PerspectiveCamera(75, containerEl.clientWidth / containerEl.clientHeight, 0.1, 1000);
            camera.position.set(0, 0, 5);
            
            // Renderer avec paramètres GltfViewer "neutral"
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(containerEl.clientWidth, containerEl.clientHeight);
            renderer.outputColorSpace = THREE.SRGBColorSpace;
            
            // ToneMapping: Linear (comme GltfViewer)
            renderer.toneMapping = THREE.LinearToneMapping;
            
            // Exposure: 1.0 (neutral)
            renderer.toneMappingExposure = 1.0;
            
            containerEl.appendChild(renderer.domElement);
            
            // Configuration lumière exacte de GltfViewer "neutral"
            // PunctualLights: true
            
            // Ambient Light - intensity: 0.3, color: #ffffff
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.3);
            scene.add(ambientLight);
            
            // Directional Lights - intensity: 2.5, color: #ffffff
            // Lumière principale (devant, en haut à droite)
            const dirLight1 = new THREE.DirectionalLight(0xffffff, 2.5);
            dirLight1.position.set(5, 10, 7.5);
            scene.add(dirLight1);
            
            // Lumière de remplissage (devant, en haut à gauche)
            const dirLight2 = new THREE.DirectionalLight(0xffffff, 2.5);
            dirLight2.position.set(-5, 5, 5);
            scene.add(dirLight2);
            
            // Contrôles
            const OrbitControlsClass = window.OrbitControls;
            if (OrbitControlsClass) {
                controls = new OrbitControlsClass(camera, renderer.domElement);
                controls.enableDamping = true;
            }
            
            // Loader GLB avec Draco
            const GLTFLoaderClass = window.GLTFLoader;
            if (!GLTFLoaderClass) {
                if (loading) loading.innerHTML = '<p class="text-red-600">Erreur: GLTFLoader non disponible</p>';
                return;
            }
            
            const loader = new GLTFLoaderClass();
            
            // Configurer Draco
            const DRACOLoaderClass = window.DRACOLoader;
            if (DRACOLoaderClass) {
                const dracoLoader = new DRACOLoaderClass();
                dracoLoader.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.5.7/');
                loader.setDRACOLoader(dracoLoader);
            }
            
            // Charger le modèle
            loader.load(
                modelUrl,
                function(gltf) {
                    if (!gltf || !gltf.scene) {
                        if (loading) loading.innerHTML = '<p class="text-red-600">Erreur: Scène vide</p>';
                        return;
                    }
                    
                    scene.add(gltf.scene);
                    
                    // Ajuster caméra
                    const box = new THREE.Box3().setFromObject(gltf.scene);
                    const center = box.getCenter(new THREE.Vector3());
                    const size = box.getSize(new THREE.Vector3());
                    const maxDim = Math.max(size.x, size.y, size.z);
                    const fov = camera.fov * (Math.PI / 180);
                    const cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 1.5;
                    camera.position.set(center.x, center.y, center.z + cameraZ);
                    if (controls) {
                        controls.target.copy(center);
                        controls.update();
                    }
                    
                    if (loading) loading.style.display = 'none';
                    animate();
                },
                function(xhr) {
                    if (xhr.lengthComputable && loading) {
                        const percent = (xhr.loaded / xhr.total) * 100;
                        const p = loading.querySelector('p');
                        if (p) p.textContent = 'Chargement... ' + percent.toFixed(0) + '%';
                    }
                },
                function(error) {
                    console.error('Error loading model:', error);
                    if (loading) loading.innerHTML = '<p class="text-red-600">Erreur lors du chargement</p>';
                }
            );
            
            // Redimensionnement
            window.addEventListener('resize', function() {
                if (containerEl && camera && renderer) {
                    camera.aspect = containerEl.clientWidth / containerEl.clientHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(containerEl.clientWidth, containerEl.clientHeight);
                }
            });
        }
        
        function animate() {
            animationId = requestAnimationFrame(animate);
            if (controls) controls.update();
            if (renderer && scene && camera) {
                renderer.render(scene, camera);
            }
        }
        
        // Attendre que le conteneur soit visible
        function checkAndLoad() {
            if (containerEl && containerEl.offsetParent !== null) {
                loadModel();
            } else {
                setTimeout(checkAndLoad, 100);
            }
        }
        
        setTimeout(checkAndLoad, 200);
    }
    
    // Scanner pour trouver les modals
    function scanModals() {
        document.querySelectorAll('[data-threejs-modal-id]').forEach(function(container) {
            const containerEl = document.getElementById('threejs-container-' + container.getAttribute('data-threejs-modal-id'));
            if (containerEl && containerEl.offsetParent !== null && containerEl.dataset.initialized !== 'true') {
                initModal(container);
            }
        });
    }
    
    // Scanner immédiatement et après les mises à jour Livewire
    scanModals();
    
    if (window.Livewire) {
        Livewire.hook('morph.updated', function() {
            setTimeout(scanModals, 100);
        });
    }
    
    // Scanner périodiquement
    setInterval(scanModals, 500);
})();
</script>
