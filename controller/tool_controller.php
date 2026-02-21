<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mundo phpBB Workspace - Tool Controller
 * Centraliza utilitários como busca/substituição, diffs, gestão de changelog e cache.
 */
class tool_controller extends base_controller
{
    /**
     * Procura um termo em todos os arquivos de um projeto (Busca Global)
     */
    public function search_project()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $project_id   = (int) $this->request->variable('project_id', 0);
        $search_term  = $this->request->variable('search', '', true);

        if (!$project_id || $search_term === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $access = $this->assert_project_access($project_id, 'view');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $like = '%' . $this->db->sql_escape($search_term) . '%';

        $sql = 'SELECT file_id, file_name 
                FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . $project_id . " 
                  AND file_content LIKE '" . $like . "'";

        $result  = $this->db->sql_query($sql);
        $matches = [];

        while ($row = $this->db->sql_fetchrow($result))
        {
            $matches[] = [
                'id'   => (int) $row['file_id'], 
                'name' => (string) $row['file_name']
            ];
        }
        $this->db->sql_freeresult($result);

        return new JsonResponse(['success' => true, 'matches' => $matches]);
    }

    /**
     * Substitui termos em arquivos (projeto inteiro ou arquivo único)
     */
    public function replace_project()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $project_id    = (int) $this->request->variable('project_id', 0);
        $file_id       = (int) $this->request->variable('file_id', 0); // Se 0, faz no projeto todo
        $search_term   = $this->request->variable('search', '', true);
        $replace_term  = $this->request->variable('replace', '', true);

        if (!$project_id || $search_term === '')
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_INVALID_DATA')]);
        }

        $access = $this->assert_project_access($project_id, 'edit');
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        // Monta o filtro SQL
        $sql_where = 'project_id = ' . $project_id;
        if ($file_id > 0)
        {
            $sql_where .= ' AND file_id = ' . $file_id;
        }

        $like = '%' . $this->db->sql_escape($search_term) . '%';
        $sql = 'SELECT file_id, file_content, file_name 
                FROM ' . $this->table_prefix . 'workspace_files 
                WHERE ' . $sql_where . " 
                  AND file_content LIKE '" . $like . "'";

        $result = $this->db->sql_query($sql);
        $updated_count = 0;

        while ($row = $this->db->sql_fetchrow($result))
        {
            // Ignora o changelog.txt na substituição em massa para evitar logs infinitos
            if ($row['file_name'] === 'changelog.txt' && $file_id === 0)
            {
                continue;
            }

            $new_content = str_replace($search_term, $replace_term, $row['file_content']);

            if ($new_content !== $row['file_content'])
            {
                $sql_update = 'UPDATE ' . $this->table_prefix . 'workspace_files 
                               SET file_content = "' . $this->db->sql_escape($new_content) . '", 
                                   file_time = ' . time() . ' 
                               WHERE file_id = ' . (int) $row['file_id'];
                $this->db->sql_query($sql_update);
                
                $this->log_to_changelog_internal($project_id, "Substituição: '{$search_term}' por '{$replace_term}' em {$row['file_name']}");
                $updated_count++;
            }
        }
        $this->db->sql_freeresult($result);

        return new JsonResponse(['success' => true, 'updated' => $updated_count]);
    }

    /**
     * Adiciona um cabeçalho de Consolidação/Versão ao changelog
     */
    public function generate_changelog()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        $access = $this->assert_project_access($project_id, 'edit');
        
        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $sql = 'SELECT file_id, file_content FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . $project_id . ' AND file_name = "changelog.txt"';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return new JsonResponse(['success' => false, 'error' => 'Changelog não encontrado.']);
        }

        $date_str = date('d/m/Y H:i');
        $header  = "\n" . str_repeat("#", 50) . "\n";
        $header .= "# CONSOLIDAÇÃO DE VERSÃO - " . $date_str . "\n";
        $header .= str_repeat("#", 50) . "\n\n";

        $final_content = $header . $row['file_content'];

        $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files 
                              SET file_content = "' . $this->db->sql_escape($final_content) . '", 
                                  file_time = ' . time() . ' 
                              WHERE file_id = ' . (int) $row['file_id']);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Limpa (Erase) o conteúdo do histórico
     */
    public function clear_changelog()
    {
        $project_id = (int) $this->request->variable('project_id', 0);
        $access = $this->assert_project_access($project_id, 'edit');

        if (!$access['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $access['error']]);
        }

        $header  = "==================================================\n";
        $header .= "  HISTÓRICO REINICIADO EM " . date('d/m/Y H:i') . "\n";
        $header .= "==================================================\n\n";

        $sql = 'UPDATE ' . $this->table_prefix . 'workspace_files 
                SET file_content = "' . $this->db->sql_escape($header) . '", 
                    file_time = ' . time() . ' 
                WHERE project_id = ' . $project_id . ' AND file_name = "changelog.txt"';
        
        $this->db->sql_query($sql);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Gera comparação Diff entre dois arquivos para exibição em BBCode
     */
    public function generate_diff()
    {
        $v1_id = (int) $this->request->variable('original_id', 0);
        $v2_id = (int) $this->request->variable('modified_id', 0);
        $filename = $this->request->variable('filename', 'arquivo.php', true);

        $a1 = $this->assert_file_access($v1_id, 'view');
        $a2 = $this->assert_file_access($v2_id, 'view');

        if (!$a1['ok'] || !$a2['ok'])
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        $v1 = $this->get_file_content($v1_id);
        $v2 = $this->get_file_content($v2_id);

        $lib_path = $this->phpbb_root_path . 'ext/mundophpbb/workspace/lib/';

        if (!file_exists($lib_path . 'Diff.php'))
        {
            return new JsonResponse(['success' => false, 'error' => 'Biblioteca Diff não encontrada.']);
        }

        require_once($lib_path . 'Diff.php');
        require_once($lib_path . 'Diff/Renderer/Abstract.php');
        require_once($lib_path . 'Diff/Renderer/Text/Unified.php');

        $diff = new \Diff(explode("\n", (string) $v1), explode("\n", (string) $v2));
        $renderer = new \Diff_Renderer_Text_Unified();
        $diff_output = $diff->render($renderer);

        if (empty(trim($diff_output)))
        {
            return new JsonResponse(['success' => true, 'bbcode' => 'Nenhuma diferença detectada.', 'filename' => $filename]);
        }

        return new JsonResponse([
            'success' => true, 
            'bbcode'  => "[diff=$filename]\n" . $diff_output . "\n[/diff]", 
            'filename' => $filename
        ]);
    }

    /**
     * Limpa o cache do phpBB
     */
    public function refresh_cache()
    {
        if (!$this->auth->acl_get('u_workspace_access'))
        {
            return new JsonResponse(['success' => false, 'error' => $this->user->lang('WSP_ERR_PERMISSION')]);
        }

        global $phpbb_container;
        try 
        {
            $phpbb_container->get('cache')->purge();
            return new JsonResponse(['success' => true]);
        } 
        catch (\Exception $e) 
        {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function get_file_content($file_id)
    {
        $sql = 'SELECT file_content FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . (int) $file_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return $row ? (string) $row['file_content'] : '';
    }

    private function log_to_changelog_internal($project_id, $message)
    {
        $date = date('d/m/Y H:i');
        $log_entry = "[$date] $message\n";

        $sql = 'SELECT file_id, file_content FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' AND file_name = "changelog.txt"';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row)
        {
            $new_content = $log_entry . $row['file_content'];
            $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files 
                                  SET file_content = "' . $this->db->sql_escape($new_content) . '", 
                                      file_time = ' . time() . ' 
                                  WHERE file_id = ' . (int) $row['file_id']);
        }
    }
}