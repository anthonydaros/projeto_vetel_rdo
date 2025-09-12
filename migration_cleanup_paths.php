<?php
/**
 * Migração em lote para limpar paths existentes no banco de dados
 * Converte paths completos para apenas nomes de arquivos
 */

require_once __DIR__ . '/startup.php';

echo "=== MIGRAÇÃO: LIMPEZA DE PATHS NO BANCO DE DADOS ===\n\n";

try {
    // Iniciar transação para segurança
    $pdo->beginTransaction();
    
    echo "1. LIMPANDO TABELA IMAGEM...\n";
    
    // Buscar todas as imagens com paths completos
    $stmt = $pdo->query("SELECT id_imagem, url FROM imagem WHERE url LIKE '/%'");
    $images = $stmt->fetchAll();
    
    echo "   Encontradas " . count($images) . " imagens com paths completos\n";
    
    $updatedImages = 0;
    foreach ($images as $image) {
        $originalUrl = $image['url'];
        $fileName = basename($originalUrl);
        
        // Atualizar apenas se o filename for diferente da URL original
        if ($fileName !== $originalUrl) {
            $updateStmt = $pdo->prepare("UPDATE imagem SET url = ? WHERE id_imagem = ?");
            $updateStmt->execute([$fileName, $image['id_imagem']]);
            $updatedImages++;
            
            if ($updatedImages <= 5) {
                echo "   ✓ $originalUrl → $fileName\n";
            } elseif ($updatedImages == 6) {
                echo "   ... (mostrando apenas os primeiros 5)\n";
            }
        }
    }
    
    echo "   Total de imagens atualizadas: $updatedImages\n\n";
    
    echo "2. LIMPANDO TABELA EMPRESA (URL_LOGO)...\n";
    
    // Buscar todas as empresas com logos que tenham paths completos
    $stmt = $pdo->query("SELECT id_empresa, nome_fantasia, url_logo FROM empresa WHERE url_logo IS NOT NULL AND url_logo != '' AND url_logo LIKE '/%'");
    $companies = $stmt->fetchAll();
    
    echo "   Encontradas " . count($companies) . " empresas com logos com paths completos\n";
    
    $updatedCompanies = 0;
    foreach ($companies as $company) {
        $originalUrl = $company['url_logo'];
        $fileName = basename($originalUrl);
        
        // Atualizar apenas se o filename for diferente da URL original
        if ($fileName !== $originalUrl) {
            $updateStmt = $pdo->prepare("UPDATE empresa SET url_logo = ? WHERE id_empresa = ?");
            $updateStmt->execute([$fileName, $company['id_empresa']]);
            $updatedCompanies++;
            
            echo "   ✓ {$company['nome_fantasia']}: $originalUrl → $fileName\n";
        }
    }
    
    echo "   Total de empresas atualizadas: $updatedCompanies\n\n";
    
    echo "3. VERIFICANDO RESULTADOS...\n";
    
    // Verificar se ainda há paths completos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM imagem WHERE url LIKE '/%'");
    $remainingImages = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM empresa WHERE url_logo LIKE '/%'");
    $remainingLogos = $stmt->fetch()['count'];
    
    echo "   Imagens com paths completos restantes: $remainingImages\n";
    echo "   Logos com paths completos restantes: $remainingLogos\n\n";
    
    if ($remainingImages == 0 && $remainingLogos == 0) {
        echo "✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!\n";
        echo "   Todos os paths foram convertidos para nomes de arquivos.\n\n";
        
        // Commit da transação
        $pdo->commit();
        
        echo "4. EXEMPLOS DOS DADOS ATUALIZADOS:\n";
        
        // Mostrar alguns exemplos
        $stmt = $pdo->query("SELECT url FROM imagem LIMIT 5");
        $examples = $stmt->fetchAll();
        echo "   Imagens (primeiras 5):\n";
        foreach ($examples as $example) {
            echo "   - {$example['url']}\n";
        }
        
        echo "\n   Logos de empresas:\n";
        $stmt = $pdo->query("SELECT nome_fantasia, url_logo FROM empresa WHERE url_logo IS NOT NULL AND url_logo != '' LIMIT 5");
        $logoExamples = $stmt->fetchAll();
        foreach ($logoExamples as $example) {
            echo "   - {$example['nome_fantasia']}: {$example['url_logo']}\n";
        }
        
    } else {
        echo "⚠️  ATENÇÃO: Ainda há registros com paths completos.\n";
        echo "   Rollback da transação por segurança.\n";
        $pdo->rollback();
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "❌ ERRO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
    echo "   Todas as alterações foram desfeitas.\n";
}

echo "\n=== FIM DA MIGRAÇÃO ===\n";