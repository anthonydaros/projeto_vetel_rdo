# Como Iniciar o Servidor PHP

## Servidor de Desenvolvimento com Limites Aumentados

Para permitir upload de arquivos até 10MB, use uma das opções abaixo:

### Opção 1: Usando php.ini local (Recomendado)
```bash
php -c php.ini -S localhost:8000
```

### Opção 2: Usando servidor Apache/Nginx
O arquivo `.htaccess` já está configurado para Apache.

### Limites Configurados:
- **Upload máximo por arquivo**: 10MB
- **POST máximo**: 100MB  
- **Máximo de arquivos**: 20
- **Memória**: 256MB
- **Tempo de execução**: 300 segundos

### Verificar Configuração:
```bash
php -c php.ini -r "echo 'Upload: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

### Problemas Comuns:

1. **Erro "Arquivo muito grande"**: 
   - Certifique-se de usar o comando com `-c php.ini`
   - O servidor padrão `php -S localhost:8000` usa limite de 2MB

2. **Servidor não inicia**:
   - Verifique se a porta 8000 está livre
   - Use outra porta: `php -c php.ini -S localhost:8080`