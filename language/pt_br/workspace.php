<?php
/**
 * mundophpbb workspace extension [Portuguese Brazilian]
 *
 * @package mundophpbb workspace
 * @copyright (c) 2026 mundophpbb
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	// Interface Principal e Branding
	'WSP_TITLE'             => 'Mundo phpBB Workspace',
	'WSP_VERSION_TAG'       => 'v2.9',
	'WSP_EXPLORER'          => 'Explorador',
	'WSP_PROJECT_LABEL'     => 'Projeto',
	'WSP_SELECT_FILE'       => 'Selecione um arquivo para editar',
	'WSP_LOADING'           => 'Carregando...',
	'WSP_PROCESSING'        => 'Processando...',

	// Menus da Toolbar (Sincronizados com v2.9)
	'WSP_MENU_FILE'         => 'Arquivo',
	'WSP_MENU_PROJECT'      => 'Projeto',
	'WSP_MENU_TOOLS'        => 'Ferramentas',
	'WSP_MENU_VIEW'         => 'Exibir',

	// Submenus: Arquivo
	'WSP_NEW_PROJECT'       => 'Novo Projeto',
	'WSP_OPEN_PROJECT'      => 'Abrir Projeto...',
	'WSP_SAVE'              => 'Salvar',
	'WSP_EXPORT_GIST'       => 'Exportar Gist (GitHub)',

	// Submenus: Projeto
	'WSP_NEW_FOLDER'        => 'Nova Pasta',
	'WSP_NEW_FILE'          => 'Novo Arquivo',
	'WSP_GENERATE_SKELETON' => 'Gerador de Esqueleto (PSR-4)',
	'WSP_DOWNLOAD_ZIP'      => 'Baixar Projeto (ZIP)',
	'WSP_GENERATE_LOG'      => 'Gerar Changelog',

	// Submenus: Ferramentas
	'WSP_DIFF_WIZARD'       => 'Assistente de Diferença (Diff)',
	'WSP_SEARCH_REPLACE'    => 'Procurar e Substituir',
	'WSP_PURGE_CACHE'       => 'Limpar Cache do phpBB',
	'WSP_SHORTCUTS'         => 'Atalhos de Teclado',

	// Submenus: Exibir
	'WSP_CHANGE_THEME'      => 'Mudar Tema',
	'WSP_ZEN_MODE'          => 'Modo Zen (Foco Total)',
	'WSP_TOGGLE_CONSOLE'    => 'Alternar Console de Saída',

	// Mensagens de Boas-vindas
	'WSP_WELCOME_MSG'       => "/*\n * MUNDO PHPBB WORKSPACE v2.9\n * =========================\n * \n * AMBIENTE PRONTO PARA DESENVOLVIMENTO.\n * \n * 1. Selecione um projeto na Toolbar superior.\n * 2. Explore ou arraste arquivos (Drag & Drop).\n * 3. Visualize imagens ou edite códigos PHP/JS/CSS.\n */\n",
	'WSP_WELCOME_MSG_SIDEBAR' => 'Nenhum projeto carregado.',
	'WSP_NO_FILES'          => 'Projeto vazio.',

	// Diálogos Dinâmicos (Usados pelo JS)
	'WSP_PROMPT_PROJECT_NAME' => 'Digite o nome do novo projeto:',
	'WSP_PROMPT_FILE_NAME'    => 'Nome do arquivo ou pasta (use / no final para pastas):',
	'WSP_PROMPT_RENAME'       => 'Digite o novo nome:',
	'WSP_PROMPT_DUPLICATE'    => 'Duplicar como:',
	'WSP_SKEL_VENDOR'         => 'Fornecedor (Vendor):',
	'WSP_SKEL_NAME'           => 'Nome da Extensão:',
	'WSP_RUN_GENERATOR'       => 'Gerar Estrutura',

	// Status e Sucesso
	'WSP_SAVE_CHANGES'      => 'SALVAR ALTERAÇÕES',
	'WSP_SAVING'            => 'SALVANDO...',
	'WSP_SAVED'             => 'SALVO!',
	'WSP_OK'                => 'OK',
	'WSP_CANCEL'            => 'Cancelar',
	'WSP_CLOSE'             => 'Fechar',
	'WSP_RENAME'            => 'Renomear',
	'WSP_DUPLICATE'         => 'Duplicar',
	'WSP_COPIED'            => 'Copiado!',

	// Erros e Alertas
	'WSP_CONFIRM_DELETE'       => 'Excluir este projeto e todos os arquivos permanentemente?',
	'WSP_CONFIRM_FILE_DELETE'  => 'Deseja realmente apagar este arquivo?',
	'WSP_CONFIRM_REPLACE_ALL'  => 'Deseja realmente substituir em TODO o projeto?',
	'WSP_ERR_PERMISSION'       => 'Erro: Você não tem permissão de Fundador.',
	'WSP_ERR_FILE_NOT_FOUND'   => 'Arquivo não encontrado.',
	'WSP_ERR_FILE_EXISTS'      => 'Já existe um arquivo com este nome.',
	'WSP_ERR_SERVER_500'       => 'Erro no Servidor: Bibliotecas ZIP ou DIFF ausentes.',
	'WSP_ERR_CHANGELOG_EMPTY'  => 'Sem alterações suficientes para gerar log.',

	// Permissões ACP
	'ACL_U_WORKSPACE_ACCESS'   => 'Pode acessar a IDE Mundo phpBB Workspace',
));