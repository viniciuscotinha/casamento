# Casamento

Sistema de site e painel administrativo para casamento, com foco em:

- confirmacao de presenca por familia
- links curtos para convidados
- painel `/admin` para gerenciar familias, convidados e relatorios
- convites de padrinhos em `/p/<slug>`
- hospedagem em ambiente compartilhado com PHP + MySQL

## Visao Geral

Este projeto foi construido para rodar em hospedagem compartilhada, sem framework pesado, usando PHP, MySQL/MariaDB, HTML, CSS e JavaScript.

Hoje ele cobre dois fluxos principais:

1. RSVP das familias
   Cada familia recebe um link proprio em `/c/<slug>` para confirmar quem vai, quem nao vai e manter o acompanhamento centralizado.

2. Convites de padrinhos
   Cada convite de padrinhos tem um link proprio em `/p/<slug>`, com layout visual personalizado, textos editaveis e suporte para convite individual ou casal.

## Principais Recursos

- painel admin com autenticacao em `/admin`
- cadastro de familias com slug publico editavel
- cadastro de convidados vinculados a familias
- controle de responsavel da familia
- confirmacao publica de presenca por link
- relatarios e status de confirmacao
- convites de padrinhos com preview no admin
- textos personalizados por convite e por participante
- auditoria basica de acoes administrativas
- compatibilidade com hospedagem compartilhada via `public_html`

## Stack

- PHP 8+
- MySQL ou MariaDB
- Apache com `.htaccess`
- HTML, CSS e JavaScript vanilla

## Estrutura do Projeto

```text
admin/   -> telas do painel administrativo
app/     -> bootstrap, auth, db, helpers e renderizacao compartilhada
assets/  -> CSS, JS, fontes e elementos visuais
c/       -> confirmacao publica dos convidados
config/  -> configuracao do ambiente
p/       -> convites de padrinhos
sql/     -> schema e migrations
index.html
.htaccess
```

## Rotas Principais

- `/` -> pagina principal do site
- `/admin` -> painel administrativo
- `/admin/setup` -> criacao do primeiro admin
- `/c/<slug>` -> confirmacao de presenca da familia
- `/p/<slug>` -> convite dos padrinhos

## Banco de Dados

O schema principal esta em:

- [`sql/schema.sql`](sql/schema.sql)

Tabelas principais:

- `familias`
- `convidados`
- `convites_padrinhos`
- `admin_users`
- `auditoria`
- `admin_login_attempts`

## Configuracao do Ambiente

Use o arquivo de exemplo:

- [`config/env.example.php`](config/env.example.php)

Crie um arquivo real em:

- `config/env.php`

Exemplo do que precisa ser configurado:

- `base_url`
- `app_key`
- `db.host`
- `db.port`
- `db.database`
- `db.username`
- `db.password`
- `public_gate_mode`

Importante:

- `config/env.php` nao deve ser versionado
- o `.gitignore` ja protege esse arquivo

## Como Rodar na Hospedagem

### 1. Criar o banco

No cPanel ou painel da hospedagem:

1. Crie um banco MySQL
2. Crie um usuario do banco
3. Vincule o usuario ao banco com todas as permissoes
4. Importe o arquivo `sql/schema.sql` no phpMyAdmin

### 2. Configurar o ambiente

Crie `config/env.php` com base em `config/env.example.php` e preencha os dados reais da hospedagem.

### 3. Publicar no servidor

Se voce usa SSH com alias configurado, um exemplo de envio para `public_html` e:

```powershell
scp -r .\.htaccess .\index.html .\favicon.png .\admin .\app .\assets .\c .\config .\p casamento-hostinger:~/public_html/
```

Se quiser limpar o servidor antes, faca isso com cuidado para nao apagar arquivos errados.

### 4. Finalizar a instalacao

Depois do upload:

1. Acesse `/admin/setup`
2. Crie o primeiro usuario admin
3. Entre no painel
4. Cadastre familias, convidados e convites de padrinhos

## Como Atualizar o Projeto no Servidor

Sempre que fizer alteracoes locais:

1. Suba apenas os arquivos alterados com `scp`
2. Se houver alteracao no banco, rode a migration correspondente
3. Faca testes nas rotas:
   - `/admin`
   - `/c/<slug>`
   - `/p/<slug>`

Exemplo de upload de um arquivo:

```powershell
scp .\admin\convidados.php casamento-hostinger:~/public_html/admin/convidados.php
```

Exemplo de upload de uma pasta:

```powershell
scp -r .\assets casamento-hostinger:~/public_html/
```

## Como Subir o Projeto no GitHub

Se o repositorio remoto ainda nao existir:

1. Crie um repositorio vazio no GitHub chamado `casamento`
2. Nao marque README, `.gitignore` nem license na criacao

Depois, no terminal:

```powershell
git config --global --add safe.directory C:/Users/vinic/Desktop/servidor_casamento
git remote add origin https://github.com/SEU_USUARIO/casamento.git
git push -u origin main
```

Se o `origin` ja existir:

```powershell
git remote set-url origin https://github.com/SEU_USUARIO/casamento.git
git push -u origin main
```

## Fluxo de Uso no Admin

### Familias

- cadastrar familia
- definir slug publico do RSVP
- copiar e exportar links

### Convidados

- cadastrar convidados da familia
- vincular responsavel
- controlar status de presenca
- associar convidados a convites de padrinhos quando necessario

### Padrinhos

- criar convite com slug proprio
- definir textos compartilhados do manual
- escolher 1 ou 2 participantes por convite
- personalizar titulo e mensagem de cada card
- usar a previa mobile dentro do admin

### Relatorios

- acompanhar confirmados
- acompanhar pendentes
- acompanhar recusas

## Cuidados Importantes

- nao commite `config/env.php`
- nao commite dados reais de convidados sem necessidade
- mantenha o painel `/admin` com senha forte
- use HTTPS na hospedagem
- se alterar estrutura do banco, salve a migration em `sql/migrations/`

## Arquivos Importantes

- [`app/admin_ui.php`](app/admin_ui.php)
- [`admin/familias.php`](admin/familias.php)
- [`admin/convidados.php`](admin/convidados.php)
- [`admin/padrinhos.php`](admin/padrinhos.php)
- [`admin/relatorios.php`](admin/relatorios.php)
- [`c/index.php`](c/index.php)
- [`p/index.php`](p/index.php)
- [`config/env.example.php`](config/env.example.php)
- [`sql/schema.sql`](sql/schema.sql)

## Status do Projeto

Projeto funcional em hospedagem compartilhada, com foco em uso real para o casamento e gerenciamento direto pelo painel admin.

---

Se quiser, o proximo passo pode ser eu deixar esse `README.md` ainda mais bonito para GitHub, com badges, tabela de modulos e screenshots.
