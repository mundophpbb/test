/**
 * Mundo phpBB Workspace - UI & Modals
 * Gerencia notificações, diálogos e a persistência de eventos via delegação.
 * Versão 2.7: Fix Sidebar Filter logic + Smart Tree Persistence
 */
WSP.ui = {
    /**
     * Inicialização
     */
    init: function ($) {
        this.injectModal($);
        this.bindEvents($);
    },

    /**
     * Injeta a estrutura de Modais e Notificações (Singleton)
     */
    injectModal: function ($) {
        if ($('#wsp-custom-modal').length === 0) {
            $('body').append(`
                <div id="wsp-custom-modal" style="display:none; position:fixed; z-index:100010; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center; backdrop-filter: blur(2px);">
                    <div id="wsp-modal-card" style="background:#2d2d2d; padding:20px; width:450px; border:1px solid #444; border-radius:6px; color:#ccc; box-shadow: 0 15px 40px rgba(0,0,0,0.6);">
                        <h4 id="wsp-modal-title" style="margin:0 0 15px 0; color:#fff; font-size:15px; font-weight:500; border-bottom:1px solid #3f3f3f; padding-bottom:10px;"></h4>
                        <div id="wsp-modal-body-custom" style="margin-bottom: 15px; display:none; max-height: 400px; overflow-y: auto;"></div>
                        <input type="text" id="wsp-modal-input" style="width:100%; padding:10px; margin-bottom:15px; background:#1e1e1e; border:1px solid #444; color:#fff; border-radius:4px; outline:none; display:none;">
                        <div style="text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                            <button id="wsp-modal-cancel" class="button" style="background:#3a3a3a; color:#eee; border:1px solid #555; padding:6px 15px;">${window.wspVars.lang.cancel || 'Cancelar'}</button>
                            <button id="wsp-modal-ok" class="button" style="background:var(--wsp-accent, #007acc); color:#fff; border:none; padding:6px 20px; border-radius:4px;">${window.wspVars.lang.ok || 'Confirmar'}</button>
                        </div>
                    </div>
                </div>
            `);
        }

        if ($('#wsp-notify-container').length === 0) {
            $('body').append('<div id="wsp-notify-container" style="position:fixed; bottom:30px; right:30px; z-index:100020; display:flex; flex-direction:column; gap:10px;"></div>');
        }

        // Fechar modal ao clicar no fundo
        $('body').off('mousedown.wsp_modal').on('mousedown.wsp_modal', '#wsp-custom-modal', function (e) {
            if ($(e.target).attr('id') === 'wsp-custom-modal') $('#wsp-custom-modal').hide();
        });
    },

    /**
     * Notificações Toast
     */
    notify: function (message, type) {
        type = type || 'success';
        var id = 'notif-' + Date.now();
        var colors = { success: '#28a745', error: '#dc3545', info: '#007acc', warning: '#e2c08d' };
        var icons = { success: 'check-circle', error: 'exclamation-triangle', info: 'info-circle', warning: 'exclamation-circle' };

        var html = `
            <div id="${id}" style="background:${colors[type]}; color:#fff; padding:12px 25px; border-radius:4px; font-size:13px; box-shadow:0 10px 20px rgba(0,0,0,0.3); display:flex; align-items:center; gap:12px; min-width:250px; border-left: 5px solid rgba(0,0,0,0.2); animation: wspFadeIn 0.3s ease;">
                <i class="fa fa-${icons[type]}"></i>
                <span>${message}</span>
            </div>
        `;

        $('#wsp-notify-container').append(html);
        setTimeout(function () { $('#' + id).fadeOut(400, function () { $(this).remove(); }); }, 4000);
    },

    /**
     * Diálogo de Prompt / Lista
     */
    prompt: function (title, defaultValue, callback) {
        callback = callback || function () { };
        $('#wsp-modal-title').text(title);
        $('#wsp-modal-body-custom').hide().empty();
        $('#wsp-modal-ok, #wsp-modal-cancel').off('click');

        if (defaultValue === 'LIST_MODE') {
            $('#wsp-modal-input').hide();
            $('#wsp-modal-ok').hide();
            $('#wsp-modal-body-custom').show();
        } else {
            $('#wsp-modal-input').val(defaultValue || '').show();
            $('#wsp-modal-ok').show();
        }

        $('#wsp-custom-modal').css('display', 'flex');
        
        if (defaultValue !== 'LIST_MODE') {
            $('#wsp-modal-input').focus().select();
            $('#wsp-modal-ok').on('click', function () {
                var val = $('#wsp-modal-input').val();
                $('#wsp-custom-modal').hide();
                callback(val);
            });
        }

        $('#wsp-modal-cancel').on('click', function () { $('#wsp-custom-modal').hide(); });
        $('#wsp-modal-input').on('keypress', function (e) { if (e.which === 13) $('#wsp-modal-ok').click(); });
    },

    confirm: function (title, callback) {
        this.prompt(title, 'LIST_MODE');
        $('#wsp-modal-body-custom').html('<p style="margin:10px 0; color:#aaa;">Esta ação não pode ser desfeita.</p>').show();
        $('#wsp-modal-ok').show().off('click').on('click', function() {
            $('#wsp-custom-modal').hide();
            callback();
        });
    },

    /**
     * Atualização da Sidebar com Reconstrução da Árvore
     */
    seamlessRefresh: function (fileIdToOpen) {
        var self = this;
        var currentUrl = window.location.href;

        // 1. Salva o estado atual das pastas abertas
        var openFolders = [];
        jQuery('.folder-item').each(function() {
            if (jQuery(this).find('> .folder-content').is(':visible')) {
                var path = jQuery(this).find('> .folder-title').attr('data-path');
                if (path) openFolders.push(path);
            }
        });

        // 2. Busca o novo HTML
        jQuery.get(currentUrl, { _nocache: Date.now() }, function (data) {
            var $newSidebar = jQuery(data).find('#project-list');
            if ($newSidebar.length) {
                jQuery('#project-list').html($newSidebar.html());

                // 3. RECONSTRÓI A ÁRVORE (Chama o renderizador do wsp_tree.js)
                if (window.WSP.tree && typeof window.WSP.tree.render === 'function') {
                    window.WSP.tree.render(jQuery);
                    window.WSP.tree.bindEvents(jQuery); // Re-vincula cliques na nova estrutura
                }

                // 4. Restaura pastas que estavam abertas
                openFolders.forEach(function(path) {
                    var $folder = jQuery(`.folder-title[data-path="${path}"]`);
                    $folder.next('.folder-content').show();
                    $folder.find('i.icon').removeClass('fa-folder').addClass('fa-folder-open');
                });

                // 5. Restaura destaque visual do arquivo ativo
                if (window.WSP.activeFileId) {
                    jQuery(`.load-file[data-id="${window.WSP.activeFileId}"]`).closest('.file-item').addClass('active-file');
                }

                // 6. Se solicitado, abre um arquivo específico (ex: recém criado)
                if (fileIdToOpen) {
                    setTimeout(function() {
                        var $target = jQuery(`.load-file[data-id="${fileIdToOpen}"]`);
                        if ($target.length) $target.click();
                    }, 200);
                }
            }
        });
    },

    /**
     * EVENTOS DELEGADOS (UI Global)
     */
    bindEvents: function ($) {
        var self = this;

        // 1. GESTÃO DE PASTA ATIVA (RESET)
        $('body').off('click', '#wsp-active-folder-indicator').on('click', '#wsp-active-folder-indicator', function() {
            if (window.WSP.tree && window.WSP.tree.clearActiveFolder) {
                window.WSP.tree.clearActiveFolder();
                self.notify("Foco retornado para a Raiz.", "info");
            }
        });

        // 2. FILTRO DA SIDEBAR (Lógica de visibilidade recursiva)
        $('body').off('input', '#wsp-sidebar-filter').on('input', '#wsp-sidebar-filter', function () {
            var term = $(this).val().toLowerCase();
            
            if (term === '') {
                $('.file-item, .folder-item').show();
                return;
            }

            // Filtra Arquivos
            $('.file-item').each(function () {
                var fileName = $(this).find('.load-file').text().toLowerCase();
                $(this).toggle(fileName.indexOf(term) > -1);
            });

            // Filtra Pastas (Só mostra se houver arquivos visíveis dentro dela)
            $('.folder-item').each(function() {
                var hasMatch = $(this).find('.file-item:visible').length > 0;
                $(this).toggle(hasMatch);
                
                if (hasMatch) {
                    $(this).find('> .folder-content').show();
                    $(this).find('> .folder-title i.icon').removeClass('fa-folder').addClass('fa-folder-open');
                }
            });
        });

        // 3. FULLSCREEN TOGGLE
        $('body').off('click', '#toggle-fullscreen').on('click', '#toggle-fullscreen', function () {
            $('.workspace-container').toggleClass('fullscreen-mode');
            $(this).find('i').toggleClass('fa-expand fa-compress');
            if (window.WSP.editor) {
                setTimeout(function() { window.WSP.editor.resize(); }, 150);
            }
        });
    },

    renderBreadcrumbs: function(path) {
        this.updateBreadcrumbs(path);
    },

    updateBreadcrumbs: function (path) {
        var $target = $('#current-file');
        if (!path) {
            $target.text(window.wspVars.lang.select_file || "Selecione um arquivo");
            return;
        }

        var parts = String(path).split('/');
        var html = '<i class="fa fa-folder-open-o" style="color:var(--wsp-accent); margin-right:5px;"></i> ';
        parts.forEach(function (part, index) {
            html += `<span class="breadcrumb-item">${$('<div/>').text(part).html()}</span>`;
            if (index < parts.length - 1) {
                html += ' <i class="fa fa-angle-right" style="font-size:10px; opacity:0.5; margin:0 5px;"></i> ';
            }
        });
        $target.html(html);
    }
};