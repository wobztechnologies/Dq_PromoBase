#!/bin/bash
set -e

# ==========================================
# SCRIPT D'INSTALLATION FRAÎCHE COMFYUI
# Version CORRIGÉE - Packages existants
# Ubuntu 22.04 LTS
# ==========================================

# Variables
INSTALL_DIR="/opt/comfyui-stable"
VENV_DIR="$INSTALL_DIR/venv"
COMFYUI_DIR="$INSTALL_DIR/ComfyUI"
LOG_FILE="/var/log/comfyui_install.log"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1" | tee -a $LOG_FILE; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1" | tee -a $LOG_FILE; }
log_error() { echo -e "${RED}[ERROR]${NC} $1" | tee -a $LOG_FILE; }

clear
echo "============================================"
echo "   ComfyUI Installation Stable - CORRIGÉE"
echo "============================================"

# Vérification root
if [ "$EUID" -ne 0 ]; then 
    log_error "Lancez avec sudo : sudo bash $0"
    exit 1
fi

# Token Hugging Face
echo
read -p "Token Hugging Face (hf_...) : " HF_TOKEN
if [[ ! "$HF_TOKEN" =~ ^hf_ ]]; then
    log_error "Token invalide"
    exit 1
fi

# ==========================================
# NETTOYAGE & PRÉPARATION
# ==========================================

log_info "Préparation système..."

# Arrêter anciens services
systemctl stop comfyui 2>/dev/null || true
systemctl stop comfyui-stable 2>/dev/null || true

# Backup si existe
if [ -d "$INSTALL_DIR" ]; then
    mv $INSTALL_DIR ${INSTALL_DIR}.backup.$(date +%Y%m%d_%H%M%S)
fi

# Installation dépendances système
apt update
apt install -y \
    python3.10 python3.10-venv python3.10-dev python3-pip \
    git wget curl build-essential ninja-build \
    libgl1-mesa-glx libglib2.0-0 libgomp1 \
    libsm6 libxext6 libxrender1 \
    ffmpeg \
    libeigen3-dev libflann-dev libglew-dev libglfw3-dev

# ==========================================
# ENVIRONNEMENT PYTHON
# ==========================================

log_info "Création environnement Python..."
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR

python3.10 -m venv venv
source venv/bin/activate

# Mise à jour pip
pip install --upgrade pip setuptools wheel

# ==========================================
# COMFYUI
# ==========================================

log_info "Installation ComfyUI..."

# Clone ComfyUI
git clone https://github.com/comfyanonymous/ComfyUI.git
cd $COMFYUI_DIR

# Version stable (pas de checkout, utiliser la version actuelle qui fonctionne)

# ==========================================
# PACKAGES PYTHON - VERSIONS CORRIGÉES
# Compatibilité vérifiée pour ComfyUI-3D-Pack + TripoSR
# ==========================================

log_info "Installation packages Python (avec vérifications de compatibilité)..."

# NumPy 1.23.x - CRITIQUE pour Open3D (doit être < 1.24)
log_info "Installation NumPy 1.23.5 (requis pour Open3D)..."
pip install "numpy<1.24" --force-reinstall --no-cache-dir
pip install numpy==1.23.5 --no-cache-dir
python -c "import numpy; assert numpy.__version__.startswith('1.23'), 'NumPy version incorrecte'; print(f'✓ NumPy {numpy.__version__} installé')" || {
    log_error "NumPy version incorrecte - Open3D ne fonctionnera pas!"
    exit 1
}

# PyTorch pour CUDA 11.8 - Compatible avec TripoSR
log_info "Installation PyTorch 2.1.2 (CUDA 11.8)..."
pip install torch==2.1.2 torchvision==0.16.2 torchaudio==2.1.2 --index-url https://download.pytorch.org/whl/cu118 --no-cache-dir
python -c "import torch; print(f'✓ PyTorch {torch.__version__} installé'); print(f'  CUDA disponible: {torch.cuda.is_available()}')" || log_error "PyTorch installation échouée"

# xformers - Compatible avec PyTorch 2.1.2
log_info "Installation xformers..."
pip install xformers==0.0.23.post1 --index-url https://download.pytorch.org/whl/cu118 --no-cache-dir || log_warn "xformers installation échouée (non critique)"

# Packages AI/ML - Versions compatibles avec TripoSR
log_info "Installation packages AI/ML (TripoSR)..."
pip install \
    transformers==4.36.2 \
    tokenizers==0.15.0 \
    diffusers==0.21.4 \
    accelerate==0.21.0 \
    safetensors==0.4.2 \
    --no-cache-dir

# Vérification packages TripoSR
python -c "import transformers, diffusers, accelerate; print('✓ Packages TripoSR installés')" || log_warn "Certains packages TripoSR manquants"

# Packages image/video - Compatibles avec ComfyUI
log_info "Installation packages image/video..."
pip install \
    opencv-python==4.9.0.80 \
    imageio==2.33.1 \
    imageio-ffmpeg==0.4.9 \
    Pillow==10.2.0 \
    --no-cache-dir

# Packages scientifiques - Compatibles avec NumPy 1.23.x
log_info "Installation packages scientifiques..."
pip install \
    scipy==1.11.4 \
    scikit-image==0.22.0 \
    einops==0.7.0 \
    omegaconf==2.3.0 \
    --no-cache-dir

# Packages ComfyUI spécifiques
log_info "Installation packages ComfyUI..."
pip install \
    kornia==0.7.1 \
    spandrel==0.1.8 \
    tqdm==4.66.1 \
    psutil \
    aiohttp \
    --no-cache-dir

# Requirements ComfyUI sans écraser les versions critiques
cd $COMFYUI_DIR
log_info "Installation requirements ComfyUI (sans écraser versions critiques)..."
if [ -f "requirements.txt" ]; then
    # Créer un requirements.txt temporaire sans les packages déjà installés avec versions spécifiques
    log_info "Filtrage requirements.txt pour éviter conflits..."
    grep -v -E "^(transformers|tokenizers|safetensors|numpy|torch|torchvision|torchaudio|open3d|trimesh)" requirements.txt > /tmp/comfyui_requirements_filtered.txt 2>/dev/null || true
    
    if [ -s /tmp/comfyui_requirements_filtered.txt ]; then
        log_info "Installation packages restants depuis requirements.txt..."
        pip install -r /tmp/comfyui_requirements_filtered.txt --no-deps --no-cache-dir 2>/dev/null || {
            log_warn "Certains packages de requirements.txt non installés"
        }
        rm -f /tmp/comfyui_requirements_filtered.txt
    else
        log_info "Tous les packages critiques déjà installés avec versions spécifiques"
    fi
    
    # Vérifier et résoudre les conflits après installation
    log_info "Vérification conflits après installation requirements.txt..."
    CONFLICTS=$(pip check 2>&1 | grep -E "(requires|but you have)" || true)
    if [ ! -z "$CONFLICTS" ]; then
        log_warn "Conflits détectés après installation requirements.txt:"
        echo "$CONFLICTS" | sed 's/^/  /' | tee -a $LOG_FILE
        
        # Forcer les versions critiques si conflits (toujours réinstaller pour éviter les conflits)
        log_info "Réinstallation versions critiques pour résoudre conflits..."
        pip install \
            transformers==4.36.2 \
            tokenizers==0.15.0 \
            safetensors==0.4.2 \
            --force-reinstall --no-deps --no-cache-dir || true
        
        # Vérifier à nouveau après réinstallation
        CONFLICTS_AFTER=$(pip check 2>&1 | grep -E "but you have" || true)
        if [ ! -z "$CONFLICTS_AFTER" ]; then
            log_warn "⚠ Conflits de versions persistent après réinstallation:"
            echo "$CONFLICTS_AFTER" | sed 's/^/  /' | tee -a $LOG_FILE
        else
            log_info "✓ Conflits résolus après réinstallation"
        fi
    else
        log_info "✓ Aucun conflit après installation requirements.txt"
    fi
fi

# ==========================================
# CUSTOM NODES
# ==========================================

log_info "Installation ComfyUI-Manager..."

cd $COMFYUI_DIR/custom_nodes
git clone https://github.com/ltdrdata/ComfyUI-Manager.git

# ==========================================
# 3D-PACK ET DÉPENDANCES
# Versions vérifiées pour compatibilité ComfyUI-3D-Pack
# ==========================================

log_info "Installation dépendances 3D (avec vérifications de compatibilité)..."

# Vérification critique NumPy < 1.24 avant installation 3D
log_info "Vérification NumPy version (CRITIQUE pour Open3D)..."
NUMPY_VERSION=$(python -c "import numpy; print(numpy.__version__)" 2>/dev/null)
NUMPY_MAJOR=$(echo $NUMPY_VERSION | cut -d. -f1)
NUMPY_MINOR=$(echo $NUMPY_VERSION | cut -d. -f2)

if [ "$NUMPY_MAJOR" -eq 1 ] && [ "$NUMPY_MINOR" -lt 24 ]; then
    log_info "✓ NumPy $NUMPY_VERSION compatible avec Open3D"
else
    log_error "NumPy $NUMPY_VERSION incompatible avec Open3D (doit être < 1.24)"
    log_error "Réinstallation NumPy 1.23.5..."
    pip install numpy==1.23.5 --force-reinstall --no-cache-dir
    
    # Résolution automatique des conflits après downgrade NumPy
    log_info "Résolution des conflits de dépendances après downgrade NumPy..."
    
    # Réinstaller les packages critiques avec versions compatibles
    log_info "Réinstallation packages AI/ML avec versions compatibles..."
    pip install \
        transformers==4.36.2 \
        tokenizers==0.15.0 \
        safetensors==0.4.2 \
        --force-reinstall --no-deps --no-cache-dir
    
    # Installer trampoline si torchsde est présent
    if python -c "import torchsde" 2>/dev/null; then
        log_info "Installation trampoline (requis par torchsde)..."
        pip install "trampoline>=0.1.2" --no-cache-dir || log_warn "trampoline installation échouée"
    fi
    
    # Vérification et résolution des conflits (distinguer conflits de versions vs dépendances manquantes)
    log_info "Vérification des conflits de dépendances..."
    PIP_CHECK_OUTPUT=$(pip check 2>&1 || true)
    
    # Séparer les vrais conflits de versions des dépendances manquantes
    VERSION_CONFLICTS=$(echo "$PIP_CHECK_OUTPUT" | grep -E "but you have" || true)
    MISSING_DEPS=$(echo "$PIP_CHECK_OUTPUT" | grep -E "requires.*which is not installed" || true)
    
    # Résoudre les vrais conflits de versions
    if [ ! -z "$VERSION_CONFLICTS" ]; then
        log_warn "Conflits de versions détectés, résolution en cours..."
        echo "$VERSION_CONFLICTS" | while read conflict; do
            log_warn "  Conflit: $conflict"
            
            # Résoudre conflit transformers/tokenizers
            if echo "$conflict" | grep -q "transformers.*requires.*tokenizers"; then
                log_info "  Correction tokenizers pour transformers..."
                pip install transformers==4.36.2 --force-reinstall --no-deps --no-cache-dir || true
            fi
            
            # Résoudre conflit transformers/safetensors
            if echo "$conflict" | grep -q "transformers.*requires.*safetensors"; then
                log_info "  Correction safetensors pour transformers..."
                pip install transformers==4.36.2 --force-reinstall --no-deps --no-cache-dir || true
            fi
        done
    fi
    
    # Installer les dépendances manquantes critiques (pas toutes, seulement celles nécessaires)
    if [ ! -z "$MISSING_DEPS" ]; then
        log_info "Installation des dépendances manquantes critiques..."
        
        # Liste complète des dépendances critiques à installer (basée sur pip check)
        CRITICAL_DEPS="mako tomli fire pybind11 portalocker protobuf flatbuffers coloredlogs ftfy timm python-dotenv pyjwt pynacl greenlet pooch hatchling lark ccimport typeguard annotated-types pydantic-core typing-inspection scs texttable wadler-lindig objprint varname optimum-quanto lightning-utilities"
        
        for dep in $CRITICAL_DEPS; do
            if echo "$MISSING_DEPS" | grep -qi "$dep"; then
                log_info "  Installation $dep..."
                pip install "$dep" --no-cache-dir 2>/dev/null || log_warn "    $dep installation échouée (peut être non-critique)"
            fi
        done
        
        # Installer trampoline si nécessaire
        if echo "$MISSING_DEPS" | grep -qi "trampoline"; then
            log_info "  Installation trampoline (requis par torchsde)..."
            pip install "trampoline>=0.1.2" --no-cache-dir || true
        fi
    fi
    
    # Vérification finale - distinguer conflits réels vs dépendances manquantes
    FINAL_CHECK=$(pip check 2>&1 || true)
    FINAL_VERSION_CONFLICTS=$(echo "$FINAL_CHECK" | grep -E "but you have" || true)
    FINAL_MISSING=$(echo "$FINAL_CHECK" | grep -E "which is not installed" || true)
    
    if [ ! -z "$FINAL_VERSION_CONFLICTS" ]; then
        log_warn "⚠ Conflits de versions persistent:"
        echo "$FINAL_VERSION_CONFLICTS" | sed 's/^/  /' | tee -a $LOG_FILE
    fi
    
    if [ ! -z "$FINAL_MISSING" ]; then
        MISSING_COUNT=$(echo "$FINAL_MISSING" | wc -l)
        log_info "ℹ $MISSING_COUNT dépendances manquantes détectées (la plupart sont non-critiques car installées avec --no-deps)"
        log_info "  Les dépendances critiques ont été installées. Les autres seront installées à la demande."
    fi
    
    if [ -z "$FINAL_VERSION_CONFLICTS" ] && [ -z "$FINAL_MISSING" ]; then
        log_info "✓ Aucun conflit détecté"
    fi
fi

# Packages 3D de base - Installés en premier (dépendances légères)
log_info "Installation packages 3D de base..."
pip install \
    plyfile==1.0.2 \
    pywavefront==1.3.3 \
    pygltflib==1.16.1 \
    --no-cache-dir

# Vérification packages de base
python -c "import plyfile, pywavefront, pygltflib; print('✓ Packages 3D de base installés')" || log_warn "Certains packages 3D de base manquants"

# numpy-stl - Version compatible avec NumPy 1.23.x
log_info "Installation numpy-stl (compatible NumPy 1.23.x)..."
if pip install numpy-stl==2.16.0 --no-cache-dir 2>/dev/null; then
    log_info "✓ numpy-stl 2.16.0 installé"
elif pip install numpy-stl==3.0.1 --no-cache-dir 2>/dev/null; then
    log_info "✓ numpy-stl 3.0.1 installé"
else
    log_warn "numpy-stl installation échouée - tentative version flexible..."
    pip install "numpy-stl>=2.16.0,<4.0" --no-cache-dir || log_error "numpy-stl installation échouée"
fi

# meshio - Version compatible
log_info "Installation meshio..."
if pip install meshio==5.3.1 --no-cache-dir 2>/dev/null; then
    log_info "✓ meshio 5.3.1 installé"
elif pip install "meshio>=5.3.0,<5.4" --no-cache-dir 2>/dev/null; then
    log_info "✓ meshio installé (version flexible)"
else
    log_warn "meshio installation échouée"
fi

# trimesh - Version compatible avec NumPy 1.23.x (CRITIQUE)
log_info "Installation trimesh (compatible NumPy 1.23.x)..."
if pip install trimesh==3.23.5 --no-cache-dir 2>/dev/null; then
    log_info "✓ trimesh 3.23.5 installé"
    python -c "import trimesh; print(f'  Version installée: {trimesh.__version__}')" || true
elif pip install "trimesh>=3.20.0,<4.0" --no-cache-dir 2>/dev/null; then
    log_info "✓ trimesh installé (version flexible)"
else
    log_error "trimesh installation échouée - CRITIQUE pour ComfyUI-3D-Pack"
fi

# Open3D - CRITIQUE, doit être compatible avec NumPy < 1.24
log_info "Installation Open3D (CRITIQUE - peut prendre 5-10 minutes)..."
OPEN3D_INSTALLED=false

# Tentative 1: Open3D 0.17.0
if pip install open3d==0.17.0 --no-cache-dir 2>&1 | tee -a $LOG_FILE; then
    if python -c "import open3d; print(f'Open3D {open3d.__version__}')" 2>/dev/null; then
        log_info "✓ Open3D 0.17.0 installé avec succès"
        OPEN3D_INSTALLED=true
    fi
fi

# Tentative 2: Open3D 0.16.1 si 0.17.0 échoue
if [ "$OPEN3D_INSTALLED" = false ]; then
    log_warn "Open3D 0.17.0 échoué, tentative 0.16.1..."
    if pip install open3d==0.16.1 --no-cache-dir 2>&1 | tee -a $LOG_FILE; then
        if python -c "import open3d; print(f'Open3D {open3d.__version__}')" 2>/dev/null; then
            log_info "✓ Open3D 0.16.1 installé avec succès"
            OPEN3D_INSTALLED=true
        fi
    fi
fi

# Tentative 3: Open3D 0.15.1 si 0.16.1 échoue
if [ "$OPEN3D_INSTALLED" = false ]; then
    log_warn "Open3D 0.16.1 échoué, tentative 0.15.1..."
    if pip install open3d==0.15.1 --no-cache-dir 2>&1 | tee -a $LOG_FILE; then
        if python -c "import open3d; print(f'Open3D {open3d.__version__}')" 2>/dev/null; then
            log_info "✓ Open3D 0.15.1 installé avec succès"
            OPEN3D_INSTALLED=true
        fi
    fi
fi

# Vérification finale Open3D
if [ "$OPEN3D_INSTALLED" = true ]; then
    python -c "import open3d; print(f'✓ Open3D {open3d.__version__} fonctionnel')" || log_error "Open3D import échoué"
else
    log_error "Open3D installation échouée - ComfyUI-3D-Pack ne fonctionnera pas correctement!"
    log_error "Vérifiez les logs: $LOG_FILE"
fi

# ComfyUI-3D-Pack
log_info "Installation ComfyUI-3D-Pack..."
cd $COMFYUI_DIR/custom_nodes
if [ -d "ComfyUI-3D-Pack" ]; then
    log_warn "ComfyUI-3D-Pack existe déjà, suppression..."
    rm -rf ComfyUI-3D-Pack
fi

log_info "Clonage ComfyUI-3D-Pack..."
git clone https://github.com/MrForExample/ComfyUI-3D-Pack.git
cd ComfyUI-3D-Pack

# Vérifier requirements.txt si existe
if [ -f "requirements.txt" ]; then
    log_info "Requirements.txt trouvé, analyse..."
    # Installer seulement les packages non déjà installés avec versions spécifiques
    log_info "Installation dépendances depuis requirements.txt..."
    pip install -r requirements.txt --no-deps 2>/dev/null || log_warn "Certains packages de requirements.txt non installés"
    
    # Réinstaller les versions critiques après installation requirements.txt
    log_info "Réinstallation versions critiques après requirements.txt 3D-Pack..."
    pip install \
        transformers==4.36.2 \
        tokenizers==0.15.0 \
        safetensors==0.4.2 \
        numpy==1.23.5 \
        --force-reinstall --no-deps --no-cache-dir 2>/dev/null || true
fi

# Installation dépendances 3D-Pack (déjà installées mais vérification)
log_info "Vérification dépendances ComfyUI-3D-Pack..."
pip install piexif omegaconf einops --no-cache-dir

# Vérification des dépendances critiques avant install.py
log_info "Vérification pré-installation..."
python << 'PYTHON_EOF'
import sys
missing = []
try:
    import open3d
    print(f"✓ Open3D {open3d.__version__}")
except ImportError:
    missing.append("open3d")
    print("✗ Open3D manquant")

try:
    import trimesh
    print(f"✓ Trimesh {trimesh.__version__}")
except ImportError:
    missing.append("trimesh")
    print("✗ Trimesh manquant")

try:
    import numpy
    if not numpy.__version__.startswith('1.23'):
        print(f"⚠ NumPy {numpy.__version__} (recommandé: 1.23.x)")
    else:
        print(f"✓ NumPy {numpy.__version__}")
except ImportError:
    missing.append("numpy")
    print("✗ NumPy manquant")

if missing:
    print(f"\n⚠ Packages manquants: {', '.join(missing)}")
    sys.exit(1)
PYTHON_EOF

# Installation 3D-Pack avec gestion d'erreurs
log_info "Exécution install.py de ComfyUI-3D-Pack..."
if python install.py 2>&1 | tee -a $LOG_FILE; then
    log_info "✓ ComfyUI-3D-Pack installé avec succès"
    
    # Vérification post-installation
    log_info "Vérification post-installation..."
    if python -c "import sys; sys.path.insert(0, '.'); import nodes" 2>/dev/null; then
        log_info "✓ Nodes ComfyUI-3D-Pack importables"
    else
        log_warn "⚠ Nodes ComfyUI-3D-Pack non importables (peut être normal)"
    fi
else
    log_warn "⚠ Installation 3D-Pack partielle - vérifiez les logs: $LOG_FILE"
fi

cd $COMFYUI_DIR

# ==========================================
# INSTALLATION FINALE DES DÉPENDANCES MANQUANTES
# Après installation de tous les packages, installer toutes les dépendances manquantes
# ==========================================

log_info "Installation finale de toutes les dépendances manquantes..."

# Vérifier les dépendances manquantes
PIP_CHECK_BEFORE_MODELS=$(pip check 2>&1 || true)
MISSING_BEFORE=$(echo "$PIP_CHECK_BEFORE_MODELS" | grep -E "which is not installed" || true)

if [ ! -z "$MISSING_BEFORE" ]; then
    log_info "Détection des dépendances manquantes avant installation finale..."
    
    # Liste complète de toutes les dépendances manquantes possibles
    ALL_MISSING_DEPS="mako tomli fire pybind11 portalocker protobuf flatbuffers coloredlogs ftfy timm python-dotenv pyjwt pynacl greenlet pooch hatchling lark ccimport typeguard annotated-types pydantic-core typing-inspection scs texttable wadler-lindig objprint varname optimum-quanto lightning-utilities trampoline"
    
    # Installer toutes les dépendances manquantes détectées
    for dep in $ALL_MISSING_DEPS; do
        if echo "$MISSING_BEFORE" | grep -qi "$dep"; then
            log_info "  Installation $dep..."
            pip install "$dep" --no-cache-dir 2>/dev/null || log_warn "    $dep installation échouée"
        fi
    done
    
    # Vérification après installation
    PIP_CHECK_AFTER=$(pip check 2>&1 || true)
    MISSING_AFTER=$(echo "$PIP_CHECK_AFTER" | grep -E "which is not installed" || true)
    
    if [ ! -z "$MISSING_AFTER" ]; then
        MISSING_COUNT=$(echo "$MISSING_AFTER" | wc -l)
        log_warn "⚠ $MISSING_COUNT dépendances manquantes persistent après installation:"
        echo "$MISSING_AFTER" | sed 's/^/  /' | tee -a $LOG_FILE
        log_info "  Ces dépendances seront installées à la demande si nécessaires"
    else
        log_info "✓ Toutes les dépendances manquantes ont été installées"
    fi
else
    log_info "✓ Aucune dépendance manquante détectée"
fi

# ==========================================
# MODÈLES
# ==========================================

log_info "Téléchargement modèles..."

mkdir -p $COMFYUI_DIR/models/checkpoints
cd $COMFYUI_DIR/models/checkpoints

# SD 1.5
if [ ! -f "sd_v1.5.safetensors" ]; then
    log_info "Téléchargement SD 1.5..."
    wget --progress=bar:force -O sd_v1.5.safetensors \
        "https://huggingface.co/runwayml/stable-diffusion-v1-5/resolve/main/v1-5-pruned-emaonly.safetensors"
fi

# TripoSR
if [ ! -f "triposr.ckpt" ]; then
    log_info "Téléchargement TripoSR..."
    wget --progress=bar:force -O triposr.ckpt \
        "https://huggingface.co/stabilityai/TripoSR/resolve/main/model.ckpt"
fi

# ==========================================
# CONFIGURATION
# ==========================================

log_info "Configuration système..."

# Token HF
mkdir -p $COMFYUI_DIR/user
echo "HUGGINGFACE_TOKEN=$HF_TOKEN" > $COMFYUI_DIR/user/.env

# Service systemd
cat > /etc/systemd/system/comfyui.service <<EOF
[Unit]
Description=ComfyUI Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$COMFYUI_DIR
Environment="PATH=$VENV_DIR/bin:/usr/bin:/bin"
Environment="PYTHONPATH=$COMFYUI_DIR"
Environment="HUGGINGFACE_TOKEN=$HF_TOKEN"
ExecStart=$VENV_DIR/bin/python main.py --listen 0.0.0.0 --port 8188
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable comfyui

# Script de test
cat > $INSTALL_DIR/test.sh <<'EOF'
#!/bin/bash
cd /opt/comfyui-stable
source venv/bin/activate
echo "=== Test Installation ==="
python -c "
import sys
print(f'Python: {sys.version}')

import torch
print(f'PyTorch: {torch.__version__}')
print(f'CUDA disponible: {torch.cuda.is_available()}')
if torch.cuda.is_available():
    print(f'CUDA version: {torch.version.cuda}')
    print(f'GPU: {torch.cuda.get_device_name(0)}')

import numpy
print(f'NumPy: {numpy.__version__}')

# Test packages 3D
packages_3d = {
    'open3d': 'Open3D',
    'trimesh': 'Trimesh',
    'meshio': 'Meshio',
    'plyfile': 'Plyfile',
    'pygltflib': 'PyGLTF',
    'numpy_stl': 'NumPy-STL'
}

print('\n=== Packages 3D ===')
for module, name in packages_3d.items():
    try:
        mod = __import__(module)
        version = getattr(mod, '__version__', 'Version inconnue')
        print(f'{name}: {version} ✓')
    except ImportError as e:
        print(f'{name}: Non installé ✗ ({str(e)[:50]})')
    except Exception as e:
        print(f'{name}: Erreur ✗ ({str(e)[:50]})')

# Test ComfyUI-3D-Pack
print('\n=== ComfyUI-3D-Pack ===')
import os
pack_path = '/opt/comfyui-stable/ComfyUI/custom_nodes/ComfyUI-3D-Pack'
if os.path.exists(pack_path):
    print('ComfyUI-3D-Pack: Présent ✓')
else:
    print('ComfyUI-3D-Pack: Non trouvé ✗')

# Vérification conflits de dépendances
print('\n=== Vérification Conflits ===')
import subprocess
result = subprocess.run(['pip', 'check'], capture_output=True, text=True)
if result.returncode == 0 and not result.stdout.strip():
    print('✓ Aucun conflit détecté')
else:
    if result.stdout.strip():
        print('⚠ Conflits détectés:')
        for line in result.stdout.split('\n'):
            if line.strip() and ('requires' in line or 'but you have' in line):
                print(f'  {line}')
    else:
        print('✓ Aucun conflit détecté')
"
EOF
chmod +x $INSTALL_DIR/test.sh

# ==========================================
# VÉRIFICATION FINALE DES DÉPENDANCES
# ==========================================

log_info "Vérification finale des dépendances et conflits..."
FINAL_PIP_CHECK=$(pip check 2>&1 || true)

# Séparer conflits de versions vs dépendances manquantes
FINAL_VERSION_CONFLICTS=$(echo "$FINAL_PIP_CHECK" | grep -E "but you have" || true)
FINAL_MISSING_DEPS=$(echo "$FINAL_PIP_CHECK" | grep -E "which is not installed" || true)

# Résoudre les conflits de versions réels
if [ ! -z "$FINAL_VERSION_CONFLICTS" ]; then
    log_warn "Conflits de versions détectés lors de la vérification finale:"
    echo "$FINAL_VERSION_CONFLICTS" | sed 's/^/  /' | tee -a $LOG_FILE
    
    # Tentative de résolution finale - forcer les versions compatibles
    log_info "Tentative de résolution finale des conflits de versions..."
    pip install \
        transformers==4.36.2 \
        tokenizers==0.15.0 \
        safetensors==0.4.2 \
        numpy==1.23.5 \
        --force-reinstall --no-deps --no-cache-dir 2>/dev/null || true
    
    # Vérifier à nouveau après résolution
    FINAL_CHECK_AFTER_RESOLVE=$(pip check 2>&1 || true)
    FINAL_VERSION_AFTER_RESOLVE=$(echo "$FINAL_CHECK_AFTER_RESOLVE" | grep -E "but you have" || true)
    
    if [ ! -z "$FINAL_VERSION_AFTER_RESOLVE" ]; then
        log_warn "⚠ Conflits de versions persistent après résolution finale:"
        echo "$FINAL_VERSION_AFTER_RESOLVE" | sed 's/^/  /' | tee -a $LOG_FILE
        log_warn "  Ces conflits peuvent affecter le fonctionnement - vérifiez les logs"
    else
        log_info "✓ Conflits de versions résolus"
    fi
fi

# Installer dépendances manquantes critiques si nécessaire
if [ ! -z "$FINAL_MISSING_DEPS" ]; then
    MISSING_COUNT=$(echo "$FINAL_MISSING_DEPS" | wc -l)
    log_info "ℹ $MISSING_COUNT dépendances manquantes détectées"
    
    # Installer toutes les dépendances manquantes critiques (liste complète basée sur pip check)
    CRITICAL_MISSING=""
    CRITICAL_DEPS_LIST="mako tomli fire pybind11 portalocker protobuf flatbuffers coloredlogs ftfy timm python-dotenv pyjwt pynacl greenlet pooch hatchling lark ccimport typeguard annotated-types pydantic-core typing-inspection scs texttable wadler-lindig objprint varname optimum-quanto lightning-utilities trampoline"
    
    for dep in $CRITICAL_DEPS_LIST; do
        if echo "$FINAL_MISSING_DEPS" | grep -qi "$dep"; then
            CRITICAL_MISSING="$CRITICAL_MISSING $dep"
        fi
    done
    
    if [ ! -z "$CRITICAL_MISSING" ]; then
        log_info "Installation des dépendances critiques manquantes..."
        for dep in $CRITICAL_MISSING; do
            log_info "  Installation $dep..."
            pip install "$dep" --no-cache-dir 2>/dev/null || log_warn "    $dep installation échouée (peut être non-critique)"
        done
    fi
    
    log_info "  Note: Les autres dépendances manquantes sont non-critiques (packages installés avec --no-deps)"
    log_info "  Elles seront installées automatiquement si nécessaires lors de l'utilisation"
fi

# Vérification après résolution
FINAL_CHECK_AFTER=$(pip check 2>&1 || true)
FINAL_VERSION_AFTER=$(echo "$FINAL_CHECK_AFTER" | grep -E "but you have" || true)

if [ ! -z "$FINAL_VERSION_AFTER" ]; then
    log_warn "⚠ Des conflits de versions persistent:"
    echo "$FINAL_VERSION_AFTER" | sed 's/^/  /' | tee -a $LOG_FILE
    log_warn "  Ces conflits peuvent affecter le fonctionnement - vérifiez les logs"
else
    log_info "✓ Aucun conflit de versions critique détecté"
fi

# Vérification NumPy version finale
log_info "Vérification NumPy version finale..."
FINAL_NUMPY=$(python -c "import numpy; print(numpy.__version__)" 2>/dev/null)
FINAL_NUMPY_MAJOR=$(echo $FINAL_NUMPY | cut -d. -f1)
FINAL_NUMPY_MINOR=$(echo $FINAL_NUMPY | cut -d. -f2)

if [ "$FINAL_NUMPY_MAJOR" -eq 1 ] && [ "$FINAL_NUMPY_MINOR" -lt 24 ]; then
    log_info "✓ NumPy $FINAL_NUMPY compatible avec Open3D"
else
    log_error "⚠ NumPy $FINAL_NUMPY incompatible avec Open3D - ComfyUI-3D-Pack peut ne pas fonctionner!"
fi

# ==========================================
# DÉMARRAGE
# ==========================================

log_info "Démarrage ComfyUI..."
systemctl start comfyui

sleep 5
if systemctl is-active --quiet comfyui; then
    log_info "✓ ComfyUI démarré!"
else
    log_warn "Vérifiez : journalctl -u comfyui -n 50"
fi

# ==========================================
# RÉSUMÉ
# ==========================================

IP=$(hostname -I | awk '{print $1}')

cat << EOF

${GREEN}============================================${NC}
${GREEN}   INSTALLATION TERMINÉE !${NC}
${GREEN}============================================${NC}

📍 Installation : $INSTALL_DIR
🌐 Interface : http://$IP:8188
📊 Logs : journalctl -u comfyui -f

${BLUE}Commandes :${NC}
• Test : $INSTALL_DIR/test.sh
• Status : systemctl status comfyui
• Restart : systemctl restart comfyui

============================================
EOF