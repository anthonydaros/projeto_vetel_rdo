#!/bin/bash
#
# Script para sincronizar fotos locais com container Docker
# Útil para atualizar fotos rapidamente sem rebuild da imagem
#
# Uso: ./scripts/sync_photos_to_docker.sh [container_name_or_id]
#

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get container name/id from argument or try to find it
if [ -n "$1" ]; then
    CONTAINER="$1"
else
    echo -e "${YELLOW}Procurando container do projeto Vetel...${NC}"
    CONTAINER=$(docker ps --format "table {{.Names}}\t{{.Image}}" | grep -E "vetel|rdo" | head -1 | awk '{print $1}')

    if [ -z "$CONTAINER" ]; then
        echo -e "${RED}Erro: Não foi possível encontrar o container. Por favor, forneça o nome ou ID como argumento.${NC}"
        echo "Uso: $0 [container_name_or_id]"
        echo ""
        echo "Containers em execução:"
        docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}"
        exit 1
    fi

    echo -e "${GREEN}Container encontrado: $CONTAINER${NC}"
fi

# Check if container exists and is running
if ! docker ps --format "{{.Names}}" | grep -q "^${CONTAINER}$"; then
    echo -e "${RED}Erro: Container '$CONTAINER' não está em execução.${NC}"
    exit 1
fi

# Source directory (local)
SOURCE_DIR="./img/album"

# Destination directory (in container)
DEST_DIR="/var/www/html/img/album"

# Check if source directory exists
if [ ! -d "$SOURCE_DIR" ]; then
    echo -e "${RED}Erro: Diretório de origem '$SOURCE_DIR' não existe.${NC}"
    exit 1
fi

# Count photos before sync
echo -e "${YELLOW}Contando fotos no container antes da sincronização...${NC}"
BEFORE_COUNT=$(docker exec "$CONTAINER" find "$DEST_DIR" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
echo "Fotos no container antes: $BEFORE_COUNT"

# Count local photos
LOCAL_COUNT=$(find "$SOURCE_DIR" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
echo "Fotos locais disponíveis: $LOCAL_COUNT"

# Sync photos to container
echo -e "${YELLOW}Sincronizando fotos para o container...${NC}"

# Create destination directory if it doesn't exist
docker exec "$CONTAINER" mkdir -p "$DEST_DIR"

# Copy all photos to container
# Using docker cp to copy the entire directory contents
docker cp "$SOURCE_DIR/." "$CONTAINER:$DEST_DIR/"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}Sincronização concluída com sucesso!${NC}"
else
    echo -e "${RED}Erro durante a sincronização.${NC}"
    exit 1
fi

# Count photos after sync
AFTER_COUNT=$(docker exec "$CONTAINER" find "$DEST_DIR" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
NEW_FILES=$((AFTER_COUNT - BEFORE_COUNT))

echo "Fotos no container depois: $AFTER_COUNT"
echo -e "${GREEN}Novas fotos sincronizadas: $NEW_FILES${NC}"

# Fix permissions
echo -e "${YELLOW}Ajustando permissões...${NC}"
docker exec "$CONTAINER" chown -R www-data:www-data "$DEST_DIR"
docker exec "$CONTAINER" chmod -R 755 "$DEST_DIR"

echo -e "${GREEN}✅ Sincronização completa!${NC}"

# Optional: Test a specific photo
if [ -n "$2" ]; then
    TEST_FILE="$2"
    echo ""
    echo -e "${YELLOW}Testando arquivo específico: $TEST_FILE${NC}"
    if docker exec "$CONTAINER" test -f "$DEST_DIR/$TEST_FILE"; then
        echo -e "${GREEN}✅ Arquivo '$TEST_FILE' existe no container${NC}"
    else
        echo -e "${RED}❌ Arquivo '$TEST_FILE' NÃO existe no container${NC}"
    fi
fi

echo ""
echo "Dica: Para testar uma foto específica, use:"
echo "  $0 $CONTAINER diario-524-foto-0.jpg"