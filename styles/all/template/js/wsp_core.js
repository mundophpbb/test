/**
 * Mundo phpBB Workspace - Core Modular
 * Versão: 2.9.9 - Restauração Completa, Hotkeys e Árvore Recursiva
 */
(function(window) {
    'use strict';

    window.WSP = {
        state: {
            activeProjectId: null,
            activeFileId: null,
            isDirty: false,
            currentTheme: 'ace/theme/monokai'
        },

        get $() { return window.jQuery || window.$; },

        init: function() {
            var $ = this.$;
            if (!$) return;

            this.config = window.wspVars || {};
            this.state.activeProjectId = this.config.activeProjectId || null;

            try {
                this.editor.init();
                this.ui.initModals();
                this.bindGlobalEvents();
                this.bindHotkeys(); // Ativa interceptação do teclado
                
                if (this.state.activeProjectId) this.project.renderTree();
                
                console.log('%c[WSP] IDE v2.9.9: Sistema Completo Online.', 'color: #28a745; font-weight: bold;');
            } catch (e) {
                console.error('[WSP] Erro na inicialização:', e);
            }
        },

        // --- GESTÃO DE TECLAS (HOTKEYS) ---
        bindHotkeys: function() {
            var self = this;
            this.$(window).off('keydown').on('keydown', function(e) {
                // Ctrl + S para Salvar
                if ((e.ctrlKey || e.metaKey) && e.which == 83) {
                    e.preventDefault();
                    self.file.save();
                }
            });
        },

        // --- MÓDULO DE PROJETO ---
        project: {
            create: function() {
                var self = WSP;
                self.ui.prompt(self.config.lang.prompt_name, '', function(name) {
                    self.$.post(self.config.addUrl, { name: name }, function(r) {
                        if(r.success) {
                            self.ui.notify("Projeto Criado!", "success");
                            self.project.loadSidebar(r.id, r.name);
                        }
                    }, 'json');
                });
            },

            openModal: function() {
                var self = WSP;
                var $list = self.$('#project-list-select');
                $list.html('<div style="text-align:center;padding:20px;"><i class="fa fa-refresh fa-spin fa-2x"></i></div>');
                self.$('#wsp-open-project-modal').css('display', 'flex').hide().fadeIn(200);

                self.$.get(self.config.mainUrl, function(data) {
                    var $projects = self.$(data).find('.project-card-hidden');
                    $list.empty();
                    if ($projects.length === 0) {
                        $list.html('<p style="padding:20px; color:#888;">Nenhum projeto encontrado.</p>');
                    } else {
                        $projects.each(function() {
                            var id = self.$(this).attr('data-id');
                            var name = self.$(this).attr('data-name');
                            $list.append(`<div class="project-card" data-id="${id}" data-name="${name}">
                                <i class="fa fa-folder"></i> <span>${name}</span></div>`);
                        });
                    }
                });
            },

            loadSidebar: function(id, name) {
                var self = WSP;
                self.state.activeProjectId = id;
                self.ui.setLoading(true);
                self.$.get(self.config.mainUrl, { project_id: id }, function(data) {
                    var $tempDiv = self.$('<div>').html(data);
                    var newHtml = $tempDiv.find('#project-list').html();
                    self.$('#project-list').html(newHtml || '<p>Projeto vazio.</p>');
                    self.ui.updateProjectDisplay(name);
                    self.project.renderTree();
                    self.ui.setLoading(false);
                });
            },

            renderTree: function() {
                var $ = WSP.$;
                $('.file-list').each(function() {
                    var $list = $(this), files = [];
                    $list.find('.file-item').each(function() {
                        var $el = $(this), $link = $el.find('.load-file');
                        files.push({
                            id: $link.attr('data-id'),
                            name: $link.find('.file-label').text().trim(),
                            type: $link.attr('data-type'),
                            html: $el[0].outerHTML
                        });
                    });

                    if (files.length === 0) return;

                    var structure = {};
                    files.forEach(file => {
                        var parts = file.name.split('/'), current = structure;
                        parts.forEach((part, i) => {
                            var isLast = (i === parts.length - 1);
                            if (!current[part]) current[part] = isLast ? file : { _isDir: true, _children: {} };
                            if (!isLast) current = current[part]._children;
                        });
                    });

                    var buildHtml = function(obj, level = 0) {
                        var html = '', keys = Object.keys(obj).sort((a, b) => (obj[b]._isDir || 0) - (obj[a]._isDir || 0));
                        keys.forEach(key => {
                            var item = obj[key], padding = level * 15;
                            if (item._isDir) {
                                html += `<li class="folder-item" style="padding-left:${padding}px">
                                            <div class="folder-title"><i class="fa fa-folder"></i> ${key}</div>
                                            <ul class="folder-content" style="display:none;">${buildHtml(item._children, level + 1)}</ul>
                                         </li>`;
                            } else { html += `<div class="tree-file-wrapper" style="padding-left:${padding}px">${item.html}</div>`; }
                        });
                        return html;
                    };
                    $list.html(buildHtml(structure));
                });
            }
        },

        // --- MÓDULO DE ARQUIVO ---
        file: {
            open: function(fileId) {
                var self = WSP;
                self.ui.setLoading(true);
                self.api(self.config.loadUrl, { file_id: fileId }, function(r) {
                    self.editor.instance.setReadOnly(false);
                    self.editor.instance.setValue(r.content, -1);
                    self.state.activeFileId = fileId;
                    self.ui.updateActiveFileUI(fileId, r.name, r.type);
                    self.ui.setLoading(false);
                });
            },
            save: function() {
                var self = WSP;
                if (!self.state.activeFileId) return self.ui.notify('Selecione um arquivo!', 'error');
                
                var content = self.editor.instance.getValue();
                self.ui.setLoading(true);
                self.api(self.config.saveUrl, { file_id: self.state.activeFileId, content: content }, function() {
                    self.ui.notify('Arquivo salvo com sucesso!', 'success');
                    self.state.isDirty = false;
                    self.ui.setLoading(false);
                });
            }
        },

        // --- MÓDULO DE FERRAMENTAS ---
        tools: {
            exportGist: function() {
                var self = WSP;
                if (!self.state.activeFileId) return self.ui.notify('Abra um arquivo primeiro.', 'error');
                self.ui.setLoading(true);
                self.api(self.config.gistUrl, { file_id: self.state.activeFileId }, function(r) {
                    self.ui.setLoading(false);
                    if (r.success) window.open(r.url, '_blank');
                });
            },
            purgeCache: function() {
                var self = WSP;
                self.api(self.config.purgeCacheUrl, {}, () => self.ui.notify("Cache limpo!", "success"));
            }
        },

        // --- MÓDULO DE UI ---
        ui: {
            initModals: function() {
                var $ = WSP.$;
                $('.close-modal, #wsp-modal-cancel').off().on('click', () => $('.wsp-modal-overlay').fadeOut(150));
            },
            prompt: function(title, def, cb) {
                var $ = WSP.$;
                $('#wsp-modal-title').text(title);
                $('#wsp-modal-input').val(def).show().focus();
                $('#wsp-custom-modal').css('display', 'flex').hide().fadeIn(200);
                $('#wsp-modal-ok').off().one('click', function() {
                    var val = $('#wsp-modal-input').val();
                    $('.wsp-modal-overlay').fadeOut(100);
                    if (val) cb(val.trim());
                });
            },
            setLoading: function(s) { WSP.$('#editor-loader').toggle(s); },
            notify: function(m, t) { console.log("["+t+"] " + m); },
            updateProjectDisplay: function(n) { WSP.$('#wsp-active-project-display span').text(n); },
            updateActiveFileUI: function(id, n, t) {
                var $ = WSP.$;
                $('#current-file').html('<i class="fa fa-file-code-o"></i> ' + n);
                $('.file-item').removeClass('active-file');
                $(`.load-file[data-id="${id}"]`).closest('.file-item').addClass('active-file');
            }
        },

        bindGlobalEvents: function() {
            var self = this, $ = this.$;

            // Toolbar - Eventos de ferramentas e menus
            $('body').off('click', '#btn-tb-new-project').on('click', '#btn-tb-new-project', () => self.project.create());
            $('body').off('click', '#menu-open-project').on('click', '#menu-open-project', () => self.project.openModal());
            $('body').off('click', '#btn-tb-save').on('click', '#btn-tb-save', (e) => { e.preventDefault(); self.file.save(); });
            $('body').off('click', '#btn-tb-gist').on('click', '#btn-tb-gist', () => self.tools.exportGist());
            $('body').off('click', '#btn-tb-purge-cache').on('click', '#btn-tb-purge-cache', () => self.tools.purgeCache());

            // Seleção de Projeto no Modal
            $('body').off('click', '.project-card').on('click', '.project-card', function() {
                var id = $(this).attr('data-id'), name = $(this).attr('data-name');
                $('.wsp-modal-overlay').fadeOut(200);
                self.project.loadSidebar(id, name);
            });

            // Outros modais estáticos (Search, Diff, Skeleton, etc)
            var staticModals = {
                '#btn-tb-search': '#wsp-search-modal',
                '#btn-tb-diff': '#wsp-diff-modal',
                '#btn-tb-skeleton': '#wsp-skeleton-modal',
                '#btn-tb-theme': '#wsp-theme-modal',
                '#btn-tb-shortcuts': '#wsp-shortcuts-modal'
            };
            $.each(staticModals, function(btn, mod) {
                $('body').off('click', btn).on('click', btn, () => $(mod).css('display', 'flex').hide().fadeIn(200));
            });

            // Navegação: Arquivos e Pastas
            $('body').off('click', '.load-file').on('click', '.load-file', (e) => self.file.open($(e.currentTarget).attr('data-id')));
            $('body').off('click', '.folder-title').on('click', '.folder-title', function() {
                $(this).next('.folder-content').slideToggle(150);
                $(this).find('i').toggleClass('fa-folder fa-folder-open');
            });
        },

        api: function(url, data, cb) {
            this.$.post(url, data, (r) => { if(r.success) cb(r); else this.ui.notify(r.error, 'error'); }, 'json');
        },

        editor: {
            instance: null,
            init: function() {
                if (typeof ace === 'undefined') return;
                this.instance = ace.edit("editor");
                this.instance.setTheme(WSP.state.currentTheme);
                this.instance.setOptions({ fontSize: "14px", wrap: true });
            }
        }
    };

})(window);