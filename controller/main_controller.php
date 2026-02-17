<?php
namespace mundophpbb\workspace\controller;

use phpbb\controller\helper;

/**
 * Mundo phpBB Workspace - Main Controller
 * Gerencia a interface principal e o carregamento dinâmico de projetos.
 */
class main_controller
{
    protected $helper;
    protected $template;
    protected $db;
    protected $table_prefix;
    protected $request;
    protected $user;
    protected $auth;
    protected $phpbb_root_path;

    public function __construct(
        helper $helper,
        \phpbb\template\template $template,
        $db,
        $table_prefix,
        \phpbb\request\request $request,
        \phpbb\user $user,
        \phpbb\auth\auth $auth,
        $phpbb_root_path
    ) {
        $this->helper = $helper;
        $this->template = $template;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
        $this->request = $request;
        $this->user = $user;
        $this->auth = $auth;
        $this->phpbb_root_path = $phpbb_root_path;
    }

    /**
     * Interface Principal da IDE
     */
    public function handle()
    {
        // 1. Verificação de Permissão (Nível Fundador/Admin configurado via ACL)
        if (!$this->auth->acl_get('u_workspace_access')) {
            trigger_error($this->user->lang('WSP_ERR_PERMISSION', $this->user->lang('ACL_U_WORKSPACE_ACCESS')));
        }

        $this->user->add_lang_ext('mundophpbb/workspace', 'workspace');
        
        // Limpa blocos de template para evitar duplicação em chamadas AJAX
        $this->template->destroy_block_vars('projects');
        $this->template->destroy_block_vars('project_list');

        // Captura o projeto solicitado (via clique no modal ou refresh)
        $requested_id = $this->request->variable('project_id', 0);

        $board_url = generate_board_url() . '/';
        $wsp_url_path = $board_url . 'ext/mundophpbb/workspace/styles/all';
        
        // Cache busting baseado na data de modificação do JS principal
        $js_path = $this->phpbb_root_path . 'ext/mundophpbb/workspace/styles/all/template/js/wsp_core.js';
        $wsp_version = (file_exists($js_path)) ? filemtime($js_path) : time();

        // 2. Variáveis de Ambiente para a IDE
        $this->template->assign_vars([
            'T_WSP_ASSETS'            => $wsp_url_path,
            'T_WSP_ACE_PATH'          => $wsp_url_path . '/template/ace',
            'WSP_VERSION'             => $wsp_version,
            'U_WORKSPACE_MAIN'        => $this->helper->route('mundophpbb_workspace_main'),
            'U_WORKSPACE_LOAD'        => $this->helper->route('mundophpbb_workspace_load_file', [], false),
            'U_WORKSPACE_SAVE'        => $this->helper->route('mundophpbb_workspace_save_file', [], false),
            'U_WORKSPACE_ADD'         => $this->helper->route('mundophpbb_workspace_add_project', [], false),
            'U_WORKSPACE_ADD_FILE'    => $this->helper->route('mundophpbb_workspace_add_file', [], false),
            'U_WORKSPACE_UPLOAD'      => $this->helper->route('mundophpbb_workspace_upload_file', [], false),
            'U_WORKSPACE_RENAME'      => $this->helper->route('mundophpbb_workspace_rename_file', [], false),
            'U_WORKSPACE_DELETE_FILE' => $this->helper->route('mundophpbb_workspace_delete_file', [], false),
            'U_WORKSPACE_DELETE'      => $this->helper->route('mundophpbb_workspace_delete_project', [], false),
            'U_WORKSPACE_DIFF'        => $this->helper->route('mundophpbb_workspace_generate_diff', [], false),
            'U_WORKSPACE_SEARCH'      => $this->helper->route('mundophpbb_workspace_search_project', [], false),
            'U_WORKSPACE_REPLACE'     => $this->helper->route('mundophpbb_workspace_replace_project', [], false),
            'ACTIVE_PROJECT_ID'       => (int) $requested_id,
        ]);

        // 3. BUSCA LISTA DE PROJETOS (Para alimentar o Modal de "Abrir Projeto")
        $sql = 'SELECT project_id, project_name FROM ' . $this->table_prefix . 'workspace_projects 
                WHERE user_id = ' . (int) $this->user->data['user_id'] . '
                ORDER BY project_name ASC';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $this->template->assign_block_vars('project_list', [
                'ID'   => $row['project_id'],
                'NAME' => $row['project_name'],
            ]);
        }
        $this->db->sql_freeresult($result);

        // 4. BUSCA ARQUIVOS DO PROJETO SELECIONADO (Para alimentar a Sidebar)
        if ($requested_id > 0) {
            $sql = 'SELECT p.project_id, p.project_name, f.file_id, f.file_name, f.file_type
                FROM ' . $this->table_prefix . 'workspace_projects p
                LEFT JOIN ' . $this->table_prefix . 'workspace_files f ON p.project_id = f.project_id
                WHERE p.user_id = ' . (int) $this->user->data['user_id'] . '
                AND p.project_id = ' . (int) $requested_id . '
                ORDER BY f.file_name ASC';
            
            $result = $this->db->sql_query($sql);
            $current_project = 0;
            while ($row = $this->db->sql_fetchrow($result)) {
                if ($current_project != $row['project_id']) {
                    $current_project = $row['project_id'];
$this->template->assign_block_vars('projects', [
    'ID'          => $row['project_id'],
    'NAME'        => $row['project_name'],
    // Ajustado para mundophpbb_workspace_export_zip conforme o routing.yml
    'U_DOWNLOAD'  => $this->helper->route('mundophpbb_workspace_export_zip', ['project_id' => $row['project_id']]),
    'U_CHANGELOG' => $this->helper->route('mundophpbb_workspace_generate_changelog', ['project_id' => $row['project_id']]),
]);
                }
                if ($row['file_id']) {
                    $this->template->assign_block_vars('projects.files', [
                        'F_ID'   => (int) $row['file_id'],
                        'F_NAME' => $row['file_name'],
                        'F_TYPE' => strtolower((string)$row['file_type']),
                    ]);
                }
            }
            $this->db->sql_freeresult($result);
        }

        // 5. Renderiza o Template Principal
        return $this->helper->render('workspace_main.html', $this->user->lang('WSP_TITLE'));
    }
}