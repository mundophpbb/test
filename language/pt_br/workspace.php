<?php
/**
 * mundophpbb workspace extension [Portuguese Brazilian]
 *
 * @package mundophpbb workspace
 * @copyright (c) 2026 mundophpbb
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
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

    // =====================================================
    // Interface Principal
    // =====================================================
    'WSP_TITLE'                 => 'Mundo phpBB Workspace',
    'WSP_EXPLORER'              => 'Explorador',
    'WSP_PROJECT_LABEL'         => 'Projeto',
    'WSP_SELECT_FILE'           => 'Selecione um arquivo para editar',
    'WSP_SELECT_TO_BEGIN'       => 'Abra ou crie um projeto para listar os arquivos.',

    // Pasta ativa (header)
    'WSP_ACTIVE_FOLDER'         => 'Pasta',
    'WSP_ACTIVE_FOLDER_TITLE'   => 'Pasta atualmente selecionada',
    'WSP_ROOT'                  => 'Raiz',

    // Welcome editor
    'WSP_WELCOME_MSG' => "/*\n * MUNDO PHPBB WORKSPACE\n * =====================\n * \n * NENHUM ARQUIVO ABERTO.\n * \n * 1. Selecione um arquivo na aba lateral.\n * 2. Edite o código.\n * 3. Use CTRL + S para salvar rapidamente.\n */\n",

    // =====================================================
    // Status gerais
    // =====================================================
    'WSP_LOADING'               => 'Carregando...',
    'WSP_PROCESSING'            => 'Processando...',
    'WSP_SAVING'                => 'Salvando...',
    'WSP_SAVED'                 => 'Alterações salvas!',
    'WSP_UPLOADING'             => 'Enviando arquivos...',
    'WSP_COPIED'                => 'Copiado!',
    'WSP_HISTORY_CLEANED'       => 'Histórico do projeto reiniciado.',
    'WSP_CACHE_CLEANED'         => 'Cache do phpBB limpo com sucesso.',

    'WSP_OK'                    => 'OK',
    'WSP_CANCEL'                => 'Cancelar',
    'WSP_CLOSE'                 => 'Fechar',
    'WSP_RENAME'                => 'Renomear',

    // =====================================================
    // Projetos
    // =====================================================
    'WSP_NEW_PROJECT'           => 'Novo Projeto',
    'WSP_OPEN_PROJECT'          => 'Abrir Projeto',
    'WSP_DEFAULT_DESC'          => 'Criado via Workspace IDE',
    'WSP_NO_PROJECTS'           => 'Nenhum projeto encontrado.',
    'WSP_EMPTY_PROJECT'         => 'Projeto vazio',
    'WSP_EMPTY_PROJECT_DESC'    => 'Este projeto ainda não possui arquivos.',
    'WSP_DRAG_UPLOAD_HINT'      => 'Arraste pastas aqui ou use o botão de upload.',

    // =====================================================
    // Arquivos e Pastas
    // =====================================================
    'WSP_ADD_FILE'              => 'Novo arquivo',
    'WSP_NEW_ROOT_FILE'         => 'Novo arquivo na raiz do projeto',
    'WSP_NEW_ROOT_FOLDER'       => 'Nome da pasta na raiz',
    'WSP_NEW_ROOT_FOLDER_TITLE' => 'Nova Pasta',
    'WSP_NEW_FILE_IN'           => 'Novo arquivo em ',
    'WSP_NEW_FOLDER_IN'         => 'Nova subpasta em ',

    'WSP_UPLOAD_FILES'          => 'Enviar Arquivos',
    'WSP_DRAG_UPLOAD'           => 'Arraste arquivos ou pastas aqui para upload',

    // Context menu tree
    'WSP_CTX_NEW_FILE'          => 'Novo arquivo aqui',
    'WSP_CTX_NEW_FOLDER'        => 'Nova subpasta aqui',
    'WSP_CTX_DELETE_FOLDER'     => 'Excluir pasta',

    // =====================================================
    // Toolbar rica
    // =====================================================
    'WSP_SAVE_CHANGES'          => 'Salvar alterações',
    'WSP_SEARCH_REPLACE'        => 'Buscar & Substituir',
    'WSP_GENERATE_CHANGELOG'    => 'Gerar changelog',
    'WSP_CLEAR_CHANGELOG'       => 'Limpar changelog',
    'WSP_REFRESH_CACHE'         => 'Limpar cache do phpBB',
    'WSP_TOGGLE_FULLSCREEN'     => 'Tela cheia',

    // =====================================================
    // Diff
    // =====================================================
    'WSP_DIFF_TITLE'            => 'Comparação de arquivos',
    'WSP_DIFF_GENERATE'         => 'Gerar comparação',
    'WSP_DIFF_GENERATING'       => 'Gerando...',
    'WSP_DIFF_PREVIEW'          => 'Visualização do diff',
    'WSP_DIFF_SELECT_ORIG'      => 'Arquivo original',
    'WSP_DIFF_SELECT_MOD'       => 'Arquivo modificado',
    'WSP_COPY_BBCODE'           => 'Copiar BBCode',

    // =====================================================
    // Busca e Replace
    // =====================================================
    'WSP_SEARCH_TERM'           => 'Termo de busca',
    'WSP_REPLACE_TERM'          => 'Substituir por',
    'WSP_REPLACE_ALL'           => 'Substituir em tudo',
    'WSP_REPLACE_SUCCESS'       => 'Sucesso! %d alteração(ões) realizadas.',
    'WSP_SEARCH_NO_RESULTS'     => 'Nenhum arquivo encontrado.',
    'WSP_SEARCH_EMPTY_ERR'      => 'Digite um termo de busca.',
    'WSP_REPLACE_ONLY_FILE'     => 'Substituir apenas neste arquivo: ',
    'WSP_REPLACE_IN_PROJECT'    => 'Substituir em todo o projeto: ',

    // =====================================================
    // Prompts
    // =====================================================
    'WSP_PROMPT_NAME'           => 'Digite o nome:',
    'WSP_PROMPT_FILE_NAME'      => 'Nome do arquivo (ex: includes/funcoes.php):',

    // =====================================================
    // Confirmações
    // =====================================================
    'WSP_CONFIRM_DELETE'        => 'Tem certeza que deseja excluir este projeto permanentemente?',
    'WSP_CONFIRM_FILE_DELETE'   => 'Deseja realmente apagar este arquivo?',
    'WSP_CONFIRM_DELETE_FOLDER' => "Excluir a pasta '{path}' e todos os arquivos?",
    'WSP_CONFIRM_CLEAR_CHANGELOG'=> 'Deseja apagar todo o histórico do projeto?',
    'WSP_CONFIRM_REPLACE_ALL'   => 'Deseja substituir em todo o projeto?',

    // =====================================================
    // Mensagens sistema
    // =====================================================
    'WSP_FILE_ELIMINATED'       => 'Arquivo removido do projeto.',
    'WSP_CHANGELOG_TITLE'       => 'Workspace - Changelog automático',
    'WSP_GENERATED_ON'          => 'Gerado em',

    // =====================================================
    // Erros
    // =====================================================
    'WSP_ERR_PERMISSION'        => 'Você não tem permissão para acessar o Workspace.',
    'WSP_ERR_INVALID_ID'        => 'ID inválido.',
    'WSP_ERR_INVALID_DATA'      => 'Dados inválidos enviados.',
    'WSP_ERR_INVALID_NAME'      => 'O nome não pode ficar vazio.',
    'WSP_ERR_FILE_NOT_FOUND'    => 'Arquivo não encontrado.',
    'WSP_ERR_FILE_EXISTS'       => 'Já existe um arquivo com este nome.',
    'WSP_ERR_INVALID_FILES'     => 'Selecione arquivos válidos.',
    'WSP_ERR_SAVE_FAILED'       => 'Erro ao salvar o arquivo.',
    'WSP_ERR_SERVER_500'        => "Erro interno do servidor.\nVerifique a biblioteca Diff.",
    'WSP_ERR_COMM'              => 'Erro de comunicação com o servidor.',
    'WSP_ERR_COPY'              => 'Erro ao copiar.',

));