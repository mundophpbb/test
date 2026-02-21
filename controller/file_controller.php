<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class file_controller extends base_controller
{
    /**
     * Realiza o upload de arquivos preservando caminhos de pastas (full_path)
     * + BLINDAGEM: Suporte a decodificação Base64 para evitar bloqueios de Firewall (ModSecurity 503)
     */
    public function upload_file()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $project_id = (int) $this->request->variable('project_id', 0);
        $access = $this->assert_project_access($project_id, 'edit');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $filename = $this->request->variable('full_path', '', true);
        $file     = $this->request->file('file');
        
        // Novos campos vindos do seu wsp_upload.js blindado
        $encoded_content = $this->request->variable('file_content', '', true);
        $is_encoded      = $this->request->variable('is_encoded', 0);

        if (empty($filename) && isset($file['name']))
        {
            $filename = $file['name'];
        }

        $filename = $this->sanitize_rel_path($filename);
        if ($filename === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        if (!$this->is_placeholder_path($filename) && !$this->is_extension_allowed($filename))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_EXT')]);
        }

        // --- LÓGICA DE CAPTURA DE CONTEÚDO (Híbrida: Base64 ou Binário) ---
        if ($is_encoded && !empty($encoded_content))
        {
            // Remove o prefixo data:image/png;base64, ou data:text/plain;base64,
            if (preg_match('/^data:.*?;base64,/', $encoded_content)) {
                $encoded_content = preg_replace('/^data:.*?;base64,/', '', $encoded_content);
            }
            $content = base64_decode($encoded_content);
        }
        else if (isset($file['tmp_name']) && !empty($file['tmp_name']))
        {
            $content = @file_get_contents($file['tmp_name']);
        }
        else
        {
            return new JsonResponse(['success' => false, 'error' => 'Nenhum conteúdo recebido no upload.']);
        }

        if ($content === false)
        {
            return new JsonResponse(['success' => false, 'error' => 'Erro ao processar conteúdo do arquivo.']);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'txt';

        // Verifica se o arquivo já existe no projeto
        $sql = 'SELECT file_id, file_content 
                FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' 
                  AND file_name = "' . $this->db->sql_escape($filename) . '"';
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $this->db->sql_transaction('begin');

        if ($row)
        {
            if ($row['file_content'] !== $content)
            {
                $this->log_to_changelog($project_id, "Upload (Atualização): " . $filename, $row['file_content'], $content);
            }

            $sql_update = 'UPDATE ' . $this->table_prefix . 'workspace_files 
                           SET file_content = "' . $this->db->sql_escape($content) . '", 
                               file_time = ' . time() . ', 
                               file_type = "' . $this->db->sql_escape($ext) . '" 
                           WHERE file_id = ' . (int) $row['file_id'];
            $this->db->sql_query($sql_update);
        }
        else
        {
            $this->log_to_changelog($project_id, "Novo arquivo (Upload): " . $filename);

            $file_ary = [
                'project_id'   => (int) $project_id,
                'file_name'    => $filename,
                'file_content' => $content,
                'file_type'    => $ext,
                'file_time'    => time(),
            ];
            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $file_ary));
        }

        $this->db->sql_transaction('commit');
        return new JsonResponse(['success' => true]);
    }

    /**
     * Adiciona um novo arquivo vazio ou com template inicial
     */
    public function add_file()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $project_id = (int) $this->request->variable('project_id', 0);
        $filename   = trim($this->request->variable('name', '', true));

        $access = $this->assert_project_access($project_id, 'edit');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $filename = $this->sanitize_rel_path($filename);
        if (!$project_id || $filename === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        if (!$this->is_placeholder_path($filename) && !$this->is_extension_allowed($filename))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_EXT')]);
        }

        // Evita duplicidade
        $sql = 'SELECT file_id FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' 
                  AND file_name = "' . $this->db->sql_escape($filename) . '"';
        $result = $this->db->sql_query($sql);
        $exists = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($exists)
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_FILE_EXISTS')]);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'txt';
        $initial_content = "";

        if (!$this->is_placeholder_path($filename) && $ext === 'php')
        {
            $initial_content = "<?php\n\n";
        }

        $this->db->sql_transaction('begin');
        $this->log_to_changelog($project_id, "Novo arquivo: " . $filename);

        $file_ary = [
            'project_id'   => (int) $project_id,
            'file_name'    => $filename,
            'file_content' => $initial_content,
            'file_type'    => $ext,
            'file_time'    => time(),
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $file_ary));
        $file_id = (int) $this->db->sql_nextid();
        $this->db->sql_transaction('commit');

        return new JsonResponse(['success' => true, 'file_id' => $file_id]);
    }

    public function load_file()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $file_id = (int) $this->request->variable('file_id', 0);

        $access = $this->assert_file_access($file_id, 'view');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $sql = 'SELECT file_content, file_name, file_type 
                FROM ' . $this->table_prefix . 'workspace_files 
                WHERE file_id = ' . (int) $file_id;
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row)
        {
            return new JsonResponse([
                'success' => true,
                'content' => (string) html_entity_decode($row['file_content'], ENT_QUOTES, 'UTF-8'),
                'name'    => (string) $row['file_name'],
                'type'    => strtolower((string) $row['file_type']),
            ]);
        }

        return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_FILE_NOT_FOUND')]);
    }

    public function save_file()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $file_id  = (int) $this->request->variable('file_id', 0);
        $content  = $this->request->variable('content', '', true);

        $access = $this->assert_file_access($file_id, 'edit');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $sql_old = 'SELECT project_id, file_name, file_content 
                    FROM ' . $this->table_prefix . 'workspace_files 
                    WHERE file_id = ' . (int) $file_id;
        $result_old = $this->db->sql_query($sql_old);
        $row_old    = $this->db->sql_fetchrow($result_old);
        $this->db->sql_freeresult($result_old);

        $this->db->sql_transaction('begin');
        if ($row_old && $row_old['file_content'] !== $content)
        {
            $this->log_to_changelog((int) $row_old['project_id'], "Alterado: " . $row_old['file_name'], $row_old['file_content'], $content);
        }

        $sql = 'UPDATE ' . $this->table_prefix . 'workspace_files 
                SET file_content = "' . $this->db->sql_escape($content) . '", 
                    file_time = ' . time() . ' 
                WHERE file_id = ' . (int) $file_id;

        $this->db->sql_query($sql);
        $this->db->sql_transaction('commit');

        return new JsonResponse(['success' => true]);
    }

    public function rename_file()
    {
        $file_id  = (int) $this->request->variable('file_id', 0);
        $new_name = $this->sanitize_rel_path($this->request->variable('new_name', '', true));

        if ($new_name === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $access = $this->assert_file_access($file_id, 'rename');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        if (!$this->is_placeholder_path($new_name) && !$this->is_extension_allowed($new_name))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_EXT')]);
        }

        $project_id = $access['project_id'];
        
        // Busca nome antigo para o log
        $sql_old = 'SELECT file_name FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . $file_id;
        $result_old = $this->db->sql_query($sql_old);
        $row_old = $this->db->sql_fetchrow($result_old);
        $this->db->sql_freeresult($result_old);

        $this->db->sql_transaction('begin');
        if ($row_old) {
            $this->log_to_changelog($project_id, "Renomeado: " . $row_old['file_name'] . " -> " . $new_name);
        }

        $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files 
                              SET file_name = "' . $this->db->sql_escape($new_name) . '", 
                                  file_type = "' . $this->db->sql_escape(pathinfo($new_name, PATHINFO_EXTENSION) ?: 'txt') . '", 
                                  file_time = ' . time() . ' 
                              WHERE file_id = ' . $file_id);
        
        $this->db->sql_transaction('commit');
        return new JsonResponse(['success' => true]);
    }

    public function delete_file()
    {
        $file_id = (int) $this->request->variable('file_id', 0);
        $access = $this->assert_file_access($file_id, 'delete');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        // Busca nome para o log antes de deletar
        $sql_name = 'SELECT file_name FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . $file_id;
        $result_name = $this->db->sql_query($sql_name);
        $row_name = $this->db->sql_fetchrow($result_name);
        $this->db->sql_freeresult($result_name);

        $this->db->sql_transaction('begin');
        if ($row_name) {
            $this->log_to_changelog($access['project_id'], "Excluído: " . $row_name['file_name']);
        }

        $this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . $file_id);
        $this->db->sql_transaction('commit');

        return new JsonResponse(['success' => true]);
    }

    /**
     * Helper para logs no changelog.txt
     */
    private function log_to_changelog($project_id, $action, $old_content = null, $new_content = null)
    {
        $date = date('d/m/Y H:i');
        $log_entry = "[$date] $action\n";

        if ($old_content !== null && $new_content !== null)
        {
            $lib_path = $this->phpbb_root_path . 'ext/mundophpbb/workspace/lib/';
            if (file_exists($lib_path . 'Diff.php'))
            {
                require_once($lib_path . 'Diff.php');
                require_once($lib_path . 'Diff/Renderer/Abstract.php');
                require_once($lib_path . 'Diff/Renderer/Text/Unified.php');

                $diff = new \Diff(explode("\n", $old_content), explode("\n", $new_content));
                $renderer = new \Diff_Renderer_Text_Unified();
                $diff_text = $diff->render($renderer);

                if (!empty($diff_text))
                {
                    $log_entry .= "Diff:\n" . $diff_text . "\n";
                }
            }
        }

        $changelog_name = 'changelog.txt';
        $sql = 'SELECT file_id, file_content FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' AND file_name = "' . $this->db->sql_escape($changelog_name) . '"';
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row)
        {
            $new_changelog = $log_entry . str_repeat("-", 30) . "\n" . $row['file_content'];
            $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files SET file_content = "' . $this->db->sql_escape($new_changelog) . '", file_time = ' . time() . ' WHERE file_id = ' . (int) $row['file_id']);
        }
        else
        {
            $file_ary = [
                'project_id'   => (int) $project_id,
                'file_name'    => $changelog_name,
                'file_content' => $log_entry . str_repeat("-", 30) . "\n",
                'file_type'    => 'txt',
                'file_time'    => time(),
            ];
            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $file_ary));
        }
    }
}