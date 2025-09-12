# 🐳 Docker Deployment - Projeto Vetel RDO

Sistema de Relatório Diário de Obra (RDO) pronto para deploy em Coolify.

## 🚀 Quick Start

### Para Coolify

1. **Criar nova aplicação** → Docker Compose
2. **Arquivo compose**: `docker-compose.coolify.yml`
3. **Configurar variáveis de ambiente** (veja abaixo)
4. **Deploy!**

## 📦 O que está incluído

- **PHP 8.2 + Apache** otimizado para produção
- **Permissões automáticas** - sem necessidade de scripts externos
- **Volumes persistentes** para uploads e sessões
- **Health check** endpoint integrado
- **SSL/HTTPS** automático via Coolify

## 🔧 Variáveis de Ambiente Obrigatórias

```env
# Banco de Dados MySQL Externo
DB_HOST=seu-mysql.com
DB_USERNAME=usuario
DB_PASSWORD=senha
DB_NAME=formulario_bd

# Admin
ADMIN_EMAIL=admin@vetel.ind.br
```

## 📁 Estrutura de Arquivos

```
projeto_vetel/
├── docker-compose.coolify.yml  # Use este para Coolify
├── Dockerfile                  # Build otimizado multi-stage
├── docker-entrypoint.sh        # Setup automático de permissões
├── .env.production             # Template de variáveis
└── health.php                  # Endpoint de monitoramento
```

## ✅ Features

### Permissões Automáticas
O container configura automaticamente as permissões ao iniciar:
- Cria diretórios necessários
- Define permissões 777 para uploads
- Testa escrita nos diretórios
- Gera arquivo .env do ambiente

### Volumes Persistentes
- `uploads`: Fotos dos relatórios
- `reports`: PDFs gerados
- `sessions`: Sessões PHP
- `logs`: Logs da aplicação

### Segurança
- Executa como www-data internamente
- Headers de segurança configurados
- Sessões seguras
- HTTPS forçado

## 🌐 Domínio

Configurado para: **https://rdo.vetel.ind.br/**

## 🔍 Monitoramento

Health check disponível em: `/health.php`

Retorna:
- Status do banco de dados
- Extensões PHP
- Permissões de diretórios
- Uso de memória e disco

## 📝 Logs Detalhados

### Visualizar Logs do Container

```bash
# Logs completos do startup
docker logs [container-id]

# Acompanhar logs em tempo real
docker logs -f [container-id]

# Últimas 100 linhas
docker logs --tail 100 [container-id]
```

### Exemplo de Log de Startup Detalhado

```
==========================================
[2024-12-13 10:00:00] PROJETO VETEL - RDO SYSTEM
[2024-12-13 10:00:00] Container Initialization Starting
[2024-12-13 10:00:00] Domain: https://rdo.vetel.ind.br/
==========================================

[2024-12-13 10:00:01] SYSTEM INFORMATION:
  Hostname: abc123def456
  OS: Debian GNU/Linux 11
  Architecture: x86_64

[2024-12-13 10:00:01] SOFTWARE VERSIONS:
  PHP: 8.2.15
  Apache: 2.4.56
  Composer: 2.6.5

[2024-12-13 10:00:02] ENVIRONMENT CONFIGURATION:
  Environment: production
  Debug Mode: false
  Timezone: America/Sao_Paulo
  Max Upload: 10M

[2024-12-13 10:00:02] DATABASE CONFIGURATION:
  Host: mysql.vetel.com
  Port: 3306
  Database: formulario_bd
  Username: rdo_user

[2024-12-13 10:00:03] SETTING UP DIRECTORIES:
  ✓ Created: /var/www/html/img/album (Photo uploads)
  ✓ Created: /var/www/html/relatorios (PDF reports)
  • Exists: /var/www/sessions (PHP sessions)

[2024-12-13 10:00:03] SETTING PERMISSIONS:
  ✓ Set 777: /var/www/html/img/album
  ✓ Set 777: /var/www/html/relatorios
  ✓ Set 777: /var/www/sessions

[2024-12-13 10:00:04] TESTING WRITE PERMISSIONS:
  ✓ Writable: /var/www/html/img/album (Photo uploads)
  ✓ Writable: /var/www/html/relatorios (Reports)
  ✓ Writable: /var/www/sessions (Sessions)

[2024-12-13 10:00:04] DISK SPACE:
  ✓ Disk usage: 45% (Free: 12GB)

[2024-12-13 10:00:05] PHP EXTENSIONS:
  ✓ pdo enabled
  ✓ pdo_mysql enabled
  ✓ gd enabled
  ✓ zip enabled
  ✓ intl enabled
  ✓ opcache enabled

[2024-12-13 10:00:06] DATABASE CONNECTION TEST:
  ✓ Database connection successful

[2024-12-13 10:00:06] APACHE MODULES:
  ✓ mod_rewrite enabled
  ✓ mod_headers enabled
  ✓ mod_expires enabled

==========================================
[2024-12-13 10:00:07] INITIALIZATION COMPLETE
[2024-12-13 10:00:07] Container ready to serve requests
[2024-12-13 10:00:07] URL: https://rdo.vetel.ind.br/
==========================================

[2024-12-13 10:00:07] STARTING APACHE WEB SERVER...
[2024-12-13 10:00:07] Listening on port 80
[2024-12-13 10:00:07] Document root: /var/www/html
```

### Monitoramento em Tempo Real

Execute o script de monitoramento dentro do container:
```bash
# Executar monitor
docker exec [container-id] php /var/www/html/monitor.php

# Monitoramento contínuo (a cada 5 segundos)
docker exec [container-id] watch -n 5 php /var/www/html/monitor.php
```

### Logs por Tipo

```bash
# Logs do Apache
docker exec [container-id] tail -f /var/log/apache2/error.log
docker exec [container-id] tail -f /var/log/apache2/access.log

# Logs de PHP
docker exec [container-id] tail -f /var/log/apache2/php_errors.log

# Logs de uploads/estáticos
docker exec [container-id] tail -f /var/log/apache2/static.log
```

## 🚨 Troubleshooting

### Uploads não funcionam?
O container corrige permissões automaticamente no startup. Se ainda houver problemas:
1. Verifique os logs do container
2. Reinicie o container para re-executar o entrypoint

### Banco de dados não conecta?
1. Verifique as variáveis de ambiente no Coolify
2. Teste conectividade do VPS para o MySQL
3. Verifique firewall do banco de dados

## 📊 Requisitos

- **VPS**: Mínimo 1GB RAM, 1 CPU
- **Docker**: Instalado via Coolify
- **MySQL**: Externo, acessível da VPS
- **Domínio**: DNS apontando para VPS

## 🔄 Atualizações

1. Push para o repositório Git
2. No Coolify: clicar "Redeploy"
3. Aguardar build e deploy automático

## 📞 Suporte

- Health: https://rdo.vetel.ind.br/health.php
- Logs: Via Coolify Dashboard
- Métricas: Coolify Monitoring

---

**Versão**: 1.0.0  
**Última atualização**: Dezembro 2024