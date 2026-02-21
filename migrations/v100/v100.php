<?php
namespace mundophpbb\workspace\migrations\v100;

/**
 * Mundo phpBB Workspace - Migration v100
 * Define a estrutura de dados para Projetos, Arquivos e Permissões da IDE.
 */
class v100 extends \phpbb\db\migration\migration
{
    /**
     * Define as tabelas e colunas
     */
    public function update_schema()
    {
        return [
            'add_tables' => [
                // Tabela de Projetos
                $this->table_prefix . 'workspace_projects' => [
                    'COLUMNS' => [
                        'project_id'     => ['UINT', null, 'auto_increment'],
                        'project_name'   => ['VCHAR:255', ''],
                        'project_desc'   => ['TEXT_UNI', ''],
                        'project_time'   => ['TIMESTAMP', 0],
                        'user_id'        => ['UINT', 0], // Criador/Dono

                        // Controle de Bloqueio (Colaboração)
                        'project_locked' => ['BOOL', 0],
                        'locked_by'      => ['UINT', 0],
                        'locked_time'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'project_id',
                    'KEYS' => [
                        'user_id'    => ['INDEX', 'user_id'],
                        'locked_by'  => ['INDEX', 'locked_by'],
                    ],
                ],

                // Tabela de Arquivos (Conteúdo da IDE)
                $this->table_prefix . 'workspace_files' => [
                    'COLUMNS' => [
                        'file_id'      => ['UINT', null, 'auto_increment'],
                        'project_id'   => ['UINT', 0],
                        'file_name'    => ['VCHAR:255', ''], // Caminho completo (ex: src/main.php)
                        'file_content' => ['MTEXT_UNI', ''], // Suporta arquivos grandes de código
                        'file_type'    => ['VCHAR:50', 'php'],
                        'file_time'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'file_id',
                    'KEYS' => [
                        'project_id' => ['INDEX', 'project_id'],
                        'file_name'  => ['INDEX', 'file_name'], // Essencial para a renderização da Sidebar
                        'proj_file'  => ['INDEX', ['project_id', 'file_name']],
                    ],
                ],

                // Vínculo de Usuários e Projetos (Colaboração)
                $this->table_prefix . 'workspace_projects_users' => [
                    'COLUMNS' => [
                        'id'           => ['UINT', null, 'auto_increment'],
                        'project_id'   => ['UINT', 0],
                        'user_id'      => ['UINT', 0],
                        'role'         => ['VCHAR:32', 'collab'], // owner | collab | viewer
                        'added_by'     => ['UINT', 0],
                        'added_time'   => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'id',
                    'KEYS' => [
                        'project_id' => ['INDEX', 'project_id'],
                        'user_id'    => ['INDEX', 'user_id'],
                        'proj_user'  => ['UNIQUE', ['project_id', 'user_id']],
                    ],
                ],

                // Permissões Granulares (ACL Customizada)
                $this->table_prefix . 'workspace_permissions' => [
                    'COLUMNS' => [
                        'perm_id'      => ['UINT', null, 'auto_increment'],
                        'project_id'   => ['UINT', 0],
                        'entity_type'  => ['VCHAR:10', 'user'], // user | group
                        'entity_id'    => ['UINT', 0],
                        'can_view'     => ['BOOL', 1],
                        'can_edit'     => ['BOOL', 0],
                        'can_manage'   => ['BOOL', 0],
                        'can_delete'   => ['BOOL', 0],
                        'can_lock'     => ['BOOL', 0],
                        'granted_by'   => ['UINT', 0],
                        'granted_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'perm_id',
                    'KEYS' => [
                        'project_id'      => ['INDEX', 'project_id'],
                        'entity_lookup'   => ['INDEX', ['entity_type', 'entity_id']],
                        'unique_perm_row' => ['UNIQUE', ['project_id', 'entity_type', 'entity_id']],
                    ],
                ],
            ],
        ];
    }

    /**
     * Remove as tabelas ao desinstalar
     */
    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'workspace_permissions',
                $this->table_prefix . 'workspace_projects_users',
                $this->table_prefix . 'workspace_files',
                $this->table_prefix . 'workspace_projects',
            ],
        ];
    }

    /**
     * Insere dados iniciais, permissões e BBCodes
     */
    public function update_data()
    {
        return [
            // Adiciona as permissões ao sistema do phpBB
            ['permission.add', ['u_workspace_access']],
            ['permission.add', ['u_workspace_create']],
            ['permission.add', ['u_workspace_download']],
            ['permission.add', ['u_workspace_manage_own']],
            ['permission.add', ['u_workspace_manage_all']],

            // Define permissões padrão para usuários registrados
            ['permission.permission_set', ['REGISTERED', 'u_workspace_access', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_workspace_create', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_workspace_download', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_workspace_manage_own', 'group']],

            // Instalação do BBCode Diff e Otimização de Storage
            ['custom', [[$this, 'install_diff_bbcode']]],
            ['custom', [[$this, 'force_utf8mb4_storage']]],
        ];
    }

    public function revert_data()
    {
        return [
            ['permission.remove', ['u_workspace_access']],
            ['permission.remove', ['u_workspace_create']],
            ['permission.remove', ['u_workspace_download']],
            ['permission.remove', ['u_workspace_manage_own']],
            ['permission.remove', ['u_workspace_manage_all']],
            ['custom', [[$this, 'uninstall_diff_bbcode']]],
        ];
    }

    /**
     * Otimiza a tabela de arquivos para suportar caracteres especiais (Emojis/Símbolos de código)
     */
    public function force_utf8mb4_storage()
    {
        $sql_layer = $this->db->get_sql_layer();

        // Apenas para bancos MySQL/MariaDB
        if (strpos($sql_layer, 'mysql') !== false || strpos($sql_layer, 'mysqli') !== false)
        {
            try {
                $sql = 'ALTER TABLE ' . $this->table_prefix . 'workspace_files 
                        MODIFY file_content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                $this->db->sql_query($sql);
            } catch (\Exception $e) {
                return true; // Ignora erro para não travar a migração
            }
        }

        return true;
    }

    /**
     * Instala o BBCode [diff] para visualização de alterações no fórum
     */
    public function install_diff_bbcode()
    {
        $bbcode_tag = 'diff';
        $this->uninstall_diff_bbcode(); // Garante uma instalação limpa

        $bbcode_id = $this->get_free_bbcode_id();
        if (!$bbcode_id) return true;

        $sql_ary = [
            'bbcode_id'            => (int) $bbcode_id,
            'bbcode_tag'           => $bbcode_tag,
            'bbcode_match'         => '[diff={TEXT}]{TEXT1}[/diff]',
            'bbcode_tpl'           => '<div class="diff-wizard" style="border: 1px solid #444; border-radius: 4px; overflow: hidden; margin: 10px 0; font-family: Consolas, Monaco, monospace;">
                                        <div class="diff-header" style="background: #333; color: #fff; padding: 5px 10px; font-size: 12px; border-bottom: 1px solid #444;">
                                            <i class="fa fa-file-text-o"></i> {TEXT}
                                        </div>
                                        <pre class="diff-content" style="background: #1e1e1e; color: #d4d4d4; padding: 10px; margin: 0; white-space: pre-wrap; font-size: 13px; line-height: 1.5;">{TEXT1}</pre>
                                      </div>',
            'display_on_posting'   => 0,
            'bbcode_helpline'      => 'Visualizar diferenças de código: [diff=arquivo.php]conteúdo[/diff]',
            'first_pass_match'     => '/\[diff=(.*?)\](.*?)\[\/diff\]/is',
            'first_pass_replace'   => '[diff=$1]$2[/diff]',
            'second_pass_match'    => '',
            'second_pass_replace'  => '',
        ];

        $this->db->sql_query('INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
        return true;
    }

    public function uninstall_diff_bbcode()
    {
        $sql = 'DELETE FROM ' . BBCODES_TABLE . " WHERE bbcode_tag = 'diff'";
        $this->db->sql_query($sql);
        return true;
    }

    private function get_free_bbcode_id()
    {
        $sql = 'SELECT bbcode_id FROM ' . BBCODES_TABLE . ' ORDER BY bbcode_id ASC';
        $result = $this->db->sql_query($sql);
        
        $used = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $used[(int) $row['bbcode_id']] = true;
        }
        $this->db->sql_freeresult($result);

        for ($i = 1; $i <= 255; $i++)
        {
            if (empty($used[$i])) return $i;
        }
        return 0;
    }
}