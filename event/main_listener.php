<?php
namespace mundophpbb\workspace\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    protected $helper;
    protected $template;
    protected $user;
    protected $auth;
    protected $request;
    protected $phpbb_root_path;

    public function __construct(
        \phpbb\controller\helper $helper,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\auth\auth $auth,
        \phpbb\request\request $request,
        $phpbb_root_path
    ) {
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
        $this->request = $request;
        $this->phpbb_root_path = $phpbb_root_path;
    }

    static public function getSubscribedEvents()
    {
        return [
            'core.page_header' => 'add_workspace_assets',
            'core.permissions' => 'add_permissions',
        ];
    }

// No topo do arquivo, adicione:
// use phpbb\controller\helper;

public function add_assets($event)
{
    // Obtém o nome da rota atual
    $route = $this->request->server('SCRIPT_NAME');
    $is_workspace = (strpos($event['page_title'], 'Mundo phpBB Workspace') !== false);

    // SÓ ADICIONA SE FOR A NOSSA PÁGINA
    // Você também pode checar pela rota se injetou o helper
    if ($is_workspace) {
        $this->template_context->add_assets('css', '@mundophpbb_workspace/workspace.css');
        $this->template_context->add_assets('js', '@mundophpbb_workspace/js/wsp_core.js');
    }
}

    public function add_workspace_assets($event)
    {
        // 1. Carrega o idioma globalmente (necessário para o link no Menu)
        $this->user->add_lang_ext('mundophpbb/workspace', 'workspace');

        // 2. Identifica se estamos na página da IDE
        // Pegamos a rota atual via request
        $current_route = $this->request->variable('_route', '');
        $is_workspace_page = ($current_route === 'mundophpbb_workspace_main');

        // 3. Define variáveis de caminho
        $board_url = generate_board_url() . '/';
        $assets_path = $board_url . 'ext/mundophpbb/workspace/styles/all';

        // Versão para cache busting
        $css_file = $this->phpbb_root_path . 'ext/mundophpbb/workspace/styles/all/theme/workspace.css';
        $version = (file_exists($css_file)) ? filemtime($css_file) : time();

        // 4. Envia variáveis para o Template
        $this->template->assign_vars([
            'S_IN_WORKSPACE'     => $is_workspace_page, // Crucial para o IF no template
            'T_WSP_ASSETS'       => $assets_path,
            'WSP_VERSION'        => $version,
        ]);

        // 5. Define o link do menu apenas para quem tem permissão
        if ($this->user->data['user_id'] != ANONYMOUS && $this->auth->acl_get('u_workspace_access'))
        {
            $this->template->assign_vars([
                'U_WORKSPACE_MAIN' => $this->helper->route('mundophpbb_workspace_main'),
            ]);
        }
    }

    public function add_permissions($event)
    {
        $this->user->add_lang_ext('mundophpbb/workspace', 'acp/permissions');

        $event->update_subarray('permissions', 'u_workspace_access', [
            'lang' => 'ACL_U_WORKSPACE_ACCESS',
            'cat'  => 'misc',
        ]);
    }
}