/**
 * Mundo phpBB Workspace - File Operations
 * Gerencia o ciclo de vida dos arquivos no editor, backups e trilha de navegação.
 */
WSP.files = {
    autoSaveInterval: null,
    _dirtyBound: false,
    _saveCommandBound: false,

    /**
     * Vincula todos os eventos de manipulação de arquivos
     */
    bindEvents: function ($) {
        var self = this;

        // Helper para verificar se o editor está pronto
        var isEditorReady = function () {
            return (WSP && WSP.editor && typeof WSP.editor.getValue === 'function');
        };

        // 1) CARREGAR ARQUIVO (Click na Sidebar)
        // Usamos delegação no 'body' para que funcione mesmo após a sidebar atualizar via AJAX
        $('body').off('click', '.load-file').on('click', '.load-file', function (e) {
            e.preventDefault();

            if (!isEditorReady()) {
                WSP.ui.notify('O editor ainda está carregando...', 'warning');
                return;
            }

            var $link = $(this);
            var fileId = $link.data('id');
            if (!fileId) return;

            // Define o ID ativo e salva no localStorage para persistência pós-F5
            WSP.activeFileId = fileId;
            localStorage.setItem('wsp_active_file_id', fileId);

            // Pega o caminho completo (importante para os breadcrumbs)
            var fullPath = ($link.attr('data-path') || $link.text() || '').trim();
            self.renderBreadcrumbs(fullPath);

            // Highlight visual na sidebar
            $('.file-item').removeClass('active-file');
            $link.closest('.file-item').addClass('active-file');

            // Feedback de progresso
            $('#current-file').addClass('loading-effect');

            $.post(
                wspVars.loadUrl,
                { file_id: fileId, _nocache: Date.now() },
                function (r) {
                    $('#current-file').removeClass('loading-effect');

                    if (!r || !r.success) {
                        var errorMsg = (r && r.error) ? r.error : 'Erro ao abrir o arquivo.';
                        WSP.ui.notify(errorMsg, 'error');
                        return;
                    }

                    // Prepara o editor
                    WSP.editor.setReadOnly(false);

                    // Define o modo de sintaxe (Ex: PHP, JS, CSS)
                    var fileName = (r.name || '').toLowerCase();
                    if (fileName === 'changelog.txt' || fileName.endsWith('.txt')) {
                        WSP.editor.session.setMode("ace/mode/text");
                        // Se for changelog, habilitamos o botão de copiar BBCode
                        if (fileName === 'changelog.txt') $('#copy-bbcode').fadeIn(200);
                    } else {
                        WSP.editor.session.setMode(WSP.modes[r.type] || 'ace/mode/text');
                        $('#copy-bbcode').hide();
                    }

                    // Gerencia conteúdo: Prioridade para o Servidor, mas checa se há backup local não salvo
                    var serverContent = (typeof r.content === 'string') ? r.content : '';
                    var backupKey = 'wsp_backup_' + fileId;
                    var localBackup = localStorage.getItem(backupKey);

                    var finalContent = serverContent;
                    if (localBackup && localBackup !== serverContent) {
                        // Opcional: Aqui poderíamos perguntar ao usuário, por hora priorizamos o servidor
                        // para evitar confusão com dados antigos.
                        localStorage.removeItem(backupKey); 
                    }

                    WSP.editor.setValue(finalContent, -1);
                    WSP.originalContent = finalContent; // Referência para o indicador de "Alterado (*)"

                    WSP.editor.resize();
                    WSP.editor.focus();

                    $('#save-file').fadeIn(200);
                    WSP.updateUIState();

                    // Inicia rotinas auxiliares
                    self.initAutoSave();
                    self.bindDirtyDetector();
                    self.bindSaveShortcut();
                },
                'json'
            ).fail(function() {
                WSP.ui.notify("Falha crítica ao carregar arquivo. Verifique sua conexão.", "error");
                $('#current-file').removeClass('loading-effect');
            });
        });

        // 2) SALVAR ARQUIVO (Botão ou Atalho)
        $('body').off('click', '#save-file').on('click', '#save-file', function () {
            if (!isEditorReady() || !WSP.activeFileId) return;

            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

            var contentToSave = WSP.editor.getValue();

            $.post(
                wspVars.saveUrl,
                { file_id: WSP.activeFileId, content: contentToSave },
                function (r) {
                    if (r && r.success) {
                        WSP.originalContent = contentToSave;
                        localStorage.removeItem('wsp_backup_' + WSP.activeFileId);

                        // Remove a marca de "sujo (*)" do nome do arquivo na sidebar
                        var $activeLink = $('.load-file[data-id="' + WSP.activeFileId + '"]');
                        if ($activeLink.length) {
                            var cleanName = $activeLink.text().replace(/^\* /, '');
                            $activeLink.text(cleanName).css('font-style', 'normal');
                        }

                        $btn.html('<i class="fa fa-check"></i> Salvo!')
                            .addClass('btn-success-temporary');

                        setTimeout(function () {
                            $btn.prop('disabled', false)
                                .html('<i class="fa fa-save"></i> Salvar')
                                .removeClass('btn-success-temporary');
                        }, 1000);
                    } else {
                        WSP.ui.notify(r.error || 'Erro ao salvar.', 'error');
                        $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar');
                    }
                },
                'json'
            );
        });
    },

    /**
     * Monitora mudanças no editor para mostrar o asterisco (*) na sidebar
     */
    bindDirtyDetector: function () {
        if (this._dirtyBound || !WSP.editor) return;
        this._dirtyBound = true;

        WSP.editor.on("input", function () {
            if (!WSP.activeFileId) return;

            var current = WSP.editor.getValue();
            var $link = jQuery('.load-file[data-id="' + WSP.activeFileId + '"]');
            if (!$link.length) return;

            var name = $link.text();
            var isDirty = (current !== WSP.originalContent);

            if (isDirty && name.indexOf('* ') !== 0) {
                $link.text('* ' + name).css('font-style', 'italic');
            } else if (!isDirty && name.indexOf('* ') === 0) {
                $link.text(name.substring(2)).css('font-style', 'normal');
            }
        });
    },

    /**
     * Atalhos de teclado (Ctrl+S / Cmd+S)
     */
    bindSaveShortcut: function () {
        if (this._saveCommandBound || !WSP.editor) return;
        this._saveCommandBound = true;

        WSP.editor.commands.addCommand({
            name: 'save',
            bindKey: { win: 'Ctrl-S', mac: 'Command-S' },
            exec: function () { jQuery('#save-file').click(); }
        });

        // Evento do botão de Copiar BBCode para o fórum
        jQuery('body').off('click', '#copy-bbcode').on('click', '#copy-bbcode', function () {
            var content = WSP.editor.getValue();
            var name = jQuery('.active-file .load-file').text().replace(/^\* /, '');
            var bbcode = "[diff=" + name + "]\n" + content + "\n[/diff]";

            var $temp = jQuery("<textarea>").val(bbcode).appendTo("body").select();
            document.execCommand("copy");
            $temp.remove();

            WSP.ui.notify('BBCode copiado para a área de transferência!', 'info');
        });
    },

    /**
     * Renderiza o caminho do arquivo (Breadcrumbs)
     */
    renderBreadcrumbs: function (path) {
        var self = this;
        var $target = jQuery('#current-file');
        
        path = (path || '').trim();
        if (!path) {
            $target.text(wspVars.lang.select_file || 'Selecione um arquivo');
            return;
        }

        var parts = path.split('/');
        var html = '<i class="fa fa-folder-open-o" style="color:var(--wsp-accent)"></i> ';
        
        parts.forEach(function (part, index) {
            html += '<span class="breadcrumb-item">' + self._escape(part) + '</span>';
            if (index < parts.length - 1) {
                html += ' <i class="fa fa-angle-right sep"></i> ';
            }
        });
        
        $target.html(html);
    },

    _escape: function(s) {
        return jQuery('<div/>').text(s).html();
    },

    /**
     * Auto-Save Local: Previne perda de código em caso de queda de energia ou fechamento de aba.
     */
    initAutoSave: function () {
        if (this.autoSaveInterval) clearInterval(this.autoSaveInterval);

        this.autoSaveInterval = setInterval(function () {
            if (WSP.activeFileId && WSP.editor) {
                var current = WSP.editor.getValue();
                if (current !== WSP.originalContent) {
                    localStorage.setItem('wsp_backup_' + WSP.activeFileId, current);
                    console.log("WSP: Backup local atualizado para arquivo " + WSP.activeFileId);
                }
            }
        }, 15000); // Salva a cada 15 segundos
    }
};