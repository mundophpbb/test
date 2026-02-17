<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mundo phpBB Workspace - Project Controller
 * Gerencia o ciclo de vida dos projetos e exportação ZIP.
 */
class project_controller
{
    protected $helper;
    protected $db;
    protected $table_prefix;
    protected $request;
    protected $user;
    protected $auth;

    public function __construct(
        \phpbb\controller\helper $helper,
        $db,
        $table_prefix,
        \phpbb\request\request $request,
        \phpbb\user $user,
        \phpbb\auth\auth $auth
    ) {
        $this->helper = $helper;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
        $this->request = $request;
        $this->user = $user;
        $this->auth = $auth;
    }

    /**
     * Adicionar novo projeto
     */
    public function add_project()
    {
        if (!$this->auth->acl_get('u_workspace_access')) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $name = trim($this->request->variable('name', '', true));
        if (empty($name)) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_NAME')]);
        }

        // Verifica se já existe projeto com esse nome para o usuário atual
        $sql = 'SELECT project_id FROM ' . $this->table_prefix . 'workspace_projects 
                WHERE project_name = "' . $this->db->sql_escape($name) . '" 
                AND user_id = ' . (int) $this->user->data['user_id'];
        $result = $this->db->sql_query($sql);
        $exists = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($exists) {
            return new JsonResponse(['success' => false, 'error' => 'Você já possui um projeto com este nome.']);
        }

        $sql_ary = [
            'project_name' => $name,
            'project_desc' => $this->user->lang('WSP_DEFAULT_DESC'),
            'project_time' => time(),
            'user_id'      => (int) $this->user->data['user_id']
        ];
        
        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_projects ' . $this->db->sql_build_array('INSERT', $sql_ary));

        return new JsonResponse([
            'success' => true, 
            'id'      => (int) $this->db->sql_nextid(), 
            'name'    => $name
        ]);
    }

    /**
     * Renomear projeto existente
     */
    public function rename_project()
    {
        if (!$this->auth->acl_get('u_workspace_access')) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $id = (int) $this->request->variable('project_id', 0);
        $new_name = trim($this->request->variable('new_name', '', true));

        if (!$id || empty($new_name)) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $sql = 'UPDATE ' . $this->table_prefix . 'workspace_projects 
                SET project_name = "' . $this->db->sql_escape($new_name) . '" 
                WHERE project_id = ' . $id . ' 
                AND user_id = ' . (int) $this->user->data['user_id'];
        $this->db->sql_query($sql);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Deletar projeto e todos os seus arquivos (Cascata manual)
     */
    public function delete_project()
    {
        if (!$this->auth->acl_get('u_workspace_access')) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $id = (int) $this->request->variable('project_id', 0);
        
        if (!$id) {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_ID')]);
        }

        // Garante que o projeto pertence ao usuário antes de deletar
        $sql = 'DELETE FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $id;
        $this->db->sql_query($sql);

        $sql = 'DELETE FROM ' . $this->table_prefix . 'workspace_projects 
                WHERE project_id = ' . $id . ' 
                AND user_id = ' . (int) $this->user->data['user_id'];
        $this->db->sql_query($sql);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Download do projeto em formato ZIP
     */
    public function download_project($project_id)
    {
        if (!class_exists('ZipArchive')) {
            trigger_error('ZipArchive não habilitado no servidor.', E_USER_ERROR);
        }

        if (!$this->auth->acl_get('u_workspace_access')) {
            trigger_error($this->user->lang('WSP_ERR_PERMISSION'), E_USER_ERROR);
        }

        $sql = 'SELECT project_name FROM ' . $this->table_prefix . 'workspace_projects 
                WHERE project_id = ' . (int) $project_id . ' 
                AND user_id = ' . (int) $this->user->data['user_id'];
        $result = $this->db->sql_query($sql);
        $project = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$project) {
            trigger_error('Projeto não encontrado ou acesso negado.', E_USER_ERROR);
        }

        $temp_file = tempnam(sys_get_temp_dir(), 'wsp');

        try {
            $zip = new \ZipArchive();
            if ($zip->open($temp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Falha ao criar arquivo ZIP temporário.');
            }

            $sql = 'SELECT file_name, file_content FROM ' . $this->table_prefix . 'workspace_files 
                    WHERE project_id = ' . (int) $project_id;
            $result = $this->db->sql_query($sql);
            
            while ($row = $this->db->sql_fetchrow($result)) {
                // Decodifica entidades para salvar o código "limpo" no ZIP
                $content = html_entity_decode((string)$row['file_content'], ENT_QUOTES, 'UTF-8');
                $zip->addFromString($row['file_name'], $content);
            }
            $this->db->sql_freeresult($result);
            $zip->close();

            $safe_name = str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $project['project_name']);

            // Limpa qualquer saída anterior para evitar corromper o ZIP
            if (ob_get_level()) ob_end_clean();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $safe_name . '.zip"');
            header('Content-Length: ' . filesize($temp_file));
            header('Pragma: no-cache');
            header('Expires: 0');
            
            readfile($temp_file);
            unlink($temp_file);
            exit;

        } catch (\Exception $e) {
            if (file_exists($temp_file)) unlink($temp_file);
            trigger_error('Erro ao gerar ZIP: ' . $e->getMessage(), E_USER_ERROR);
        }
    }
}