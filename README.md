# Simplifica Receita

> Transforme receitas médicas do e-SUS em guias visuais acessíveis

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

## Sobre

**Simplifica Receita** é uma plataforma gratuita para profissionais de UBS que converte receitas do e-SUS em formatos pictográficos, facilitando a compreensão dos pacientes.

### Funcionalidades

- 📋 **Extração automática** de medicamentos do PDF (suporte a múltiplas páginas)
- 🧹 **Limpeza inteligente** de dados sensíveis e lixo de impressão
- 🎨 **Guia visual** com ícones, emojis e cores
- 📊 **Quadro de horários** para cada medicamento
- 🔊 **QR Codes de áudio** para instruções faladas
- 🎬 **Vídeos educativos** do YouTube por medicamento
- 🏷️ **Etiquetas de recorte** para colar nas caixas
- 🤖 **Análise de interações** por IA (Gemini)
- 🔒 **LGPD compliant** - dados não são armazenados

## Instalação

### Requisitos

- PHP 8.0+
- MySQL 8.0+
- Composer
- Extensões PHP: curl, gd, mbstring, pdo_mysql

### Passos

```bash
# 1. Clone o repositório
git clone https://github.com/vvictorpedrosavs-cloud/simplificareceita.git
cd simplificareceita

# 2. Instale dependências
composer install

# 3. Configure o banco
mysql -u root -p < config/schema.sql

# 4. Configure credenciais
cp config/database.example.php config/database.php
# Edite config/database.php com suas credenciais do banco e chave da API Gemini

# 5. Crie o primeiro admin
# Acesse: https://seu-dominio.com/setup_admin.php
# IMPORTANTE: Exclua setup_admin.php após usar!
```

### Gemini AI (Opcional)

Para habilitar análise de interações medicamentosas, adicione sua chave de API no arquivo `config/database.php`.

## Uso

1. Acesse a plataforma
2. Faça upload do PDF da receita e-SUS
3. Revise os medicamentos extraídos
4. Gere o PDF visual

## Estrutura

```
├── admin/           # Área administrativa
├── auth/            # Autenticação
├── config/          # Configurações
├── docs/            # Documentação
├── assets/          # CSS, JS, imagens
├── includes/        # Componentes PHP
├── index.php        # Página principal
├── process.php      # Processador de PDFs
├── generate_pdf.php # Gerador de PDF
└── eula.php         # Termos de uso
```

## Segurança

- ✅ Dados pessoais processados apenas em memória
- ✅ PDF original excluído após processamento
- ✅ Senhas hasheadas com bcrypt
- ✅ Proteção CSRF em formulários
- ✅ Prepared statements (SQL injection)
- ✅ Rate limiting no login

## Tecnologias

- **Backend**: PHP 8.0+
- **PDF Parser**: smalot/pdfparser
- **PDF Generator**: TCPDF + FPDI
- **QR Codes**: endroid/qr-code
- **Banco**: MySQL 8.0
- **IA**: Google Gemini API


## Documentação

- [📖 Documentação Técnica](docs/TECHNICAL.md)
- [📚 Manual do Usuário](docs/USER_MANUAL.md)

## Contribuição

Pull requests são bem-vindos! Para mudanças maiores, abra uma issue primeiro.

## Licença

MIT License - [Victor Pedrosa](mailto:vvictor.pedrosa.vs@gmail.com)

---

<p align="center">
Desenvolvido com ❤️ para profissionais de saúde
</p>
