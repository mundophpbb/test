/**
 * Mundo phpBB Workspace - Inicializador Blindado (Nova Interface)
 * Gerencia o carregamento de módulos, dependências e restauração de estado.
 */
(function ($) {
    'use strict';

    var MAX_DEP_TRIES = 200; // 20 segundos de limite
    var depTries = 0;

    /**
     * Verifica se o elemento do editor existe na página atual.
     */
    var hasWorkspaceDom = function () {
        return !!document.getElementById('editor');
    };

    /**
     * Helper de tradução/lang com fallback.
     */
    var lang = function (key, fallback) {
        if (window.wspVars && wspVars.lang && typeof wspVars.lang[key] !== 'undefined') {
            return wspVars.lang[key];
        }
        return fallback;
    };

    /**
     * Função principal de ativação da IDE.
     */
    var startWorkspace = function () {
        if (!hasWorkspaceDom()) return;

        console.log("WSP: Iniciando módulos da IDE...");

        // Desativa cache global para evitar que o navegador sirva versões antigas dos arquivos do banco
        $.ajaxSetup({ cache: false });

        // 1. Inicializa o Editor ACE primeiro (Dependência base)
        if (!window.WSP || typeof WSP.initEditor !== 'function' || !WSP.initEditor()) {
            console.error("WSP: Falha crítica - O editor ACE não pôde ser inicializado.");
            return;
        }

        // 2. Ordem de Inicialização dos Módulos (Init -> Bind)
        var modules = [
            { name: 'UI', ref: WSP.ui },
            { name: 'Tree', ref: WSP.tree },
            { name: 'Files', ref: WSP.files },
            { name: 'Projects', ref: WSP.projects },
            { name: 'Upload', ref: WSP.upload },
            { name: 'Tools', ref: WSP.tools }
        ];

        modules.forEach(function (m) {
            try {
                if (m.ref) {
                    if (typeof m.ref.init === 'function') m.ref.init($);
                    if (typeof m.ref.render === 'function') m.ref.render($); // Importante para a Tree
                    if (typeof m.ref.bindEvents === 'function') m.ref.bindEvents($);
                    console.log("WSP: Módulo [" + m.name + "] carregado.");
                }
            } catch (e) {
                console.error("WSP: Erro no módulo [" + m.name + "]:", e);
            }
        });

        // 3. Sincroniza o estado visual da Toolbar
        WSP.updateUIState();

        /**
         * 4. RESTAURAÇÃO DE ESTADO (PÓS-F5)
         * Recupera o último arquivo aberto e tenta restaurá-lo.
         */
        var savedFileId = localStorage.getItem('wsp_active_file_id');
        var hasProject = !!WSP.activeProjectId;

        if (savedFileId && hasProject) {
            WSP.activeFileId = savedFileId;

            // Feedback visual no label do arquivo
            $('#current-file').text(lang('loading', 'Carregando arquivo...')).css('color', 'var(--wsp-accent)');

            // Tenta carregar do servidor (autoridade máxima)
            $.post(wspVars.loadUrl, { file_id: savedFileId, _nocache: Date.now() }, function (r) {
                if (r && r.success) {
                    // Carrega conteúdo e configura editor
                    WSP.editor.setValue(r.content || '', -1);
                    WSP.editor.setReadOnly(false);
                    WSP.originalContent = r.content;

                    // Atualiza Breadcrumbs e modo do Ace
                    if (WSP.files && typeof WSP.files.renderBreadcrumbs === 'function') {
                        WSP.files.renderBreadcrumbs(r.name);
                    }

                    var nameLower = (r.name || '').toLowerCase();
                    if (nameLower === 'changelog.txt') {
                        WSP.editor.session.setMode("ace/mode/text");
                        $('#copy-bbcode').show();
                    } else {
                        WSP.editor.session.setMode(WSP.modes[r.type] || 'ace/mode/text');
                        $('#copy-bbcode').hide();
                    }

                    $('#save-file').show();

                    // Re-expande a pasta do arquivo na árvore lateral
                    setTimeout(function () {
                        var $target = $('.load-file[data-id="' + savedFileId + '"]');
                        if ($target.length) {
                            $('.file-item').removeClass('active-file');
                            $target.closest('.file-item').addClass('active-file');
                            
                            // Abre todas as pastas pai
                            $target.parents('.folder-content').show();
                            $target.parents('.folder-item').find('> .folder-title i.icon')
                                .removeClass('fa-folder').addClass('fa-folder-open');
                        }
                    }, 300);
                } else {
                    // Se o arquivo não existe mais ou houve erro, limpa o estado
                    localStorage.removeItem('wsp_active_file_id');
                    $('#current-file').text(lang('select_file', 'Selecione um arquivo')).css('color', '');
                }
            }, 'json');
        }

        console.log("WSP: IDE pronta para uso.");
    };

    /**
     * Verificador de Dependências (Polling)
     * Garante que bibliotecas externas e módulos WSP existam antes de rodar.
     */
    var checkDeps = function () {
        if (!hasWorkspaceDom()) return;

        depTries++;

        var libsReady = (typeof jQuery !== 'undefined' && typeof ace !== 'undefined' && typeof wspVars !== 'undefined');
        var modulesReady = (window.WSP && WSP.ui && WSP.tree && WSP.files && WSP.projects && WSP.upload && WSP.tools);

        if (libsReady && modulesReady) {
            $(document).ready(startWorkspace);
            return;
        }

        if (depTries >= MAX_DEP_TRIES) {
            console.error("WSP: Erro de Timeout. As dependências demoraram demais para carregar.");
            return;
        }

        setTimeout(checkDeps, 100);
    };

    checkDeps();

})(jQuery);