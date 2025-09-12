# 📋 Projeto Vetel - Sistema RDO

**Sistema de Relatório Diário de Obra (RDO)** para gerenciamento e controle de obras na construção civil.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://mysql.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-green)](https://docker.com)
[![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)

## 🚀 Visão Geral

Sistema web desenvolvido em PHP para gestão de Relatórios Diários de Obra (RDO) e Relatórios de Produção (RDP), permitindo o acompanhamento detalhado de obras de construção civil com:

- 📸 **Upload de fotos** do progresso da obra
- 👷 **Controle de funcionários** presentes
- 📝 **Registro de serviços** executados
- 📊 **Geração de relatórios** em PDF
- 🏢 **Gestão multi-empresa** (contratante/contratada)
- 🔒 **Sistema de autenticação** e controle de acesso

## 🌐 Acesso

**Produção**: https://rdo.vetel.ind.br/

## 📁 Estrutura do Projeto

```
projeto_vetel/
├── models/              # Classes e DAO
│   ├── Connection.php   # Conexão com banco de dados
│   ├── DAO.php         # Data Access Object
│   └── *.php           # Entidades (Empresa, Obra, DiarioObra, etc)
├── img/                # Imagens e uploads
│   ├── album/          # Fotos das obras
│   └── logo/           # Logos das empresas
├── relatorios/         # PDFs gerados
├── auth/               # Sistema de autenticação
├── admin/              # Área administrativa
├── vendor/             # Dependências Composer
├── css/                # Estilos Bootstrap
├── js/                 # Scripts JavaScript
└── sql/                # Scripts de banco de dados
```

## 🛠️ Tecnologias

- **Backend**: PHP 8.2+
- **Frontend**: Bootstrap 4.5.2, jQuery 3.5.1
- **Banco de Dados**: MySQL/MariaDB
- **PDF**: DOMPDF
- **Upload**: Dropzone.js 5.7.0
- **Container**: Docker & Docker Compose
- **Deploy**: Coolify

## ⚙️ Requisitos

### Desenvolvimento Local
- PHP 8.2 ou superior
- MySQL 5.7 ou MariaDB 10.3+
- Composer 2.0+
- Apache ou Nginx

### Docker (Recomendado)
- Docker 20.10+
- Docker Compose 2.0+

## 🚀 Instalação

### Opção 1: Docker (Recomendado)

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/projeto_vetel.git
cd projeto_vetel

# Configure as variáveis de ambiente
cp .env.example .env
# Edite o arquivo .env com suas configurações

# Inicie com Docker Compose
docker-compose up -d

# Acesse http://localhost:8000
```

### Opção 2: Instalação Manual

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/projeto_vetel.git
cd projeto_vetel

# Instale as dependências
composer install

# Configure o banco de dados
mysql -u root -p < sql/formulario_bd.sql

# Configure as variáveis de ambiente
cp .env.example .env
# Edite o arquivo .env com suas configurações

# Inicie o servidor PHP
php -c php.ini -S localhost:8000

# Acesse http://localhost:8000
```

## 🔧 Configuração

### Variáveis de Ambiente (.env)

```env
# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=formulario_bd
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Aplicação
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
ADMIN_EMAIL=admin@example.com

# Upload
MAX_UPLOAD_SIZE=10M
PHOTO_STORAGE_PATH=/img/album
```

### Configuração do Banco de Dados

1. Crie o banco de dados:
```sql
CREATE DATABASE formulario_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Execute o script SQL:
```bash
mysql -u root -p formulario_bd < sql/formulario_bd.sql
```

3. Configure as credenciais no arquivo `.env`

## 📖 Uso do Sistema

### 1. Login
- Acesse o sistema com as credenciais configuradas
- Padrão: `admin` / `admin123` (altere após o primeiro acesso)

### 2. Cadastro de Empresas
- Navegue para **Cadastro → Empresas**
- Registre empresas contratantes e contratadas
- Faça upload dos logos (opcional)

### 3. Cadastro de Obras
- Vá para **Cadastro → Obras**
- Selecione contratante e contratada
- Adicione descrição da obra

### 4. Criação de RDO
- Acesse **Cadastro → Diário de Obras**
- Selecione a obra
- Clique em **Criar** para novo RDO
- Preencha:
  - Data e número do relatório
  - Funcionários presentes
  - Serviços executados
  - Observações gerais
  - Fotos do progresso

### 5. Geração de Relatórios
- Visualize RDOs criados
- Exporte para PDF
- Gere relatórios consolidados (RDP)

## 🐳 Docker Deployment

### Para Coolify

O projeto está preparado para deploy no Coolify:

```bash
# Use o arquivo específico
docker-compose.coolify.yml

# Configure no Coolify:
- Build Pack: Docker Compose
- Arquivo: docker-compose.coolify.yml
- Domínio: rdo.vetel.ind.br
```

Veja [DEPLOY_COOLIFY.md](DEPLOY_COOLIFY.md) para instruções completas.

### Desenvolvimento com Docker

```bash
# Construir imagem
docker build -t projeto-vetel .

# Executar container
docker run -d -p 8000:80 \
  -e DB_HOST=seu_mysql_host \
  -e DB_USERNAME=usuario \
  -e DB_PASSWORD=senha \
  projeto-vetel

# Ver logs detalhados
docker logs -f [container-id]
```

## 📊 Monitoramento

### Health Check
- Endpoint: `/health.php`
- Verifica: banco de dados, PHP, permissões, memória

### Monitor em Tempo Real
```bash
docker exec [container-id] php monitor.php
```

### Logs
- Apache: `/var/log/apache2/access.log`
- Erros: `/var/log/apache2/error.log`
- PHP: `/var/log/apache2/php_errors.log`

## 🔒 Segurança

- ✅ Autenticação com hash bcrypt
- ✅ Proteção contra SQL Injection (PDO prepared statements)
- ✅ Proteção XSS (htmlspecialchars)
- ✅ Headers de segurança configurados
- ✅ HTTPS forçado em produção
- ✅ Sessões seguras com timeout
- ✅ Controle de acesso por níveis

## 📝 API Endpoints

### Principais Rotas

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/` | Página inicial |
| GET | `/cadastroObra.php` | Lista/cadastro de obras |
| GET | `/cadastroDiarioObras.php?id_obra=X` | RDOs da obra |
| GET | `/coletorDados.php?id_diario_obra=X` | Editar RDO |
| POST | `/uploadFotos.php` | Upload de imagens |
| GET | `/rdo.php?id=X` | Visualizar RDO |
| GET | `/exportadorPdf.php` | Gerar PDF |
| GET | `/health.php` | Status do sistema |

## 🧪 Testes

```bash
# Testar conexão com banco
php test-db.php

# Testar uploads
php test-upload.php

# Verificar permissões
php check-permissions.php
```

## 🤝 Contribuindo

1. Fork o projeto
2. Crie uma branch (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -m 'feat: adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

### Padrões de Commit

- `feat:` Nova funcionalidade
- `fix:` Correção de bug
- `docs:` Documentação
- `style:` Formatação
- `refactor:` Refatoração
- `test:` Testes
- `chore:` Manutenção

## 📄 Licença

Este é um software proprietário. Todos os direitos reservados © 2024 Vetel Indústria.

## 👥 Equipe

- **Desenvolvimento**: Anthony Daros
- **Empresa**: Vetel Indústria
- **Contato**: admin@vetel.ind.br

## 🆘 Suporte

Para suporte, envie um email para suporte@vetel.ind.br ou abra uma issue no GitHub.

## 📸 Screenshots

### Dashboard
![Dashboard](docs/images/dashboard.png)

### Cadastro de RDO
![RDO](docs/images/rdo.png)

### Relatório PDF
![PDF](docs/images/pdf.png)

---

**Vetel Indústria** - Sistema de Gestão de Obras
© 2024 - Todos os direitos reservados