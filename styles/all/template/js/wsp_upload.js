/**
 * Mundo phpBB Workspace - Upload & DragDrop
 * Versão Blindada: Suporte a pastas vazias, normalização de paths e filtro de arquivos de sistema.
 */
WSP.upload = {
    _refreshTimer: null,
    _pendingUploads: 0,

    /**
     * Normaliza caminhos para o padrão Unix (relativo ao projeto)
     */
    _normalizePath: function(p) {
        if (!p) return '';
        p = p.toString().trim().replace(/\\/g, '/');
        p = p.replace(/^\/+/, '');              // Remove / inicial
        p = p.replace(/(\.\.\/)+/g, '');        // Bloqueia traversal
        p = p.replace(/(^|\/)\.\//g, '$1');     // Remove ./
        p = p.replace(/\/{2,}/g, '/');          // Colapsa barras duplas
        return p;
    },

    /**
     * Filtra arquivos temporários de sistemas operacionais que poluem o projeto
     */
    _isIgnoredFile: function(name) {
        var ignored = ['thumbs.db', '.ds_store', 'desktop.ini', '__macosx'];
        var base = name.toLowerCase();
        return ignored.some(item => base.includes(item));
    },

    /**
     * Debounce: Só atualiza a Sidebar quando TODOS os uploads da sequência terminarem.
     */
    _scheduleRefresh: function() {
        var self = this;
        clearTimeout(self._refreshTimer);
        self._refreshTimer = setTimeout(function() {
            if (self._pendingUploads <= 0) {
                self._pendingUploads = 0; // Reset de segurança
                WSP.ui.seamlessRefresh();
                WSP.ui.notify("Lista de arquivos atualizada.", "success");
            }
        }, 800); // Aguarda 800ms após o último arquivo terminar
    },

    /**
     * Envia o arquivo para o servidor via AJAX
     */
    performUpload: function(file, projectId, customPath) {
        var self = this;
        if (!file || !projectId) return;

        var finalPath = self._normalizePath(customPath || file.name);
        if (self._isIgnoredFile(finalPath)) return;

        // Leitura do arquivo para converter em Base64 (Opcional, mas resolve 503 por Firewall)
        var reader = new FileReader();
        reader.onload = function(e) {
            var formData = new FormData();
            // Em vez de enviar o File object direto, enviamos o conteúdo lido
            formData.append('file_content', e.target.result); 
            formData.append('project_id', projectId);
            formData.append('full_path', finalPath);
            formData.append('is_encoded', '1'); // Sinaliza ao PHP que deve dar base64_decode

            self._pendingUploads++;
            $.ajax({
                url: wspVars.uploadUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(r) {
                    self._pendingUploads--;
                    if (!r || !r.success) {
  var msg = (r && r.error) ? r.error : ("Falha em " + finalPath);
  WSP.ui.notify(msg, "error");
}
                    self._scheduleRefresh();
                }
            });
        };
        reader.readAsDataURL(file); // Converte para DataURL (Base64)
    },
    /**
     * Varre pastas arrastadas (API Webkit)
     */
    traverseFileTree: function(item, path, projectId) {
        var self = this;
        path = self._normalizePath(path || '');
        if (path && !path.endsWith('/')) path += '/';

        if (!item) return;

        if (item.isFile) {
            item.file(function(file) {
                self.performUpload(file, projectId, path + file.name);
            });
        } else if (item.isDirectory) {
            var dirReader = item.createReader();
            dirReader.readEntries(function(entries) {
                // TRATAMENTO DE PASTA VAZIA: Cria .placeholder para o PHP registrar a pasta
                if (entries.length === 0) {
                    var blob = new Blob([""], { type: 'text/plain' });
                    var placeholder = new File([blob], ".placeholder");
                    self.performUpload(placeholder, projectId, path + item.name + "/.placeholder");
                } else {
                    for (var i = 0; i < entries.length; i++) {
                        self.traverseFileTree(entries[i], path + item.name + "/", projectId);
                    }
                }
            });
        }
    },

    bindEvents: function($) {
        var self = this;

        // 1. UPLOAD VIA BOTÃO (TOOLBAR)
        $('body').off('click', '.trigger-upload').on('click', '.trigger-upload', function(e) {
            e.preventDefault();
            if (!WSP.activeProjectId) return WSP.ui.notify("Selecione um projeto primeiro.", "warning");

            if ($('#wsp-upload-input').length === 0) {
                $('body').append('<input type="file" id="wsp-upload-input" multiple style="display:none;">');
            }
            $('#wsp-upload-input').click();
        });

        $('body').off('change', '#wsp-upload-input').on('change', '#wsp-upload-input', function() {
            var files = this.files;
            if (files.length > 0) {
                WSP.ui.notify("Enviando " + files.length + " arquivo(s)...", "info");
                for (var i = 0; i < files.length; i++) {
                    self.performUpload(files[i], WSP.activeProjectId, null);
                }
            }
            $(this).val(''); // Limpa para permitir re-upload do mesmo arquivo
        });

        // 2. DRAG & DROP (SIDEBAR)
        var $zone = $('#sidebar-dropzone');
        if ($zone.length) {
            $('body').on('dragover', '#sidebar-dropzone', function(e) {
                e.preventDefault();
                $(this).addClass('sidebar-drag-active');
            });

            $('body').on('dragleave', '#sidebar-dropzone', function(e) {
                e.preventDefault();
                $(this).removeClass('sidebar-drag-active');
            });

            $('body').on('drop', '#sidebar-dropzone', function(e) {
                e.preventDefault();
                $(this).removeClass('sidebar-drag-active');

                if (!WSP.activeProjectId) return WSP.ui.notify("Abra um projeto para soltar arquivos.", "warning");

                var dt = e.originalEvent.dataTransfer;
                var items = dt.items;

                WSP.ui.notify("Processando upload...", "info");

                // Se houver uma pasta aberta na sidebar, os arquivos caem nela
                var prefix = WSP.activeFolderPath || "";

                if (items && items.length) {
                    for (var i = 0; i < items.length; i++) {
                        var entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
                        if (entry) {
                            self.traverseFileTree(entry, prefix, WSP.activeProjectId);
                        } else {
                            var f = items[i].getAsFile();
                            if (f) self.performUpload(f, WSP.activeProjectId, self._normalizePath(prefix) + '/' + f.name);
                        }
                    }
                }
            });
        }
    }
};