/**
 * Mundo phpBB Workspace - Tools (Diff, Search, Cache)
 * Gerencia utilitários de busca, substituição, comparação e manutenção.
 */
WSP.tools = {
    /**
     * Vincula eventos das ferramentas
     */
    bindEvents: function ($) {
        var self = this;

        // Helper para verificar existência de elementos
        var hasEl = function (sel) { return $(sel).length > 0; };

        /**
         * 1. BUSCA E SUBSTITUIÇÃO
         */
        $('body').off('click', '.open-search-replace').on('click', '.open-search-replace', function () {
            var projectId = WSP.activeProjectId;

            if (!projectId) {
                return WSP.ui.notify("Abra um projeto para usar a busca.", "warning");
            }

            if (!hasEl('#search-replace-modal')) {
                return WSP.ui.notify("Erro: Interface de busca não carregada.", "error");
            }

            // Limpa campos antes de abrir
            $('#wsp-search-term').val('');
            $('#wsp-replace-term').val('');
            $('#search-project-id').val(projectId);

            $('#search-replace-modal').css('z-index', '100005').fadeIn(200);
        });

        // Executar Substituição
        $('body').off('click', '#exec-replace-btn').on('click', '#exec-replace-btn', function () {
            var $btn = $(this);
            var projectId = $('#search-project-id').val() || WSP.activeProjectId;
            
            var data = {
                project_id: projectId,
                file_id: WSP.activeFileId || 0, // 0 = Projeto Inteiro
                search: $('#wsp-search-term').val(),
                replace: $('#wsp-replace-term').val()
            };

            if (!data.search) {
                return WSP.ui.notify("Digite o termo que deseja procurar.", "warning");
            }

            WSP.ui.confirm("Isso alterará o conteúdo dos arquivos no banco de dados. Confirmar?", function () {
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processando...');

                $.post(wspVars.replaceUrl, data, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Substituição concluída: " + r.updated + " arquivos alterados.");
                        
                        // Limpa backups locais para evitar conflitos com a nova versão do banco
                        self._clearBackups(data.file_id);

                        $('#search-replace-modal').hide();
                        WSP.ui.seamlessRefresh();

                        // Se o arquivo aberto foi alterado, recarrega ele no editor
                        if (WSP.activeFileId) {
                            $('.active-file .load-file').click();
                        }
                    } else {
                        WSP.ui.notify(r.error || "Erro na substituição.", "error");
                    }
                }, 'json').always(function() {
                    $btn.prop('disabled', false).text("Substituir");
                });
            });
        });

        /**
         * 2. FERRAMENTA DE DIFERENÇA (DIFF / COMPARAR)
         * Corrigido para ler caminhos na nova Tree
         */
        $('body').off('click', '#open-diff-tool').on('click', '#open-diff-tool', function () {
            if (!WSP.activeProjectId) {
                return WSP.ui.notify("Selecione um projeto primeiro.", "info");
            }

            var $orig = $('#diff-original').empty();
            var $mod = $('#diff-modified').empty();
            var foundFiles = 0;

            // Popula os selects usando os arquivos visíveis na sidebar
            $('.load-file').each(function () {
                var $a = $(this);
                var id = $a.data('id');
                var path = $a.attr('data-path') || $a.text();

                // Ignora marcadores de pasta
                if (path.indexOf('.placeholder') !== -1) return;

                $orig.append($('<option>').val(id).text(path));
                $mod.append($('<option>').val(id).text(path));
                foundFiles++;
            });

            if (foundFiles < 2) {
                return WSP.ui.notify("Você precisa de pelo menos 2 arquivos no projeto para comparar.", "info");
            }

            $('#diff-modal').css('z-index', '100006').fadeIn(200);
        });

        // Gerar o Diff Visual
        $('body').off('click', '#generate-diff-btn').on('click', '#generate-diff-btn', function () {
            var $btn = $(this);
            var data = {
                original_id: $('#diff-original').val(),
                modified_id: $('#diff-modified').val(),
                filename: $('#diff-original option:selected').text()
            };

            if (data.original_id === data.modified_id) {
                return WSP.ui.notify("Escolha arquivos diferentes para comparar.", "warning");
            }

            $btn.prop('disabled', true).text("Comparando...");

            $.post(wspVars.diffUrl, data, function (r) {
                if (r && r.success) {
                    $('#diff-modal').hide();

                    // Desativa o salvamento para o Diff (ele é apenas visual)
                    WSP.activeFileId = null;
                    
                    if (WSP.editor) {
                        WSP.editor.setReadOnly(false);
                        WSP.editor.session.setMode("ace/mode/diff");
                        WSP.editor.setValue(r.bbcode || '', -1);
                        WSP.editor.focus();
                    }

                    $('#copy-bbcode').fadeIn(300);
                    $('#save-file').hide();
                    $('#current-file').html('<i class="fa fa-columns"></i> Diff: ' + data.filename);
                } else {
                    WSP.ui.notify(r.error || "Erro ao gerar comparação.", "error");
                }
            }, 'json').always(function() {
                $btn.prop('disabled', false).text("Gerar Comparação");
            });
        });

        /**
         * 3. LIMPEZA DE CACHE DO FORUM
         */
        $('body').off('click', '#refresh-phpbb-cache').on('click', '#refresh-phpbb-cache', function () {
            var $btn = $(this);
            var $icon = $btn.find('i').addClass('fa-spin');
            $btn.css('pointer-events', 'none');

            $.post(wspVars.refreshCacheUrl, function (r) {
                if (r && r.success) {
                    WSP.ui.notify("Cache do phpBB limpo com sucesso!");
                    setTimeout(function() { window.location.reload(true); }, 1000);
                } else {
                    WSP.ui.notify("Erro ao limpar cache.", "error");
                    $icon.removeClass('fa-spin');
                    $btn.css('pointer-events', 'auto');
                }
            }, 'json');
        });

        /**
         * 4. FECHAMENTO DE MODAIS (Esc)
         */
        $(document).off('keydown.wsp_tools').on('keydown.wsp_tools', function (e) {
            if (e.key === "Escape") {
                $('#search-replace-modal, #diff-modal').fadeOut(150);
            }
        });
    },

    /**
     * Helper para limpar backups do localStorage
     */
    _clearBackups: function(fileId) {
        if (!fileId || fileId === 0) {
            // Limpa tudo que for do Workspace
            Object.keys(localStorage).forEach(function (key) {
                if (key.indexOf('wsp_backup_') === 0) localStorage.removeItem(key);
            });
        } else {
            localStorage.removeItem('wsp_backup_' + fileId);
        }
    }
};