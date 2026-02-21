/**
 * Mundo phpBB Workspace - File Tree Logic
 * Versão 2.8: Blidagem de Hierarquia + Fix de Renderização de Subpastas
 */
WSP.tree = {
    activeFolderPath: '',

    _normalizePath: function (p) {
        if (!p) return '';
        p = (p || '').trim().replace(/\\/g, '/');
        p = p.replace(/^\/+/, '');
        p = p.replace(/(\.\.\/)+/g, '');
        p = p.replace(/(^|\/)\.\//g, '$1');
        p = p.replace(/\/{2,}/g, '/');
        return p;
    },

    _ensureTrailingSlash: function (p) {
        p = this._normalizePath(p);
        if (p && !p.endsWith('/')) p += '/';
        return p;
    },

    _escapeHtml: function (s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    },

    _isPlaceholder: function (name) {
        var s = (name || '').toLowerCase().trim();
        return (s === '.placeholder' || s.indexOf('/.placeholder') !== -1);
    },

    _syncActiveFolderIndicator: function () {
        var path = (this.activeFolderPath || '').trim();
        var display = path ? path.replace(/\/$/, '') : (window.wspVars.lang.root || 'Raiz');

        if (jQuery('#wsp-active-folder-text').length) {
            jQuery('#wsp-active-folder-text').text(display);
        }

        var $container = jQuery('#wsp-main-container');
        if ($container.length) {
            $container.attr('data-active-folder', path);
        }
    },

    clearActiveFolder: function () {
        this.activeFolderPath = '';
        WSP.activeFolderPath = '';
        jQuery('.folder-title').removeClass('active-folder');
        this._syncActiveFolderIndicator();
    },

    _selectFolder: function ($folderTitle) {
        if (!$folderTitle || !$folderTitle.length) return;
        var path = $folderTitle.attr('data-path') || '';
        path = this._ensureTrailingSlash(path);
        this.activeFolderPath = path;
        WSP.activeFolderPath = path; 
        jQuery('.folder-title').removeClass('active-folder');
        $folderTitle.addClass('active-folder');
        this._syncActiveFolderIndicator();
    },

    /**
     * Renderização da Árvore Hierárquica
     */
    render: function ($) {
        var self = this;

        $('.project-group').each(function () {
            var $project = $(this),
                $fileList = $project.find('.file-list'),
                files = [];

            // 1. Coleta itens brutos vindos do banco
            $fileList.find('.file-item').each(function () {
                var $link = $(this).find('.load-file');
                if (!$link.length) return;

                files.push({
                    id: $link.data('id'),
                    name: ($link.attr('data-path') || $link.text()).trim(),
                    html: $(this)[0].outerHTML
                });
            });

            // 2. Reconstrói a estrutura lógica
            $fileList.empty();
            var structure = {};

            files.forEach(function (file) {
                var name = self._normalizePath(file.name);
                var parts = name.split('/');
                var current = structure;

                // GARANTIA DE NÓS: Registra todos os diretórios do caminho
                for (var i = 0; i < parts.length - 1; i++) {
                    var part = parts[i];
                    if (!part) continue;
                    if (!current[part]) {
                        current[part] = { _isDir: true, _children: {} };
                    }
                    current = current[part]._children;
                }

                // Adiciona o arquivo final
                var leaf = parts[parts.length - 1];
                if (leaf) {
                    current[leaf] = file;
                }
            });

            // 3. Função recursiva para gerar HTML visual
            var buildHtml = function (obj, currentPath) {
                currentPath = currentPath || '';
                var html = '';
                var keys = Object.keys(obj).sort(function (a, b) {
                    var aIsDir = obj[a]._isDir ? 1 : 0;
                    var bIsDir = obj[b]._isDir ? 1 : 0;
                    return (bIsDir - aIsDir) || a.localeCompare(b);
                });

                keys.forEach(function (key) {
                    if (key === '_isDir' || key === '_children') return;

                    // Renderiza Pasta
                    if (obj[key] && obj[key]._isDir) {
                        var fullFolderPath = self._ensureTrailingSlash(currentPath + key);
                        html += `
                            <li class="folder-item">
                                <div class="folder-title" data-path="${self._escapeHtml(fullFolderPath)}">
                                    <i class="icon fa fa-folder fa-fw"></i> ${self._escapeHtml(key)}
                                    <span class="folder-context-actions">
                                        <i class="fa fa-plus-square ctx-add-file" title="Novo Arquivo"></i>
                                        <i class="fa fa-plus-circle ctx-add-folder" title="Nova Subpasta"></i>
                                        <i class="fa fa-i-cursor ctx-rename-folder" title="Renomear Pasta"></i>
                                        <i class="fa fa-trash ctx-delete-folder" title="Deletar Pasta"></i>
                                    </span>
                                </div>
                                <ul class="folder-content" style="display:none;">${buildHtml(obj[key]._children, fullFolderPath)}</ul>
                            </li>`;
                        return;
                    }

                    // OCULTA PLACEHOLDERS: A pasta foi mantida pela lógica acima, mas o arquivo fantasma some
                    if (self._isPlaceholder(key)) return;

                    var fileId = obj[key].id;
                    var fullFilePath = self._normalizePath(obj[key].name);

                    html += `
                        <li class="file-item">
                            <i class="icon fa fa-file-text-o fa-fw"></i> 
                            <a href="javascript:void(0);" class="load-file" data-id="${fileId}" data-path="${self._escapeHtml(fullFilePath)}">${self._escapeHtml(key)}</a>
                            <span class="file-context-actions">
                                <i class="fa fa-pencil ctx-rename-file" data-id="${fileId}" data-name="${self._escapeHtml(key)}"></i>
                                <i class="fa fa-trash-o ctx-delete-file" data-id="${fileId}"></i>
                            </span>
                        </li>`;
                });
                return html;
            };

            $fileList.append(buildHtml(structure, ''));
        });
        
        this._syncActiveFolderIndicator();
    },

    bindEvents: function ($) {
        var self = this;
        $('body').off('click.wsp_tree');

        $('body').on('click.wsp_tree', '.folder-title', function (e) {
            if ($(e.target).closest('.folder-context-actions').length) return;
            var $title = $(this);
            self._selectFolder($title);
            $title.next('.folder-content').slideToggle(150);
            var $icon = $title.find('i.icon');
            $icon.toggleClass('fa-folder fa-folder-open');
        });

        $('body').on('click.wsp_tree', '.folder-context-actions i', function (e) {
            e.stopPropagation();
            var $title = $(this).closest('.folder-title');
            self._selectFolder($title);
        });

        $('body').on('click.wsp_tree', '#wsp-active-folder-indicator', function (e) {
            e.preventDefault();
            self.clearActiveFolder();
        });
    }
};