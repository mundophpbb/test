<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mundo phpBB Workspace - Tool Controller (v2.9)
 * Responsável por: Busca, Substituição, Diff, Changelog, Cache, Duplicação, Gist, Skeleton e Export ZIP.
 */
class tool_controller
{
    protected $helper;
    protected $db;
    protected $table_prefix;
    protected $request;
    protected $user;
    protected $auth;
    protected $phpbb_root_path;

    public function __construct(
        \phpbb\controller\helper $helper,
        $db,
        $table_prefix,
        \phpbb\request\request $request,
        \phpbb\user $user,
        \phpbb\auth\auth $auth,
        $phpbb_root_path
    ) {
        $this->helper = $helper;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
        $this->request = $request;
        $this->user = $user;
        $this->auth = $auth;
        $this->phpbb_root_path = $phpbb_root_path;
    }

    /**
     * Busca Global: Localiza termos com preview da linha.
     */
    public function search_project()
    {
        if (!$this->auth->acl_get('u_workspace_access')) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado.']);
        }

        $project_id = $this->request->variable('project_id', 0);
        $term = $this->request->variable('term', '', true);

        if (!$project_id || empty($term)) return new JsonResponse(['success' => false]);

        $sql = 'SELECT file_id, file_name, file_content FROM ' . $this->table_prefix . 'workspace_files
                WHERE project_id = ' . (int) $project_id . '
                AND file_content LIKE "%' . $this->db->sql_escape($term) . '%"';
        $result = $this->db->sql_query($sql);
        
        $results = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $lines = explode("\n", $row['file_content']);
            foreach ($lines as $index => $line) {
                if (stripos($line, $term) !== false) {
                    $results[] = [
                        'file_id'   => $row['file_id'],
                        'file_name' => $row['file_name'],
                        'line'      => $index + 1,
                        'preview'   => trim(htmlspecialchars($line))
                    ];
                    if (count($results) > 100) break 2;
                }
            }
        }
        $this->db->sql_freeresult($result);
        return new JsonResponse(['success' => true, 'results' => $results]);
    }

    /**
     * Substituição Global em massa.
     */
    public function replace_project()
    {
        if (!$this->auth->acl_get('u_workspace_access')) return new JsonResponse(['success' => false]);

        $project_id = $this->request->variable('project_id', 0);
        $search = $this->request->variable('search', '', true);
        $replace = $this->request->variable('replace', '', true);

        $sql = 'SELECT file_id, file_content FROM ' . $this->table_prefix . 'workspace_files 
                WHERE project_id = ' . (int) $project_id . ' 
                AND file_content LIKE "%' . $this->db->sql_escape($search) . '%"';
        $result = $this->db->sql_query($sql);
        
        $affected = 0;
        while ($row = $this->db->sql_fetchrow($result)) {
            $new_content = str_replace($search, $replace, $row['file_content']);
            $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files SET file_content = "' . $this->db->sql_escape($new_content) . '", file_time = ' . time() . ' WHERE file_id = ' . (int) $row['file_id']);
            $affected++;
        }
        $this->db->sql_freeresult($result);
        return new JsonResponse(['success' => true, 'affected_files' => $affected]);
    }

    /**
     * Limpeza de Cache nativa do phpBB.
     */
    public function purge_cache()
    {
        if (!$this->auth->acl_get('a_') && !$this->auth->acl_get('u_workspace_access')) return new JsonResponse(['success' => false]);
        global $cache;
        $cache->purge();
        return new JsonResponse(['success' => true]);
    }

    /**
     * Duplicar registro de arquivo.
     */
    public function duplicate_file()
    {
        $file_id = $this->request->variable('file_id', 0);
        $new_name = $this->request->variable('new_name', '', true);

        $sql = 'SELECT * FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . (int) $file_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row) return new JsonResponse(['success' => false]);

        unset($row['file_id']);
        $row['file_name'] = $new_name;
        $row['file_time'] = time();

        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $row));
        return new JsonResponse(['success' => true]);
    }

    /**
     * Exportar para Gist.
     */
    public function export_gist()
    {
        $file_id = $this->request->variable('file_id', 0);
        $sql = 'SELECT file_name, file_content FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . (int) $file_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row) return new JsonResponse(['success' => false]);

        $data = ['public' => true, 'files' => [$row['file_name'] => ['content' => $row['file_content']]]];
        $ch = curl_init('https://api.github.com/gists');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: phpBB-IDE', 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return (isset($response['html_url'])) ? new JsonResponse(['success' => true, 'url' => $response['html_url']]) : new JsonResponse(['success' => false]);
    }

    /**
     * Gerador de Esqueleto PSR-4.
     */
    public function generate_skeleton()
    {
        $project_id = $this->request->variable('project_id', 0);
        $vendor = preg_replace('/[^a-z0-9]/', '', $this->request->variable('vendor', '', true));
        $name = preg_replace('/[^a-z0-9]/', '', $this->request->variable('ext_name', '', true));

        $base = "ext/{$vendor}/{$name}/";
        $skeleton = [
            "{$base}composer.json" => json_encode(['name' => "{$vendor}/{$name}", 'type' => 'phpbb-extension'], JSON_PRETTY_PRINT),
            "{$base}config/services.yml" => "services:\n    {$vendor}.{$name}.listener:\n        class: {$vendor}\\{$name}\\event\\main_listener\n        tags: [{name: event.listener}]",
            "{$base}event/main_listener.php" => "<?php\nnamespace {$vendor}\\{$name}\\event;\nclass main_listener implements \Symfony\Component\EventDispatcher\EventSubscriberInterface\n{\n    public static function getSubscribedEvents() { return []; }\n}",
        ];

        foreach ($skeleton as $fname => $fcontent) {
            $sql_ary = ['project_id' => $project_id, 'file_name' => $fname, 'file_content' => $fcontent, 'file_type' => pathinfo($fname, PATHINFO_EXTENSION), 'file_time' => time()];
            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $sql_ary));
        }
        return new JsonResponse(['success' => true]);
    }

    /**
     * NOVO: Exportar Projeto como ZIP.
     */
    public function export_project_zip($project_id = 0)
    {
        if (!$this->auth->acl_get('u_workspace_access')) return;

        $project_id = (int) ($project_id ?: $this->request->variable('project_id', 0));
        
        $sql = 'SELECT project_name FROM ' . $this->table_prefix . 'workspace_projects WHERE project_id = ' . $project_id;
        $result = $this->db->sql_query($sql);
        $project = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$project) return;

        $zip = new \ZipArchive();
        $zip_name = preg_replace('/[^a-z0-9]/i', '_', $project['project_name']) . '.zip';
        $zip_path = $this->phpbb_root_path . 'store/' . $zip_name;

        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $sql = 'SELECT file_name, file_content FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $project_id;
            $result = $this->db->sql_query($sql);

            while ($row = $this->db->sql_fetchrow($result)) {
                $content = $row['file_content'];

                // Decodifica Imagens Base64 para binário real no ZIP
                if (strpos($content, 'data:image/') === 0) {
                    $base64_string = explode(',', $content)[1];
                    $content = base64_decode($base64_string);
                }

                $zip->addFromString($row['file_name'], $content);
            }
            $this->db->sql_freeresult($result);
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename=' . $zip_name);
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            exit;
        }
    }

    /**
     * Gerador de Changelog.
     */
    public function generate_changelog()
    {
        $project_id = $this->request->variable('project_id', 0);
        $sql = 'SELECT file_name, file_time FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $project_id . ' AND file_name != "CHANGELOG.md" ORDER BY file_time DESC';
        $result = $this->db->sql_query($sql);
        $log_content = "# CHANGELOG\n\n";
        while ($row = $this->db->sql_fetchrow($result)) {
            $log_content .= "- **" . $row['file_name'] . "**: " . $this->user->format_date($row['file_time']) . "\n";
        }
        $this->db->sql_freeresult($result);

        $sql = 'SELECT file_id FROM ' . $this->table_prefix . 'workspace_files WHERE project_id = ' . $project_id . ' AND file_name = "CHANGELOG.md"';
        $res = $this->db->sql_query($sql);
        $existing = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);

        if ($existing) {
            $this->db->sql_query('UPDATE ' . $this->table_prefix . 'workspace_files SET file_content = "' . $this->db->sql_escape($log_content) . '", file_time = ' . time() . ' WHERE file_id = ' . (int)$existing['file_id']);
            $file_id = $existing['file_id'];
        } else {
            $sql_ary = ['project_id' => $project_id, 'file_name' => 'CHANGELOG.md', 'file_content' => $log_content, 'file_type' => 'md', 'file_time' => time()];
            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $sql_ary));
            $file_id = $this->db->sql_nextid();
        }
        return new JsonResponse(['success' => true, 'file_id' => $file_id]);
    }
}