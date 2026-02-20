# Mundo phpBB Workspace

Extensão colaborativa para phpBB que transforma o fórum em um **repositório de projetos e códigos**, armazenados diretamente no banco de dados e editáveis pelo navegador.

---

## ?? O que é o Workspace

O **Mundo phpBB Workspace** é uma IDE leve integrada ao phpBB, criada para:

* organizar projetos de código dentro do fórum
* editar arquivos rapidamente
* compartilhar projetos com outros usuários
* controlar permissões individuais e por grupo
* gerar diffs e changelogs
* permitir colaboração entre membros

Não é um VSCode nem substitui Git.
O objetivo é **centralizar desenvolvimento e colaboração dentro da comunidade phpBB**.

---

## ?? Para que serve

Uso ideal para:

* desenvolvimento de extensões phpBB
* bibliotecas internas
* snippets e scripts
* colaboração entre membros do fórum
* repositório de código da comunidade

Funcionalidades principais:

* criação de projetos
* armazenamento de arquivos no banco
* edição de código via navegador (Ace Editor)
* upload de arquivos e pastas
* drag & drop
* geração de changelog
* ferramenta de diff
* download em ZIP
* permissões avançadas

---

## ?? Estrutura da extensão

### Banco de dados

Tabelas principais:

* `workspace_projects`
* `workspace_files`
* `workspace_projects_users`
* `workspace_permissions`

Recursos suportados:

* projetos multiusuário
* lock de projeto
* permissões por usuário e grupo
* roles (owner/dev/viewer)
* arquivos armazenados no banco

---

## ?? Interface

* Explorer lateral estilo IDE
* Editor de código (Ace)
* Toolbar com ações rápidas
* Drag & drop de arquivos e pastas
* Indicador de pasta ativa
* Breadcrumb de navegação
* Modais de busca, replace e diff

---

## ?? Sistema de permissões

A extensão possui três camadas de segurança.

### 1) ACL global do phpBB

Controla acesso à IDE.

Permissões:

* `u_workspace_access`
* `u_workspace_create`
* `u_workspace_download`
* `u_workspace_manage_own`
* `u_workspace_manage_all`

---

### 2) Permissões por projeto

Roles disponíveis:

* **owner**
* **dev**
* **viewer**

| Role   | Ver | Editar | Gerenciar | Excluir |
| ------ | --- | ------ | --------- | ------- |
| owner  | ?   | ?      | ?         | ?       |
| dev    | ?   | ?      | ?         | ?       |
| viewer | ?   | ?      | ?         | ?       |

---

### 3) Permissões granulares

Tabela `workspace_permissions` permite:

* liberar usuário específico
* liberar grupo
* definir flags independentes

Flags disponíveis:

* `can_view`
* `can_edit`
* `can_manage`
* `can_delete`
* `can_lock`

Permite cenários como:

* projeto somente leitura
* colaboração parcial
* equipe específica
* projeto travado

---

## ?? Lock de projeto

Projetos podem ser bloqueados para:

* evitar edição simultânea
* revisão ou deploy

Registro:

* usuário que travou
* data e hora

---

## ?? Upload inteligente

Suporte a:

* múltiplos arquivos
* pastas completas
* drag & drop
* criação automática de `.placeholder` para manter estrutura
* envio direto para pasta ativa

---

## ?? Changelog automático

* registro de alterações
* geração de histórico
* limpeza de changelog
* exportação para fórum
* integração com diff

---

## ?? Diff integrado

Comparação entre:

* versões de arquivos
* arquivos diferentes do projeto

Saídas:

* visual
* BBCode pronto para o fórum

---

## ?? Segurança

Inclui:

* validação de extensão
* sanitização de paths
* bloqueio de `../`
* ACL phpBB
* permissões por projeto
* permissões individuais

---

## ?? Público-alvo

* desenvolvedores phpBB
* comunidades técnicas
* equipes de modding
* projetos colaborativos

---

## ?? Filosofia do projeto

O Workspace não tenta substituir:

* VSCode
* Git
* IDEs profissionais

Ele resolve outro problema:

> Centralizar código e colaboração dentro do fórum phpBB.

---

## ?? Roadmap

Possíveis evoluções:

* versionamento real de arquivos
* rollback
* integração com Git
* permissões por pasta
* comentários em código
* revisão colaborativa

---

## ?? Status atual

? Estrutura de projetos
? Editor integrado
? Upload e drag & drop
? Permissões multi-camada
? Diff e changelog
? Colaboração básica

Em evolução contínua.

---

## ? Informações

**Nome:** Mundo phpBB Workspace
**Autor:** mundophpbb
**Licença:** GPL
**Compatibilidade:** phpBB 3.3+

---

## ?? Changelog inicial

### v1.0.0

* Base da IDE integrada ao phpBB
* Estrutura de projetos no banco
* Editor Ace
* Upload de arquivos e pastas
* Sistema de permissões
* Roles por projeto
* Lock de projeto
* Diff e changelog
* Interface Explorer

---
