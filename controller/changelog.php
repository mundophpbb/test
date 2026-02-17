<?php
namespace mundophpbb\workspace\controller;

use phpbb\request\request;
use phpbb\db\driver\driver_interface;
use phpbb\controller\helper;
use phpbb\language\language;

class changelog
{
    protected $db;
    protected $request;
    protected $helper;
    protected $language;
    protected $table_files;
    protected $table_projects;

    public function __construct(driver_interface $db, request $request, helper $helper, language $language, $table_files, $table_projects)
    {
        $this->db = $db;
        $this->request = $request;
        $this->helper = $helper;
        $this->language = $language;
        $this->table_files = $table_files;
        $this->table_projects = $table_projects;
    }

    public function generate($project_id)
    {
        // 1. Busca dados do projeto
        $sql = "SELECT project_name FROM " . $this->table_projects . " 
                WHERE project_id = " . (int) $project_id;
        $result = $this->db->sql_query($sql);
        $project = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$project) {
            return $this->helper->render_json(['success' => false, 'error' => 'Projeto não encontrado.']);
        }

        // 2. Gera o conteúdo do Changelog (Markdown)
        $date = date('d/m/Y H:i');
        $content = "# Changelog - " . $project['project_name'] . "\n";
        $content .= "Gerado automaticamente pelo Mundo phpBB Workspace em " . $date . "\n\n";
        $content .= "## [1.0.0] - Atualização de " . date('d/m/Y') . "\n";
        $content .= "### Arquivos atuais no projeto:\n";

        $sql = "SELECT file_name FROM " . $this->table_files . " 
                WHERE project_id = " . (int) $project_id . " 
                ORDER BY file_name ASC";
        $result = $this->db->sql_query($sql);
        
        while ($row = $this->db->sql_fetchrow($result)) {
            $content .= "- " . $row['file_name'] . "\n";
        }
        $this->db->sql_freeresult($result);

        // 3. Verifica se o changelog.md já existe no banco
        $sql = "SELECT file_id FROM " . $this->table_files . " 
                WHERE project_id = " . (int) $project_id . " 
                AND file_name = 'changelog.md'";
        $result = $this->db->sql_query($sql);
        $file = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($file) {
            // Atualiza existente
            $sql_ary = ['file_content' => $content];
            $sql = "UPDATE " . $this->table_files . " SET " . $this->db->sql_build_array('UPDATE', $sql_ary) . " 
                    WHERE file_id = " . (int) $file['file_id'];
            $this->db->sql_query($sql);
            
            $file_id = $file['file_id'];
            $is_new = false;
        } else {
            // Cria novo
            $sql_ary = [
                'project_id'   => (int) $project_id,
                'file_name'    => 'changelog.md',
                'file_content' => $content,
                'file_type'    => 'md'
            ];
            $this->db->sql_query("INSERT INTO " . $this->table_files . " " . $this->db->sql_build_array('INSERT', $sql_ary));
            
            $file_id = $this->db->sql_nextid();
            $is_new = true;
        }

        return $this->helper->render_json([
            'success' => true,
            'file_id' => $file_id,
            'is_new'  => $is_new
        ]);
    }
}