<?php
namespace mundophpbb\workspace\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mundo phpBB Workspace - File Controller (v2.9)
 * Gerencia CRUD, Duplicação, Renomeação e Upload Seguro (Base64 para Imagens).
 */
class file_controller
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
     * Adicionar novo arquivo ou pasta
     */
    public function add_file()
    {
        if (!$this->auth->acl_get('u_workspace_access')) return new JsonResponse(['success' => false]);

        $project_id = $this->request->variable('project_id', 0);
        $filename   = trim($this->request->variable('name', '', true));

        $is_folder = (substr($filename, -1) === '/');
        $ext = $is_folder ? 'folder' : (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'txt');

        $sql_ary = [
            'project_id'   => (int) $project_id,
            'file_name'    => (string) $filename,
            'file_content' => (string) ($is_folder ? '' : $this->get_boilerplate($ext, $filename)),
            'file_type'    => (string) $ext,
            'file_time'    => time()
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $sql_ary));
        return new JsonResponse(['success' => true, 'file_id' => $this->db->sql_nextid()]);
    }

    /**
     * Carregar conteúdo (Texto ou Base64 para Imagens)
     */
    public function load_file()
    {
        $file_id = $this->request->variable('file_id', 0);
        $sql = 'SELECT * FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . (int) $file_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row) return new JsonResponse(['success' => false]);

        return new JsonResponse([
            'success' => true,
            'content' => $row['file_content'],
            'name'    => $row['file_name'],
            'type'    => $row['file_type']
        ]);
    }

    /**
     * Salvar alterações (Apenas para texto)
     */
    public function save_file()
    {
        if (!$this->auth->acl_get('u_workspace_access')) return new JsonResponse(['success' => false]);

        $file_id = $this->request->variable('file_id', 0);
        $content = $this->request->variable('content', '', true);

        $sql = 'UPDATE ' . $this->table_prefix . 'workspace_files SET file_content = "' . $this->db->sql_escape($content) . '", file_time = ' . time() . ' WHERE file_id = ' . (int) $file_id;
        $this->db->sql_query($sql);
        return new JsonResponse(['success' => true]);
    }

    /**
     * Duplicar Arquivo
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
     * Renomear
     */
    public function rename_file()
    {
        $file_id = $this->request->variable('file_id', 0);
        $new_name = $this->request->variable('new_name', '', true);

        $sql = 'UPDATE ' . $this->table_prefix . 'workspace_files SET file_name = "' . $this->db->sql_escape($new_name) . '" WHERE file_id = ' . (int) $file_id;
        $this->db->sql_query($sql);
        return new JsonResponse(['success' => true]);
    }

    /**
     * Upload Seguro (Converte Imagens p/ Base64)
     */
    public function upload_file()
    {
        $project_id = $this->request->variable('project_id', 0);
        $files = $this->request->file('files');
        $count = 0;

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $name = $file['name'];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $content = file_get_contents($file['tmp_name']);

                // Solução robusta: Imagens viram texto seguro para o BD
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp'])) {
                    $content = "data:image/{$ext};base64," . base64_encode($content);
                }

                $sql_ary = [
                    'project_id'   => (int) $project_id,
                    'file_name'    => $name,
                    'file_content' => $content,
                    'file_type'    => $ext,
                    'file_time'    => time()
                ];
                $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'workspace_files ' . $this->db->sql_build_array('INSERT', $sql_ary));
                $count++;
            }
        }
        return new JsonResponse(['success' => true, 'count' => $count]);
    }

    public function delete_file()
    {
        $file_id = $this->request->variable('file_id', 0);
        $this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'workspace_files WHERE file_id = ' . (int) $file_id);
        return new JsonResponse(['success' => true]);
    }

    private function get_boilerplate($ext, $filename)
    {
        switch ($ext) {
            case 'php': return "<?php\n/**\n * Arquivo: $filename\n */\n\n";
            case 'js':  return "'use strict';\n\n";
            case 'css': return "/* Estilos para: $filename */\n\n";
            default:    return "";
        }
    }
}