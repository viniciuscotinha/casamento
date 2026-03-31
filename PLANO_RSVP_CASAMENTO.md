# Plano de Implementacao do RSVP

Este arquivo agora resume o estado real do projeto e tambem registra o que foi mantido como legado de forma intencional.

## Arquitetura atual

- RSVP de convidados:
  rota publica em `/c/<slug>` e compatibilidade com `/c/<token>` antigo.
- Convites de padrinhos:
  rota publica em `/p/<slug>`, separada das familias.
- Admin:
  painel em `/admin` com telas de familias, convidados, padrinhos e relatorios.
- Stack:
  `PHP + MySQL + HTML/CSS/JS`.

## Estrutura ativa

```text
/admin
  index.php
  login.php
  logout.php
  setup.php
  familias.php
  convidados.php
  padrinhos.php
  relatorios.php
/app
  admin_ui.php
  auth.php
  bootstrap.php
  csrf.php
  db.php
  helpers.php
  manual.php
/assets
  /css
  /fonts
  /js
  /svg
/c
  index.php
/config
  env.php
  env.example.php
/p
  index.php
/sql
  schema.sql
  /migrations
```

## Banco atual

Tabelas principais:
- `familias`
- `convidados`
- `convites_padrinhos`
- `admin_users`
- `auditoria`
- `admin_login_attempts`

Relacoes principais:
- cada `convidado` pertence a uma `familia`
- um `convidado` pode apontar para um `responsavel_id` da mesma familia
- um `convidado` pode ser vinculado a um `manual_invite_id`
- cada `convites_padrinhos` pode reunir 1 ou 2 convidados, inclusive de familias diferentes

## Fluxos implementados

- [x] Login admin em `/admin`
- [x] Cadastro de familias
- [x] Cadastro de convidados
- [x] Responsavel por familia
- [x] RSVP publico por familia
- [x] Links curtos editaveis para familias
- [x] Exportacao CSV das familias com links
- [x] Convites de padrinhos independentes
- [x] Links curtos editaveis para padrinhos
- [x] Textos compartilhados por convite de padrinhos
- [x] Textos individuais por card de convidado
- [x] Relatorios basicos

## Migracoes ativas

Use conforme o estado do banco:
- `sql/schema.sql`
  para instalacao nova do zero
- `sql/migrations/20260330_add_token_encrypted.sql`
  para bancos antigos que ainda nao tinham token exportavel
- `sql/migrations/20260331_add_public_slug.sql`
  para bancos antigos que ainda nao tinham slug curto nas familias
- `sql/migrations/20260331_add_manual_invites.sql`
  para habilitar convites de padrinhos independentes em bancos que ainda nao receberam essa estrutura
- `sql/migrations/20260331_add_manual_invite_groups_only.sql`
  complemento para casos em que a migracao antiga de padrinhos baseada em familia ja tinha sido aplicada

## Limpeza ja feita

- [x] Removido `build/`
- [x] Removido `GUIA_PUBLICACAO_PUBLIC_HTML.md`
- [x] Removido o helper legado `family_manual_url_from_row`
- [x] Removidos `confirmacao.html` e `obrigado.html`, substituidos pelo fluxo dinamico de `/c/...`
- [x] Limpa a pagina `admin/familias.php`, deixando padrinhos fora dela

## Legado mantido de proposito

- [x] compatibilidade com `/c/<token>`
  continua ativa para links antigos enquanto voce quiser manter

## Limpeza adicional feita

- [x] removido fallback de `p/*.html`
- [x] `/p/` agora responde por `p/index.php`

## Pendencias praticas

- [ ] Refinar mais o visual mobile do RSVP em `/c/...`
- [ ] Refinar densidade e produtividade do admin para uso no desktop
- [ ] Decidir se a validacao publica extra sera `none`, `phone_last4` ou `surname`
- [ ] Revisar se a homepage deve ganhar CTA novo apontando para a logica atual

## Checklist de deploy

- [x] Publicar arquivos PHP e `.htaccess`
- [x] Validar `/admin/setup`
- [x] Criar primeiro admin
- [x] Cadastrar familia e convidado de teste
- [ ] Rodar a migracao correta de padrinhos no banco de producao
- [ ] Publicar a versao mais recente de `admin/`, `app/`, `assets/`, `c/` e `p/`
- [ ] Testar um link real `/c/<slug>`
- [ ] Testar um link real `/p/<slug>`
