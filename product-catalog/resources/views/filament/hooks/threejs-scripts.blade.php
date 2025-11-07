<script>
// Variable globale pour suivre le chargement
window.threeJSLoaded = false;
window.threeJSLoading = false;

// Fonction pour charger Three.js de manière synchrone
function loadThreeJS() {
    if (window.threeJSLoaded || window.threeJSLoading) {
        return Promise.resolve();
    }
    
    window.threeJSLoading = true;
    
    return new Promise((resolve, reject) => {
        // Charger Three.js d'abord
        const threeScript = document.createElement('script');
        threeScript.src = 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js';
        threeScript.onload = function() {
            // Vérifier que THREE est disponible
            if (typeof THREE === 'undefined') {
                window.threeJSLoading = false;
                reject(new Error('Three.js n\'est pas disponible après le chargement'));
                return;
            }
            
            // Charger GLTFLoader - utiliser esm.sh qui convertit automatiquement
            const gltfScript = document.createElement('script');
            gltfScript.type = 'module';
            gltfScript.textContent = `
                import * as THREE_MODULE from 'https://esm.sh/three@0.160.0';
                import { GLTFLoader } from 'https://esm.sh/three@0.160.0/examples/jsm/loaders/GLTFLoader.js';
                import { OrbitControls } from 'https://esm.sh/three@0.160.0/examples/jsm/controls/OrbitControls.js';
                
                // Exposer globalement
                window.GLTFLoader = GLTFLoader;
                window.OrbitControls = OrbitControls;
                
                // Utiliser THREE global si disponible, sinon utiliser le module
                const THREE_REF = typeof THREE !== 'undefined' ? THREE : THREE_MODULE;
                THREE_REF.GLTFLoader = GLTFLoader;
                THREE_REF.OrbitControls = OrbitControls;
                
                // Si THREE n'était pas global, le rendre global
                if (typeof THREE === 'undefined') {
                    window.THREE = THREE_MODULE;
                }
                
                window.dispatchEvent(new CustomEvent('threejs-modules-loaded'));
            `;
            
            window.addEventListener('threejs-modules-loaded', function() {
                setTimeout(function() {
                    // Vérifier que tout est bien chargé
                    if (typeof THREE === 'undefined') {
                        window.threeJSLoading = false;
                        reject(new Error('Three.js n\'est pas disponible'));
                        return;
                    }
                    
                    if (typeof THREE.OrbitControls === 'undefined' && typeof OrbitControls === 'undefined') {
                        window.threeJSLoading = false;
                        reject(new Error('OrbitControls n\'est pas disponible après le chargement'));
                        return;
                    }
                    
                    if (typeof THREE.GLTFLoader === 'undefined' && typeof GLTFLoader === 'undefined') {
                        window.threeJSLoading = false;
                        reject(new Error('GLTFLoader n\'est pas disponible après le chargement'));
                        return;
                    }
                    
                    window.threeJSLoaded = true;
                    window.threeJSLoading = false;
                    resolve();
                }, 200);
            }, { once: true });
            
            gltfScript.onerror = function() {
                window.threeJSLoading = false;
                reject(new Error('Erreur lors du chargement de GLTFLoader depuis esm.sh'));
            };
            document.head.appendChild(gltfScript);
        };
        threeScript.onerror = function() {
            window.threeJSLoading = false;
            reject(new Error('Erreur lors du chargement de Three.js'));
        };
        document.head.appendChild(threeScript);
    });
}

// Initialiser les modaux Three.js après le chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Stocker les instances de modaux
    window.threeJSModals = window.threeJSModals || {};
    
    // Initialiser tous les conteneurs avec data-threejs-modal-id
    document.querySelectorAll('[data-threejs-modal-id]').forEach(function(container) {
        const modalId = container.getAttribute('data-threejs-modal-id');
        const modelUrl = container.getAttribute('data-threejs-model-url');
        
        if (!modalId || !modelUrl) return;
        
        const button = container.querySelector('button');
        const modal = document.getElementById('threejs-modal-' + modalId);
        
        if (!button || !modal) return;
        
        let scene, camera, renderer, controls, model;
        let isLoaded = false;
        let animationId = null;
        
        // Gestionnaire de clic pour le bouton
        button.addEventListener('click', function() {
            openThreeJSModal();
        });
        
        // Gestionnaires de clic pour fermer le modal
        document.querySelectorAll('[data-threejs-close-modal="' + modalId + '"]').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeThreeJSModal();
            });
        });
        
        async function openThreeJSModal() {
            if (!modal) return;
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            const loading = document.getElementById('threejs-loading-' + modalId);
            
            try {
                // Charger Three.js si pas déjà chargé
                if (!window.threeJSLoaded) {
                    if (loading) {
                        loading.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div><p class="mt-2 text-sm text-gray-600">Chargement des bibliothèques Three.js...</p></div>';
                    }
                    await loadThreeJS();
                }
                
                // Vérifier que tout est disponible
                if (typeof THREE === 'undefined' || (typeof THREE.OrbitControls === 'undefined' && typeof OrbitControls === 'undefined') || (typeof THREE.GLTFLoader === 'undefined' && typeof GLTFLoader === 'undefined')) {
                    throw new Error('Les bibliothèques Three.js ne sont pas complètement chargées');
                }
                
                // Charger le modèle uniquement si pas déjà chargé
                if (!isLoaded) {
                    if (loading) {
                        loading.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div><p class="mt-2 text-sm text-gray-600">Chargement du modèle...</p></div>';
                    }
                    loadModel();
                } else {
                    // Réafficher le modèle déjà chargé
                    const containerEl = document.getElementById('threejs-container-' + modalId);
                    if (containerEl && renderer) {
                        animate();
                    }
                }
            } catch (error) {
                console.error('Erreur lors du chargement de Three.js:', error);
                if (loading) {
                    loading.innerHTML = '<p class="text-red-600">Erreur: ' + error.message + '. Veuillez recharger la page.</p>';
                }
            }
        }
        
        function closeThreeJSModal() {
            if (!modal) return;
            
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            
            // Arrêter l'animation
            if (animationId) {
                cancelAnimationFrame(animationId);
            }
        }
        
        function loadModel() {
            const containerEl = document.getElementById('threejs-container-' + modalId);
            const loading = document.getElementById('threejs-loading-' + modalId);
            
            if (!containerEl || !loading) return;
            
            // Vérifier si Three.js est chargé
            if (typeof THREE === 'undefined') {
                loading.innerHTML = '<p class="text-red-600">Erreur: Three.js n\'est pas chargé. Veuillez recharger la page.</p>';
                return;
            }
            
            // Initialiser Three.js
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0xf0f0f0);
            
            camera = new THREE.PerspectiveCamera(75, containerEl.clientWidth / containerEl.clientHeight, 0.1, 1000);
            camera.position.set(0, 0, 5);
            
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(containerEl.clientWidth, containerEl.clientHeight);
            renderer.shadowMap.enabled = true;
            
            // Nettoyer le conteneur avant d'ajouter le renderer
            const existingCanvas = containerEl.querySelector('canvas');
            if (existingCanvas) {
                existingCanvas.remove();
            }
            
            containerEl.appendChild(renderer.domElement);
            
            // Ajouter des lumières
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
            scene.add(ambientLight);
            
            const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
            directionalLight.position.set(5, 10, 5);
            directionalLight.castShadow = true;
            scene.add(directionalLight);
            
            // Contrôles OrbitControls
            let OrbitControlsClass = null;
            if (typeof THREE.OrbitControls !== 'undefined') {
                OrbitControlsClass = THREE.OrbitControls;
            } else if (typeof OrbitControls !== 'undefined') {
                OrbitControlsClass = OrbitControls;
            } else if (window.OrbitControls) {
                OrbitControlsClass = window.OrbitControls;
            }
            
            if (OrbitControlsClass) {
                controls = new OrbitControlsClass(camera, renderer.domElement);
                controls.enableDamping = true;
                controls.dampingFactor = 0.05;
            } else {
                loading.innerHTML = '<p class="text-red-600">Erreur: OrbitControls n\'est pas chargé. Veuillez recharger la page.</p>';
                console.error('OrbitControls non trouvé');
                return;
            }
            
            // Charger le modèle GLB
            let GLTFLoaderClass = null;
            if (typeof THREE.GLTFLoader !== 'undefined') {
                GLTFLoaderClass = THREE.GLTFLoader;
            } else if (typeof GLTFLoader !== 'undefined') {
                GLTFLoaderClass = GLTFLoader;
            } else if (window.GLTFLoader) {
                GLTFLoaderClass = window.GLTFLoader;
            }
            
            if (!GLTFLoaderClass) {
                loading.innerHTML = '<p class="text-red-600">Erreur: GLTFLoader n\'est pas chargé. Veuillez recharger la page.</p>';
                console.error('GLTFLoader non trouvé');
                return;
            }
            
            const loader = new GLTFLoaderClass();
            loader.load(
                modelUrl,
                function(gltf) {
                    model = gltf.scene;
                    scene.add(model);
                    
                    // Ajuster la caméra pour cadrer le modèle
                    const box = new THREE.Box3().setFromObject(model);
                    const center = box.getCenter(new THREE.Vector3());
                    const size = box.getSize(new THREE.Vector3());
                    const maxDim = Math.max(size.x, size.y, size.z);
                    const fov = camera.fov * (Math.PI / 180);
                    let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
                    cameraZ *= 1.5;
                    camera.position.set(center.x, center.y, center.z + cameraZ);
                    if (controls) {
                        controls.target.copy(center);
                        controls.update();
                    }
                    
                    loading.style.display = 'none';
                    isLoaded = true;
                    
                    animate();
                },
                function(xhr) {
                    const percentComplete = (xhr.loaded / xhr.total) * 100;
                    console.log('Chargement: ' + percentComplete.toFixed(0) + '%');
                },
                function(error) {
                    console.error('Erreur lors du chargement du modèle:', error);
                    loading.innerHTML = '<p class="text-red-600">Erreur lors du chargement du modèle 3D. Veuillez vérifier que le fichier est accessible.</p>';
                }
            );
            
            // Gérer le redimensionnement
            const handleResize = function() {
                if (containerEl && camera && renderer) {
                    camera.aspect = containerEl.clientWidth / containerEl.clientHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(containerEl.clientWidth, containerEl.clientHeight);
                }
            };
            
            window.addEventListener('resize', handleResize);
        }
        
        function animate() {
            animationId = requestAnimationFrame(animate);
            if (controls) {
                controls.update();
            }
            if (renderer && scene && camera) {
                renderer.render(scene, camera);
            }
        }
        
        // Stocker les fonctions pour ce modal
        window.threeJSModals[modalId] = {
            open: openThreeJSModal,
            close: closeThreeJSModal
        };
    });
});

// Gérer les mises à jour Livewire
document.addEventListener('livewire:init', function() {
    // Réinitialiser après les mises à jour Livewire
    setTimeout(function() {
        document.querySelectorAll('[data-threejs-modal-id]').forEach(function(container) {
            const modalId = container.getAttribute('data-threejs-modal-id');
            if (modalId && !window.threeJSModals[modalId]) {
                // Le modal n'est pas encore initialisé, déclencher l'initialisation
                const event = new Event('DOMContentLoaded');
                document.dispatchEvent(event);
            }
        });
    }, 100);
});
</script>


