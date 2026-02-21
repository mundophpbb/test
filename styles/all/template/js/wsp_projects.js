/**
 * Mundo phpBB Workspace - Project Operations
 * Versão 2.9: Fix de Subpastas + Toolbar Integrada + UI Persistence
 */
WSP.projects = {
    _normalizePath: function (p) {
        if (!p) return '';
        p = (p || '').trim().replace(/\\/g, '/');
        p = p.replace(/^\/+/, '').replace(/\/+$/, '');
        return p;
    },

    _joinPath: function (base, name) {
        base = this._normalizePath(base);
        name = this._normalizePath(name);
        return base ? base + '/' + name : name;
    },

    bindEvents: function ($) {
        var self = this;
        // Limpa eventos anteriores para evitar execução dupla
        $('body').off('click.wsp_projects');

        // --- 1. PROJETOS (TOOLBAR GLOBAL) ---
        
        // Criar Novo Projeto
        $('body').on('click.wsp_projects', '#add-project', function () {
            WSP.ui.prompt(window.wspVars.lang.prompt_name || "Nome do novo projeto:", '', function (name) {
                if (!name) return;
                $.post(window.wspVars.addUrl, { name: name }, function (r) {
                    if (r && r.success) window.location.href = window.location.href.split('?')[0] + '?p=' + r.project_id;
                }, 'json');
            });
        });

        // Abrir Switcher de Projetos
        $('body').on('click.wsp_projects', '#open-project', function () {
            var $projects = $('.project-group');
            if ($projects.length === 0) return WSP.ui.notify("Nenhum projeto encontrado.", "warning");

            var listHtml = '<div class="project-switcher-box" style="display:flex; flex-direction:column; gap:10px;">';
            $projects.each(function () {
                var pid = $(this).data('project-id');
                var pname = $(this).find('.project-title-simple').text().trim();
                var isActive = (String(pid) === String(WSP.activeProjectId));
                
                listHtml += `
                    <div class="switcher-item ${isActive ? 'active' : ''}" 
                         style="padding:15px; background:#333; border:1px solid #444; cursor:pointer; display:flex; align-items:center; gap:12px;"
                         onclick="window.location.href='?p=${pid}'">
                        <i class="fa ${isActive ? 'fa-folder-open' : 'fa-folder'}" style="color:var(--wsp-folder); font-size:18px;"></i>
                        <div style="display:flex; flex-direction:column; text-align:left;">
                            <span style="color:#fff; font-weight:bold; font-size:14px;">${$('<div/>').text(pname).html()}</span>
                            ${isActive ? '<small style="color:var(--wsp-accent)">Projeto Ativo</small>' : '<small style="color:#888">Clique para abrir</small>'}
                        </div>
                    </div>`;
            });
            listHtml += '</div>';

            WSP.ui.prompt("SELECIONE O PROJETO", 'LIST_MODE');
            $('#wsp-modal-body-custom').html(listHtml).show();
        });

        // Renomear Projeto Ativo (Acionado pela Toolbar)
        $('body').on('click.wsp_projects', '.rename-active-project', function () {
            if (!WSP.activeProjectId) return WSP.ui.notify("Nenhum projeto ativo.", "warning");
            
            var oldName = $('.project-group.active-focus .project-title-simple').text().trim();
            WSP.ui.prompt("Novo nome do Projeto:", oldName, function (newName) {
                if (!newName || newName === oldName) return;
                
                $.post(window.wspVars.renameProjectUrl, { project_id: WSP.activeProjectId, new_name: newName }, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Projeto renomeado!");
                        window.location.reload();
                    } else {
                        WSP.ui.notify(r.error || "Erro ao renomear.", "error");
                    }
                }, 'json');
            });
        });

        // Deletar Projeto Ativo (Acionado pela Toolbar)
        $('body').on('click.wsp_projects', '.delete-project', function () {
            if (!WSP.activeProjectId) return;
            
            WSP.ui.confirm("Deseja APAGAR este projeto e todos os seus arquivos permanentemente?", function () {
                $.post(window.wspVars.deleteUrl, { project_id: WSP.activeProjectId }, function (r) {
                    if (r && r.success) {
                        window.location.href = window.location.href.split('?')[0];
                    } else {
                        WSP.ui.notify(r.error || "Erro ao excluir.", "error");
                    }
                }, 'json');
            });
        });

// --- 2. ARQUIVOS (SIDEBAR - CONTEXTO) ---
        
        // CORRIGIDO: Renomear Arquivo preservando a hierarquia de pastas
        $('body').on('click.wsp_projects', '.ctx-rename-file', function (e) {
            e.stopPropagation();
            var $item = $(this).closest('.file-item');
            var $link = $item.find('.load-file');
            
            var id = $link.data('id');
            var fullPath = $link.attr('data-path') || $link.text(); 
            
            var pathParts = fullPath.split('/');
            var oldFileName = pathParts.pop(); // Pega apenas o nome do arquivo
            var folderPrefix = pathParts.join('/'); // Pega o caminho da pasta

            WSP.ui.prompt("Renomear arquivo:", oldFileName, function (newName) {
                var cleanName = newName.replace(/[\/\\?%*:|"<>]/g, '').trim();
                if (!cleanName || cleanName === oldFileName) return;

                // PROTEÇÃO: Reconstrói o caminho completo para o arquivo NÃO pular para a raiz
                var newFullPath = folderPrefix ? folderPrefix + '/' + cleanName : cleanName;

                $.post(window.wspVars.renameUrl, { file_id: id, new_name: newFullPath }, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Arquivo renomeado!");
                        WSP.ui.seamlessRefresh();
                    } else {
                        WSP.ui.notify(r.error || "Erro ao renomear.", "error");
                    }
                }, 'json');
            });
        });

        // CORRIGIDO: Deletar Arquivo com proteção de estado do editor
        $('body').on('click.wsp_projects', '.ctx-delete-file', function (e) {
            e.stopPropagation();
            var $link = $(this).closest('.file-item').find('.load-file');
            var id = $link.data('id');
            
            WSP.ui.confirm("Excluir este arquivo permanentemente?", function () {
                $.post(window.wspVars.deleteFileUrl, { file_id: id }, function (r) {
                    if (r && r.success) {
                        // Se o arquivo deletado era o que estava aberto, limpa o editor
                        if (String(WSP.activeFileId) === String(id)) {
                            WSP.activeFileId = null;
                            WSP.editor.setValue(window.wspVars.lang.welcome_msg || '', -1);
                            WSP.editor.setReadOnly(true);
                        }
                        WSP.ui.seamlessRefresh();
                    } else {
                        WSP.ui.notify(r.error || "Erro ao excluir.", "error");
                    }
                }, 'json');
            });
        });

        // CORRIGIDO: Deletar Arquivo
        $('body').on('click.wsp_projects', '.ctx-delete-file', function (e) {
            e.stopPropagation();
            var id = $(this).closest('.file-item').find('.load-file').data('id') || $(this).data('id');
            
            WSP.ui.confirm("Excluir este arquivo permanentemente?", function () {
                $.post(window.wspVars.deleteFileUrl, { file_id: id }, function (r) {
                    if (r && r.success) {
                        if (String(WSP.activeFileId) === String(id)) {
                            WSP.activeFileId = null;
                            WSP.editor.setValue(window.wspVars.lang.welcome_msg || '', -1);
                            WSP.editor.setReadOnly(true);
                        }
                        WSP.ui.seamlessRefresh();
                    } else {
                        WSP.ui.notify(r.error || "Erro ao excluir.", "error");
                    }
                }, 'json');
            });
        });

        $('body').on('click.wsp_projects', '.ctx-delete-file', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            WSP.ui.confirm("Excluir este arquivo?", function () {
                $.post(window.wspVars.deleteFileUrl, { file_id: id }, function (r) {
                    if (r && r.success) {
                        if (String(WSP.activeFileId) === String(id)) {
                            WSP.activeFileId = null;
                            WSP.editor.setValue(window.wspVars.lang.welcome_msg || '', -1);
                            WSP.editor.setReadOnly(true);
                        }
                        WSP.ui.seamlessRefresh();
                    }
                }, 'json');
            });
        });

        // --- 3. PASTAS (SIDEBAR - CONTEXTO) ---

        $('body').on('click.wsp_projects', '.ctx-rename-folder', function (e) {
            e.stopPropagation();
            var oldPath = self._normalizePath(WSP.activeFolderPath);
            if (!oldPath) return;

            var parts = oldPath.split('/');
            var oldFolderName = parts.pop();
            var parentPath = parts.join('/');

            WSP.ui.prompt("Novo nome da pasta:", oldFolderName, function (newName) {
                var clean = newName.replace(/[\/\\?%*:|"<>]/g, '').trim();
                if (!clean || clean === oldFolderName) return;

                var newPath = parentPath ? parentPath + '/' + clean : clean;

                $.post(window.wspVars.renameFolderUrl, {
                    project_id: WSP.activeProjectId,
                    old_path: oldPath,
                    new_path: newPath
                }, function (r) {
                    if (r && r.success) {
                        WSP.activeFolderPath = newPath;
                        WSP.ui.notify("Pasta renomeada!");
                        WSP.ui.seamlessRefresh();
                    } else {
                        WSP.ui.notify(r.error || "Erro na operação.", "error");
                    }
                }, 'json');
            });
        });

        $('body').on('click.wsp_projects', '.ctx-add-file', function (e) {
            e.stopPropagation();
            var path = WSP.activeFolderPath;
            WSP.ui.prompt("Novo arquivo em /" + path, '', function (name) {
                if (!name) return;
                var full = self._joinPath(path, name);
                $.post(window.wspVars.addFileUrl, { project_id: WSP.activeProjectId, name: full }, function (r) {
                    if (r && r.success) WSP.ui.seamlessRefresh(r.file_id);
                }, 'json');
            });
        });

        // Criar Subpasta (Garante o Placeholder para renderização imediata)
        $('body').on('click.wsp_projects', '.ctx-add-folder', function (e) {
            e.stopPropagation();
            var path = WSP.activeFolderPath; 
            
            WSP.ui.prompt("Nova subpasta em /" + path, '', function (name) {
                if (!name) return;
                var full = self._joinPath(path, name) + '/.placeholder';
                
                $.post(window.wspVars.addFileUrl, { 
                    project_id: WSP.activeProjectId, 
                    name: full 
                }, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Pasta criada com sucesso!");
                        WSP.ui.seamlessRefresh(); 
                    } else {
                        WSP.ui.notify(r.error || "Erro ao criar pasta.", "error");
                    }
                }, 'json');
            });
        });

        $('body').on('click.wsp_projects', '.ctx-delete-folder', function (e) {
            e.stopPropagation();
            var path = self._normalizePath(WSP.activeFolderPath);
            WSP.ui.confirm("Apagar a pasta /" + path + " e todo o seu conteúdo?", function () {
                $.post(window.wspVars.deleteFolderUrl, { project_id: WSP.activeProjectId, path: path }, function (r) {
                    if (r && r.success) {
                        WSP.activeFolderPath = '';
                        WSP.ui.seamlessRefresh();
                    }
                }, 'json');
            });
        });

        // --- 4. TOOLBAR (AÇÕES NA RAIZ) ---
        
        $('body').on('click.wsp_projects', '.add-file-to-project', function () {
            if (!WSP.activeProjectId) return;
            WSP.ui.prompt("Novo arquivo na Raiz:", '', function (name) {
                if (!name) return;
                $.post(window.wspVars.addFileUrl, { project_id: WSP.activeProjectId, name: name }, function (r) {
                    if (r && r.success) WSP.ui.seamlessRefresh(r.file_id);
                }, 'json');
            });
        });

        $('body').on('click.wsp_projects', '.add-folder-to-project', function () {
            if (!WSP.activeProjectId) return;
            WSP.ui.prompt("Nova pasta na Raiz:", '', function (name) {
                if (!name) return;
                var placeholder = name + '/.placeholder';
                $.post(window.wspVars.addFileUrl, { project_id: WSP.activeProjectId, name: placeholder }, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Pasta criada!");
                        WSP.ui.seamlessRefresh();
                    }
                }, 'json');
            });
        });

        // --- 5. CHANGELOG LOGIC ---
        
        $('body').on('click.wsp_projects', '.generate-project-changelog', function () {
            if (!WSP.activeProjectId) return;
            $.post(window.wspVars.changelogUrl, { project_id: WSP.activeProjectId }, function (r) {
                if (r && r.success) {
                    WSP.ui.notify("Changelog consolidado!");
                    if ($('.active-file .load-file').text().trim() === 'changelog.txt') {
                        $('.active-file .load-file').click();
                    }
                }
            }, 'json');
        });

        $('body').on('click.wsp_projects', '.clear-project-changelog', function () {
            if (!WSP.activeProjectId) return;
            WSP.ui.confirm("Limpar todo o histórico do changelog?", function () {
                $.post(window.wspVars.clearChangelogUrl, { project_id: WSP.activeProjectId }, function (r) {
                    if (r && r.success) {
                        WSP.ui.notify("Histórico resetado!");
                        if ($('.active-file .load-file').text().trim() === 'changelog.txt') {
                            $('.active-file .load-file').click();
                        }
                    }
                }, 'json');
            });
        });
    }
};