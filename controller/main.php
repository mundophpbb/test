<?php
namespace mundophpbb\workspace\controller;

/**
 * Mundo phpBB Workspace - Main Controller
 * Gerencia a renderização da IDE com foco em Projeto Ativo.
 */
class main extends base_controller
{
    /** @var bool Quando não há projeto ativo, carregar arquivos de todos os projetos (modo antigo) */
    protected $load_files_when_no_active_project = false;

    public function handle()
    {
        // Permissão geral para acessar o Workspace
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            trigger_error($this->user->lang('WSP_ERR_PERMISSION', $this->user->lang('ACL_U_WORKSPACE_ACCESS')));
        }

        $this->user->add_lang_ext('mundophpbb/workspace', 'workspace');

        // Pega o ID do projeto ativo pela URL (?p=123)
        $active_p_id = $this->request->variable('p', 0);

        // Segurança e Funcionalidade: Valida acesso e gerencia Download
        if ($active_p_id > 0)
        {
            $access = $this->assert_project_access($active_p_id);
            if (!$access['ok'])
            {
                // Se não tiver acesso, reseta para 0 para evitar vazamento de dados
                $active_p_id = 0;
            }
            else
            {
                // Verifica se o usuário solicitou o download do projeto via link direto
                if ($this->request->variable('download', 0))
                {
                    // Encaminha para o método de download
                    return $this->download_project_proxy($active_p_id);
                }
            }
        }

        // Limpa variáveis de bloco para evitar duplicidade em refresh via AJAX
        $this->template->destroy_block_vars('projects');

        // Injeta assets (CSS/JS com cache busting) e mapeamento de rotas para o objeto wspVars
        $this->assign_assets_and_routes($active_p_id);

        // Lista todos os projetos onde o usuário é dono ou colaborador
        $projects = $this->fetch_user_projects((int) $this->user->data['user_id']);

        foreach ($projects as $row)
        {
            $project_id = (int) $row['project_id'];
            $is_active  = ($active_p_id > 0 && $project_id === (int) $active_p_id);

            $this->template->assign_block_vars('projects', [
                'ID'         => $project_id,
                'NAME'       => $row['project_name'],
                'IS_ACTIVE'  => $is_active,
                // Rota de download exclusiva para cada item da lista
                'U_DOWNLOAD' => $this->helper->route('mundophpbb_workspace_download', ['project_id' => $project_id]),
            ]);

            // PERFORMANCE: Carregar arquivos apenas do projeto que está aberto
            if ($this->should_load_files_for_project($active_p_id, $is_active))
            {
                $files = $this->fetch_project_files($project_id);

                foreach ($files as $f_row)
                {
                    // F_PATH: Caminho completo (ex: ext/mundophpbb/main.php)
                    // F_NAME: basename para exibição visual no nó final
                    $this->template->assign_block_vars('projects.files', [
                        'F_ID'   => (int) $f_row['file_id'],
                        'F_NAME' => basename($f_row['file_name']), 
                        'F_PATH' => $f_row['file_name'],           
                        'F_TYPE' => strtolower($f_row['file_type']),
                    ]);
                }
            }
        }

        $response = $this->helper->render('workspace_main.html', $this->user->lang('WSP_TITLE'));

        // Prevenção agressiva de cache para garantir que o editor sempre carregue a versão mais recente do banco
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Helper para disparar o download através do controller especializado.
     * Corrigido: Agora passa o project_id explicitamente para evitar WSP_ERR_INVALID_DATA.
     */
    protected function download_project_proxy($project_id)
    {
        $id = (int) $project_id;
        if ($id <= 0)
        {
             trigger_error($this->user->lang('WSP_ERR_INVALID_DATA'));
        }

        return redirect($this->helper->route('mundophpbb_workspace_download', ['project_id' => $id]));
    }

    protected function assign_assets_and_routes($active_p_id)
    {
        $board_url    = generate_board_url() . '/';
        $wsp_url_path = $board_url . 'ext/mundophpbb/workspace/styles/all';

        // Geração de versão automática baseada em mtime
        $js_path = $this->phpbb_root_path . 'ext/mundophpbb/workspace/styles/all/template/js/wsp_core.js';
        $wsp_js_version = (file_exists($js_path)) ? filemtime($js_path) : time();

        $css_path = $this->phpbb_root_path . 'ext/mundophpbb/workspace/styles/all/theme/workspace.css';
        $wsp_css_version = (file_exists($css_path)) ? filemtime($css_path) : time();

        $this->template->assign_vars([
            'T_WSP_ASSETS'       => $wsp_url_path,
            'T_WSP_ACE_PATH'     => $wsp_url_path . '/template/ace',
            'WSP_JS_VERSION'     => $wsp_js_version,
            'WSP_CSS_VERSION'    => $wsp_css_version,
            'ACTIVE_PROJECT_ID'  => (int) $active_p_id,
            'WSP_ALLOWED_EXT'    => implode(',', $this->allowed_extensions),
            'WSP_ROOT_LABEL'     => $this->user->lang('WSP_ROOT'), 

            // Mapeamento de Rotas para o AJAX
            'U_WORKSPACE_MAIN'           => $this->helper->route('mundophpbb_workspace_main'),
            'U_WORKSPACE_LOAD'           => $this->helper->route('mundophpbb_workspace_load', [], false),
            'U_WORKSPACE_SAVE'           => $this->helper->route('mundophpbb_workspace_save', [], false),
            'U_WORKSPACE_ADD'            => $this->helper->route('mundophpbb_workspace_add_project', [], false),
            'U_WORKSPACE_ADD_FILE'       => $this->helper->route('mundophpbb_workspace_add_file', [], false),
            'U_WORKSPACE_UPLOAD'         => $this->helper->route('mundophpbb_workspace_upload', [], false),
            'U_WORKSPACE_RENAME'         => $this->helper->route('mundophpbb_workspace_rename_file', [], false),
            'U_WORKSPACE_RENAME_PROJECT' => $this->helper->route('mundophpbb_workspace_rename_project', [], false),
            'U_WORKSPACE_RENAME_FOLDER'  => $this->helper->route('mundophpbb_workspace_rename_folder', [], false),
            'U_WORKSPACE_DELETE_FILE'    => $this->helper->route('mundophpbb_workspace_delete_file', [], false),
            'U_WORKSPACE_DELETE'         => $this->helper->route('mundophpbb_workspace_delete_project', [], false),
            'U_WORKSPACE_DELETE_FOLDER'  => $this->helper->route('mundophpbb_workspace_delete_folder', [], false),
            'U_WORKSPACE_CHANGELOG'      => $this->helper->route('mundophpbb_workspace_changelog', [], false),
            'U_WORKSPACE_CLEAR_CHANGELOG' => $this->helper->route('mundophpbb_workspace_clear_changelog', [], false),
            'U_WORKSPACE_DIFF'           => $this->helper->route('mundophpbb_workspace_diff', [], false),
            'U_WORKSPACE_SEARCH'         => $this->helper->route('mundophpbb_workspace_search', [], false),
            'U_WORKSPACE_REPLACE'        => $this->helper->route('mundophpbb_workspace_replace', [], false),
            'U_WORKSPACE_REFRESH_CACHE'  => $this->helper->route('mundophpbb_workspace_refresh_cache', [], false),
            // Passamos project_id: 0 como padrão para a rota global
            'U_WORKSPACE_DOWNLOAD'       => $this->helper->route('mundophpbb_workspace_download', ['project_id' => 0], false),
        ]);
    }

    protected function fetch_user_projects($user_id)
    {
        $sql = 'SELECT project_id, project_name
                FROM ' . $this->table_prefix . "workspace_projects
                WHERE user_id = " . (int) $user_id . '
                ORDER BY project_name ASC';

        $result = $this->db->sql_query($sql);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    protected function fetch_project_files($project_id)
    {
        $sql = 'SELECT file_id, file_name, file_type
                FROM ' . $this->table_prefix . 'workspace_files
                WHERE project_id = ' . (int) $project_id . '
                ORDER BY file_name ASC';

        $result = $this->db->sql_query($sql);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    protected function should_load_files_for_project($active_p_id, $is_active)
    {
        if ((int) $active_p_id > 0)
        {
            return (bool) $is_active;
        }

        return (bool) $this->load_files_when_no_active_project;
    }
}