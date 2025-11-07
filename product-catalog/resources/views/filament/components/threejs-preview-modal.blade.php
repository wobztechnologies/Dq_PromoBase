@php
    $modalId = str_replace('-', '_', $modalId ?? 'default');
@endphp

<div class="w-full" data-threejs-modal-id="{{ $modalId }}" data-threejs-model-url="{{ $modelUrl ?? '' }}">
    <div id="threejs-container-{{ $modalId }}" class="w-full h-96 bg-gray-100 rounded-lg relative">
        <div id="threejs-loading-{{ $modalId }}" class="absolute inset-0 flex items-center justify-center z-10 bg-gray-100 rounded-lg">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                <p class="mt-2 text-sm text-gray-600">Chargement du modèle...</p>
            </div>
        </div>
    </div>
    <div class="mt-4 text-sm text-gray-500">
        <p>Utilisez la souris pour faire pivoter, la molette pour zoomer, et maintenez Shift + clic pour déplacer.</p>
    </div>
</div>

<script>
(function() {
    const modalId = '{{ $modalId }}';
    const modelUrl = '{{ $modelUrl }}';
    let isInitialized = false;
    
    console.log('Three.js preview modal initialized for:', modalId, 'URL:', modelUrl);
    
    // Fonction pour initialiser Three.js
    const initThreeJS = function() {
        if (isInitialized) {
            console.log('Already initialized, skipping');
            return;
        }
        
        const container = document.getElementById('threejs-container-' + modalId);
        const loading = document.getElementById('threejs-loading-' + modalId);
        
        if (!container) {
            console.log('Container not found:', 'threejs-container-' + modalId);
            return;
        }
        
        // Vérifier si le conteneur est visible (modal ouverte)
        const isVisible = container.offsetParent !== null || container.closest('.fi-modal-open');
        if (!isVisible) {
            console.log('Container not visible yet');
            return;
        }
        
        console.log('Container found and visible, initializing Three.js...');
        isInitialized = true;
        
        // Fonction pour charger le modèle
        const loadModel = function() {
            console.log('Loading model from:', modelUrl);
            loadModel3D(container, modalId, modelUrl);
        };
        
        // Vérifier si Three.js est déjà chargé
        if (typeof THREE !== 'undefined' && window.threeJSLoaded) {
            console.log('Three.js already loaded');
            loadModel();
        } else {
            console.log('Three.js not loaded, loading...');
            // Charger Three.js si nécessaire
            if (typeof loadThreeJS === 'function') {
                loadThreeJS().then(function() {
                    console.log('Three.js loaded successfully');
                    loadModel();
                }).catch(function(error) {
                    console.error('Erreur lors du chargement de Three.js:', error);
                    if (loading) {
                        loading.innerHTML = '<p class="text-red-600">Erreur lors du chargement de Three.js. Veuillez recharger la page.</p>';
                    }
                });
            } else {
                // Attendre que le script global soit chargé
                console.log('loadThreeJS not available, waiting...');
                let attempts = 0;
                const checkLoadThreeJS = setInterval(function() {
                    attempts++;
                    if (typeof loadThreeJS === 'function') {
                        clearInterval(checkLoadThreeJS);
                        console.log('loadThreeJS found after', attempts, 'attempts');
                        loadThreeJS().then(function() {
                            loadModel();
                        });
                    } else if (attempts > 20) {
                        clearInterval(checkLoadThreeJS);
                        console.error('loadThreeJS not available after 20 attempts');
                        if (loading) {
                            loading.innerHTML = '<p class="text-red-600">Erreur: Three.js n\'est pas disponible. Veuillez recharger la page.</p>';
                        }
                    }
                }, 200);
            }
        }
    };
    
    // Observer les changements dans le DOM pour détecter l'ouverture de la modal Filament
    const observer = new MutationObserver(function(mutations) {
        const container = document.getElementById('threejs-container-' + modalId);
        if (container) {
            const isVisible = container.offsetParent !== null || container.closest('.fi-modal-open') || container.closest('[role="dialog"]');
            if (isVisible && !isInitialized) {
                console.log('Modal detected as visible');
                initThreeJS();
            }
        }
    });
    
    // Observer le body pour détecter l'ouverture de la modal
    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }
    
    // Essayer immédiatement au cas où la modal est déjà ouverte
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initThreeJS, 200);
        });
    } else {
        setTimeout(initThreeJS, 200);
    }
    
    // Également écouter les événements Livewire
    document.addEventListener('livewire:init', function() {
        setTimeout(initThreeJS, 300);
    });
    
    document.addEventListener('livewire:load', function() {
        setTimeout(initThreeJS, 300);
    });
})();

function loadModel3D(containerEl, modalId, modelUrl) {
    console.log('loadModel3D called with:', { containerEl, modalId, modelUrl });
    
    if (!containerEl || !modelUrl) {
        console.error('Missing parameters:', { containerEl: !!containerEl, modelUrl: !!modelUrl });
        return;
    }
    
    const loading = document.getElementById('threejs-loading-' + modalId);
    
    // Vérifier si Three.js est chargé
    if (typeof THREE === 'undefined') {
        console.error('THREE is undefined');
        if (loading) {
            loading.innerHTML = '<p class="text-red-600">Erreur: Three.js n\'est pas chargé. Veuillez recharger la page.</p>';
        }
        return;
    }
    
    console.log('THREE is available, proceeding with model load');
    
    // Initialiser Three.js
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf0f0f0);
    
    const camera = new THREE.PerspectiveCamera(75, containerEl.clientWidth / containerEl.clientHeight, 0.1, 1000);
    camera.position.set(0, 0, 5);
    
    const renderer = new THREE.WebGLRenderer({ antialias: true });
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
    
    let controls = null;
    if (OrbitControlsClass) {
        controls = new OrbitControlsClass(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
    } else {
        if (loading) {
            loading.innerHTML = '<p class="text-red-600">Erreur: OrbitControls n\'est pas chargé. Veuillez recharger la page.</p>';
        }
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
        if (loading) {
            loading.innerHTML = '<p class="text-red-600">Erreur: GLTFLoader n\'est pas chargé. Veuillez recharger la page.</p>';
        }
        console.error('GLTFLoader non trouvé');
        return;
    }
    
    const loader = new GLTFLoaderClass();
    console.log('Starting to load GLB model from:', modelUrl);
    
    if (loading) {
        loading.querySelector('p').textContent = 'Chargement du modèle depuis S3...';
    }
    
    loader.load(
        modelUrl,
        function(gltf) {
            console.log('Model loaded successfully:', gltf);
            const model = gltf.scene;
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
            
            if (loading) {
                loading.style.display = 'none';
            }
            
            console.log('Starting animation loop');
            animate();
        },
        function(xhr) {
            if (xhr.lengthComputable) {
                const percentComplete = (xhr.loaded / xhr.total) * 100;
                console.log('Chargement: ' + percentComplete.toFixed(0) + '%');
                if (loading) {
                    const loadingText = loading.querySelector('p');
                    if (loadingText) {
                        loadingText.textContent = 'Chargement du modèle depuis S3... ' + percentComplete.toFixed(0) + '%';
                    }
                }
            } else {
                console.log('Chargement en cours...', xhr.loaded, 'bytes');
            }
        },
        function(error) {
            console.error('Erreur lors du chargement du modèle:', error);
            console.error('URL utilisée:', modelUrl);
            if (loading) {
                loading.innerHTML = '<p class="text-red-600">Erreur lors du chargement du modèle 3D. URL: ' + modelUrl + '<br>Erreur: ' + (error.message || error) + '</p>';
            }
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
    
    let animationId = null;
    function animate() {
        animationId = requestAnimationFrame(animate);
        if (controls) {
            controls.update();
        }
        if (renderer && scene && camera) {
            renderer.render(scene, camera);
        }
    }
}
</script>

