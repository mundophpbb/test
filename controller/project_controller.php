<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mundo phpBB Workspace - Project Controller
 * Gerencia a estrutura de projetos, pastas virtuais e exportação.
 */
class project_controller extends base_controller
{
    /**
     * Cria um novo projeto e inicializa o arquivo de histórico.
     */
    public function add_project()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $name = trim($this->request->variable('name', '', true));
        if ($name === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_NAME')]);
        }

        // Limita o tamanho do nome do projeto
        if (utf8_strlen($name) > 120)
        {
            $name = utf8_substr($name, 0, 120);
        }

        $sql_ary = [
            'project_name' => $name,
            'project_desc' => $this->user->lang('WSP_DEFAULT_DESC'),
            'project_time' => time(),
            'user_id'      => (int) $this->user->data['user_id'],
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_projects ' . $this->db->sql_build_array('INSERT', $sql_ary));
        $project_id = (int) $this->db->sql_nextid();

        // Inicializa o changelog.txt automaticamente
        if ($project_id && $this->is_extension_allowed('changelog.txt'))
        {
            $header  = "==================================================\n";
            $header .= "  PROJETO CRIADO EM " . date('d/m/Y H:i') . "\n";
            $header .= "==================================================\n\n";

            $file_ary = [
                'project_id'   => $project_id,
                'file_name'    => 'changelog.txt',
                'file_content' => $header,
                'file_type'    => 'txt',
                'file_time'    => time(),
            ];

            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $file_ary));
        }

        return new JsonResponse(['success' => true, 'project_id' => $project_id]);
    }

    /**
     * Renomeia o projeto (apenas metadados).
     */
    public function rename_project()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        $new_name   = trim($this->request->variable('new_name', '', true));

        $access = $this->assert_project_access($project_id, 'manage');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        if ($new_name === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $this->db->sql_query('UPDATE ' . $this->table_prefix . "workspace_projects 
                              SET project_name = '" . $this->db->sql_escape($new_name) . "', project_time = " . time() . " 
                              WHERE project_id = " . $project_id);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Remove o projeto e todos os arquivos vinculados.
     */
    public function delete_project()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        
        $access = $this->assert_project_access($project_id, 'manage');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $this->db->sql_transaction('begin');
        $ok1 = $this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $project_id);
        $ok2 = $this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'workspace_projects WHERE project_id = ' . $project_id);

        if ($ok1 && $ok2)
        {
            $this->db->sql_transaction('commit');
            return new JsonResponse(['success' => true]);
        }

        $this->db->sql_transaction('rollback');
        return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_DELETE_FAILED')]);
    }

    /**
     * Exclui uma pasta virtual e todo o seu conteúdo recursivamente.
     */
    public function delete_folder()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        $path       = $this->sanitize_rel_path($this->request->variable('path', '', true));

        $access = $this->assert_project_access($project_id, 'delete');
        if (!$access['ok'] || $path === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        // Garante que o seletor LIKE atinja apenas subitens da pasta (ex: pasta/...)
        $prefix = (substr($path, -1) !== '/') ? $path . '/' : $path;
        $escaped_prefix = $this->db->sql_escape($prefix);

        $this->db->sql_transaction('begin');
        
        $sql = 'DELETE FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . $project_id . ' 
                  AND (file_name LIKE "' . $escaped_prefix . '%" 
                       OR file_name = "' . $this->db->sql_escape($path) . '")';

        $this->db->sql_query($sql);
        $this->db->sql_transaction('commit');

        return new JsonResponse(['success' => true]);
    }

    /**
     * Renomeia uma pasta virtual e atualiza o caminho de todos os arquivos filhos.
     */
    public function rename_folder()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        $old_path = trim($this->request->variable('old_path', '', true), '/');
        $new_path = trim($this->request->variable('new_path', '', true), '/');

        $access = $this->assert_project_access($project_id, 'rename');
        
        if (!$access['ok'] || $old_path === '' || $new_path === '' || $old_path === $new_path)
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $old_prefix = $old_path . '/';
        $new_prefix = $new_path . '/';

        if (strpos($new_prefix, $old_prefix) === 0)
        {
            return new JsonResponse(['success' => false, 'error' => 'Operação inválida: destino dentro da origem.']);
        }

        $sql = 'SELECT file_id, file_name FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . $project_id . ' 
                  AND (file_name LIKE "' . $this->db->sql_escape($old_prefix) . '%" 
                       OR file_name = "' . $this->db->sql_escape($old_path) . '")';
        
        $result = $this->db->sql_query($sql);
        $files = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);

        if (empty($files))
        {
            return new JsonResponse(['success' => false, 'error' => 'Pasta vazia ou não encontrada.']);
        }

        $this->db->sql_transaction('begin');
        try
        {
            foreach ($files as $f)
            {
                $current_name = $f['file_name'];
                
                if ($current_name === $old_path)
                {
                    $target_name = $new_path;
                }
                else
                {
                    $relative_part = substr($current_name, strlen($old_prefix));
                    $target_name = $new_prefix . $relative_part;
                }

                $new_ext = strtolower(pathinfo($target_name, PATHINFO_EXTENSION)) ?: 'txt';

                $sql_up = 'UPDATE ' . $this->table_prefix . "workspace_files 
                           SET file_name = '" . $this->db->sql_escape($target_name) . "', 
                               file_type = '" . $this->db->sql_escape($new_ext) . "', 
                               file_time = " . time() . " 
                           WHERE file_id = " . (int) $f['file_id'];
                $this->db->sql_query($sql_up);
            }

            $this->log_folder_rename($project_id, $old_path, $new_path);
            $this->db->sql_transaction('commit');
        }
        catch (\Exception $e)
        {
            $this->db->sql_transaction('rollback');
            return new JsonResponse(['success' => false, 'error' => 'Erro crítico ao processar renomeação.']);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * Gera e entrega um pacote ZIP do projeto.
     * Corrigido: Captura do ID de forma blindada para evitar WSP_ERR_INVALID_DATA.
     */
    public function download_project($project_id = 0)
    {
        // 1. Resolve o ID do projeto (Argumento da rota > Variável Global)
        $p_id = (int) $project_id;
        if ($p_id <= 0)
        {
            $p_id = $this->request->variable('project_id', 0);
        }

        // Fallback de segurança para capturar 'p' direto da URL caso as rotas amigáveis falhem
        if ($p_id <= 0)
        {
             $p_id = (int) $this->request->variable('p', 0);
        }

        // 2. Validação final do dado
        if ($p_id <= 0)
        {
            trigger_error($this->user->lang('WSP_ERR_INVALID_DATA'));
        }

        // 3. Validação de acesso
        $access = $this->assert_project_access($p_id, 'view');
        if (!$access['ok'])
        {
            trigger_error($access['error']);
        }

        $sql = 'SELECT project_name FROM ' . $this->table_prefix . 'workspace_projects WHERE project_id = ' . $p_id;
        $result = $this->db->sql_query($sql);
        $project = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$project) trigger_error($this->user->lang('WSP_ERR_INVALID_ID'));

        if (!class_exists('ZipArchive'))
        {
            trigger_error('ZipArchive não disponível no servidor PHP.');
        }

        $zip = new \ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'wsp');

        if ($zip->open($temp_file, \ZipArchive::CREATE) !== true)
        {
            trigger_error('Não foi possível gerar o arquivo temporário.');
        }

        $sql = 'SELECT file_name, file_content FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $p_id;
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result))
        {
            if (basename($row['file_name']) === '.placeholder') continue;
            $zip->addFromString($row['file_name'], $row['file_content']);
        }
        $this->db->sql_freeresult($result);
        $zip->close();

        $download_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $project['project_name']) . '.zip';

        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($temp_file));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($temp_file);
        unlink($temp_file);
        exit;
    }

    /**
     * Registra a alteração de pasta no log central do projeto.
     */
    private function log_folder_rename($project_id, $old_path, $new_path)
    {
        $date = date('d/m/Y H:i');
        $log_entry = "[$date] Pasta movida/renomeada: {$old_path} -> {$new_path}\n";

        $sql = 'SELECT file_id, file_content FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' AND file_name = "changelog.txt"';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row)
        {
            $new_content = $log_entry . str_repeat("-", 30) . "\n" . $row['file_content'];
            $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files 
                                  SET file_content = "' . $this->db->sql_escape($new_content) . '", 
                                      file_time = ' . time() . ' 
                                  WHERE file_id = ' . (int) $row['file_id']);
        }
    }
}