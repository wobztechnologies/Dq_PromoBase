#!/bin/bash
# Script de vérification complète des dépendances pour ComfyUI-3D-Pack et TripoSR

set -e

INSTALL_DIR="/opt/comfyui-stable"
VENV_DIR="$INSTALL_DIR/venv"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

check_package() {
    local package=$1
    local required_version=$2
    local compatible_versions=$3
    
    echo -e "\n${BLUE}=== Vérification: $package ===${NC}"
    
    if [ -d "$VENV_DIR" ]; then
        source $VENV_DIR/bin/activate
        
        # Vérifier si le package est installé
        if python -c "import $package" 2>/dev/null; then
            local installed_version=$(python -c "import $package; print($package.__version__)" 2>/dev/null || echo "inconnue")
            echo -e "${GREEN}✓ Installé: $installed_version${NC}"
            
            # Vérifier compatibilité avec versions requises
            if [ ! -z "$required_version" ]; then
                echo "  Requis: $required_version"
                if [ "$installed_version" != "$required_version" ]; then
                    echo -e "${YELLOW}  ⚠ Version différente de celle requise${NC}"
                fi
            fi
            
            if [ ! -z "$compatible_versions" ]; then
                echo "  Versions compatibles: $compatible_versions"
            fi
            
            # Test d'importation fonctionnel
            if python -c "import $package; print('Import OK')" 2>/dev/null; then
                echo -e "${GREEN}  ✓ Import fonctionnel${NC}"
            else
                echo -e "${RED}  ✗ Erreur d'import${NC}"
            fi
        else
            echo -e "${RED}✗ Non installé${NC}"
        fi
    else
        echo -e "${RED}✗ Environnement virtuel non trouvé${NC}"
    fi
}

echo "============================================"
echo "   VÉRIFICATION COMPLÈTE DES DÉPENDANCES"
echo "   ComfyUI-3D-Pack + TripoSR"
echo "============================================"

# Matrice de compatibilité des dépendances critiques
declare -A DEPENDENCIES=(
    # Package:Version_requise:Versions_compatibles
    ["numpy"]="1.23.5:1.23.0-1.23.5"
    ["torch"]="2.1.2:2.1.0-2.1.2"
    ["torchvision"]="0.16.2:0.16.0-0.16.2"
    ["open3d"]="0.17.0:0.15.1-0.17.0"
    ["trimesh"]="3.23.5:3.20.0-3.23.5"
    ["meshio"]="5.3.1:5.3.0-5.3.4"
    ["numpy_stl"]="2.16.0:2.16.0-3.0.1"
    ["plyfile"]="1.0.2:1.0.0-1.0.2"
    ["pygltflib"]="1.16.1:1.15.0-1.16.1"
    ["pywavefront"]="1.3.3:1.3.0-1.3.3"
    ["transformers"]="4.36.2:4.30.0-4.36.2"
    ["diffusers"]="0.21.4:0.20.0-0.21.4"
    ["accelerate"]="0.21.0:0.20.0-0.21.0"
    ["safetensors"]="0.4.2:0.3.0-0.4.2"
    ["opencv-python"]="4.9.0.80:4.8.0-4.9.0"
    ["Pillow"]="10.2.0:10.0.0-10.2.0"
    ["scipy"]="1.11.4:1.10.0-1.11.4"
    ["scikit-image"]="0.22.0:0.20.0-0.22.0"
    ["einops"]="0.7.0:0.6.0-0.7.0"
    ["omegaconf"]="2.3.0:2.2.0-2.3.0"
    ["kornia"]="0.7.1:0.7.0-0.7.1"
    ["xformers"]="0.0.23.post1:0.0.22-0.0.23"
)

# Vérifier chaque dépendance
for package in "${!DEPENDENCIES[@]}"; do
    IFS=':' read -r required compatible <<< "${DEPENDENCIES[$package]}"
    check_package "$package" "$required" "$compatible"
done

# Vérifications spécifiques TripoSR
echo -e "\n${BLUE}=== Vérifications spécifiques TripoSR ===${NC}"
source $VENV_DIR/bin/activate 2>/dev/null || true

# Vérifier CUDA
if python -c "import torch; print('CUDA:', torch.cuda.is_available())" 2>/dev/null; then
    CUDA_AVAILABLE=$(python -c "import torch; print(torch.cuda.is_available())" 2>/dev/null)
    if [ "$CUDA_AVAILABLE" = "True" ]; then
        echo -e "${GREEN}✓ CUDA disponible${NC}"
        python -c "import torch; print('  Version CUDA:', torch.version.cuda); print('  GPU:', torch.cuda.get_device_name(0))" 2>/dev/null
    else
        echo -e "${YELLOW}⚠ CUDA non disponible${NC}"
    fi
fi

# Vérifier NumPy < 1.24 (critique pour Open3D)
NUMPY_VERSION=$(python -c "import numpy; print(numpy.__version__)" 2>/dev/null || echo "0.0.0")
NUMPY_MAJOR=$(echo $NUMPY_VERSION | cut -d. -f1)
NUMPY_MINOR=$(echo $NUMPY_VERSION | cut -d. -f2)

if [ "$NUMPY_MAJOR" -eq 1 ] && [ "$NUMPY_MINOR" -lt 24 ]; then
    echo -e "${GREEN}✓ NumPy version compatible avec Open3D (< 1.24)${NC}"
else
    echo -e "${RED}✗ NumPy version incompatible avec Open3D (doit être < 1.24)${NC}"
fi

# Vérifier ComfyUI-3D-Pack
echo -e "\n${BLUE}=== Vérification ComfyUI-3D-Pack ===${NC}"
PACK_PATH="$INSTALL_DIR/ComfyUI/custom_nodes/ComfyUI-3D-Pack"
if [ -d "$PACK_PATH" ]; then
    echo -e "${GREEN}✓ ComfyUI-3D-Pack présent${NC}"
    
    # Vérifier requirements.txt si existe
    if [ -f "$PACK_PATH/requirements.txt" ]; then
        echo "  Requirements.txt trouvé"
        echo "  Contenu:"
        head -20 "$PACK_PATH/requirements.txt" | sed 's/^/    /'
    fi
    
    # Vérifier install.py
    if [ -f "$PACK_PATH/install.py" ]; then
        echo -e "${GREEN}  ✓ install.py présent${NC}"
    fi
else
    echo -e "${RED}✗ ComfyUI-3D-Pack non trouvé${NC}"
fi

# Test d'importation combinée
echo -e "\n${BLUE}=== Test d'importation combinée ===${NC}"
python << 'PYTHON_EOF'
import sys
errors = []

# Test packages critiques ensemble
try:
    import numpy
    import torch
    import open3d
    import trimesh
    print("✓ Packages 3D de base: OK")
except Exception as e:
    errors.append(f"Packages 3D de base: {str(e)}")
    print(f"✗ Erreur: {e}")

# Test TripoSR dependencies
try:
    import transformers
    import diffusers
    import accelerate
    print("✓ Packages TripoSR: OK")
except Exception as e:
    errors.append(f"Packages TripoSR: {str(e)}")
    print(f"✗ Erreur: {e}")

# Test ComfyUI-3D-Pack imports
try:
    import plyfile
    import pygltflib
    import meshio
    print("✓ Packages ComfyUI-3D-Pack: OK")
except Exception as e:
    errors.append(f"Packages ComfyUI-3D-Pack: {str(e)}")
    print(f"✗ Erreur: {e}")

if errors:
    print(f"\n⚠ {len(errors)} erreur(s) détectée(s)")
    sys.exit(1)
else:
    print("\n✓ Tous les tests d'importation réussis")
PYTHON_EOF

echo -e "\n${GREEN}============================================${NC}"
echo -e "${GREEN}   VÉRIFICATION TERMINÉE${NC}"
echo -e "${GREEN}============================================${NC}"

