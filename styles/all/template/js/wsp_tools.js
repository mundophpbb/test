/**
 * Mundo phpBB Workspace - TOOLS
 * Árvore de Ficheiros, CRUD de Projetos, Menus Dropdown e Carregamento Dinâmico
 */
function initWspTools($) {
    'use strict';

    // --- FUNÇÃO: CARREGAR SIDEBAR (AJAX) ---
    window.loadProjectSidebar = function(id, name) {
        if (!id) return;
        
        window.activeProjectId = id;
        window.showNotification(wspVars.lang.loading + " " + name, "info");

        $.get(wspVars.mainUrl, { project_id: id }, function(data) {
            var $tempDiv = $('<div>').html(data);
            var newProjectListHtml = $tempDiv.find('#project-list').html();

            if (!newProjectListHtml || newProjectListHtml.trim() === "") {
                newProjectListHtml = '<div id="sidebar-empty-state" style="padding:20px;text-align:center;color:#888;">' + 
                                     '<i class="fa fa-folder-open-o fa-2x"></i><p>Projeto vazio.</p></div>';
            } else {
                // Limpa o estado vazio se houver conteúdo
                newProjectListHtml = newProjectListHtml.replace(/<div id="sidebar-empty-state"[\s\S]*?<\/div>/i, '');
            }

            // Atualiza o container principal
            $('#project-list').html(newProjectListHtml);

            // Sincroniza estado global e renderiza árvore
            if (typeof window.recoverActiveProjectId === 'function') {
                window.recoverActiveProjectId();
            }
            
            window.renderTree();

            // Re-bind imediato dos botões da toolbar (Novo Arquivo, Delete, etc)
            setTimeout(function() {
                if (typeof window.rebindProjectActions === 'function') {
                    window.rebindProjectActions();
                }
            }, 50);

            // UI Updates
            $('.project-actions').fadeIn();
            $('#current-file').html('<i class="fa fa-cube"></i> ' + name).css('color', '#e2c08d');
            
            console.log('%cSidebar Renderizada - ID:', 'color: #28a745', id);
        }).fail(function() {
            window.showNotification('Erro ao carregar projeto.', 'error');
        });
    };

    // --- FUNÇÃO: RENDERIZAÇÃO DA ÁRVORE ---
    window.renderTree = function() {
        $('.project-group').each(function() {
            var $fileList = $(this).find('.file-list');
            var files = [];

            // Captura arquivos brutos do HTML (gerados pelo PHP)
            $fileList.find('.file-item').each(function() {
                var $el = $(this);
                var $link = $el.find('.load-file');
                var fileName = $link.text().trim();
                
                files.push({
                    id: $link.attr('data-id'),
                    name: fileName,
                    type: $link.attr('data-type') || 'file',
                    html: $el[0].outerHTML
                });
            });

            // Se não houver caminhos com "/" (projeto plano), apenas limpa e sai
            if (!files.some(f => f.name.includes('/'))) {
                files.sort((a, b) => a.name.localeCompare(b.name, undefined, {sensitivity: 'base'}));
                $fileList.html(files.map(f => f.html).join(''));
                return;
            }

            // Constroi objeto de estrutura aninhada
            var structure = {};
            files.forEach(file => {
                var parts = file.name.split('/').filter(p => p !== '');
                var current = structure;

                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i];
                    var isLast = (i === parts.length - 1);
                    var isFolderType = file.type === 'folder' || file.name.endsWith('/');

                    if (!current[part]) {
                        current[part] = { _isDir: (isLast ? isFolderType : true), _children: {} };
                    }

                    if (isLast && !isFolderType) {
                        current[part] = { ...file, _isDir: false };
                    } else {
                        current = current[part]._children;
                    }
                }
            });

            // Função recursiva para montar o HTML final
            var buildHtml = function(obj, level = 0) {
                var html = '';
                var keys = Object.keys(obj).sort((a, b) => {
                    var aIsDir = obj[a]._isDir;
                    var bIsDir = obj[b]._isDir;
                    if (aIsDir !== bIsDir) return bIsDir - aIsDir; // Pastas primeiro
                    return a.localeCompare(b, undefined, { sensitivity: 'base' });
                });

                keys.forEach(key => {
                    var item = obj[key];
                    if (item._isDir) {
                        html += '<li class="folder-item" style="padding-left: ' + (level ? 15 : 0) + 'px;">' +
                                '<div class="folder-title"><i class="fa fa-folder fa-fw icon"></i> ' + key + '</div>' +
                                '<ul class="folder-content" style="display:none;">' + buildHtml(item._children || {}, level + 1) + '</ul></li>';
                    } else {
                        // Aplica ícones baseados na extensão
                        var icon = 'fa-file-text-o';
                        var ext = item.name.split('.').pop().toLowerCase();
                        
                        if (['php', 'inc', 'module'].includes(ext)) icon = 'fa-file-code-o';
                        else if (['js', 'ts', 'json'].includes(ext)) icon = 'fa-file-code-o';
                        else if (['css', 'scss', 'less'].includes(ext)) icon = 'fa-file-code-o';
                        else if (['yaml', 'yml', 'xml'].includes(ext)) icon = 'fa-file-text-o';
                        else if (ext === 'md') icon = 'fa-info-circle';
                        
                        var fileHtml = item.html.replace(/<i class="fa [^"]*"><\/i>/, '<i class="fa ' + icon + '"></i>');
                        html += '<div class="tree-file-wrapper" style="padding-left: ' + (level ? 15 : 0) + 'px;">' + fileHtml + '</div>';
                    }
                });
                return html;
            };

            $fileList.html(buildHtml(structure));
        });

        // Evento de abrir/fechar pasta (delegado)
        $('body').off('click', '.folder-title').on('click', '.folder-title', function(e) {
            e.stopPropagation();
            var $icon = $(this).find('.icon');
            $(this).next('.folder-content').slideToggle(150);
            $icon.toggleClass('fa-folder fa-folder-open');
        });
    };

    // --- MODAL: NOVO PROJETO ---
    $('body').off('click', '#menu-new-project').on('click', '#menu-new-project', function() {
        window.wspPrompt(wspVars.lang.prompt_name, '', function(name) {
            $.post(wspVars.addUrl, { name: name }, function(r) {
                if(r.success) {
                    window.showNotification("Projeto Criado!", "success");
                    window.loadProjectSidebar(r.id, r.name);
                } else {
                    window.showNotification(r.error, 'error');
                }
            }, 'json');
        });
    });

    // --- MODAL: ABRIR PROJETO ---
    $('body').off('click', '#menu-open-project').on('click', '#menu-open-project', function() {
        $('#project-list-select').html('<div style="text-align:center;padding:20px;"><i class="fa fa-refresh fa-spin fa-2x"></i></div>');
        $('#open-project-modal').css('display', 'flex').hide().fadeIn(200);

        $.get(wspVars.mainUrl, function(data) {
            var $projects = $(data).find('.project-card-hidden'); // O PHP deve gerar cards invisíveis para o modal
            $('#project-list-select').empty();

            if ($projects.length === 0) {
                $('#project-list-select').html('<p style="padding:20px; color:#888;">Nenhum projeto encontrado.</p>');
            } else {
                $projects.each(function() {
                    $('#project-list-select').append(
                        '<div class="project-card" data-id="'+$(this).attr('data-id')+'">' +
                        '<i class="fa fa-folder"></i> <span>'+$(this).attr('data-name')+'</span></div>'
                    );
                });
            }
        });
    });

    $('body').off('click', '.project-card').on('click', '.project-card', function() {
        var id = $(this).attr('data-id');
        var name = $(this).find('span').text();
        $('#open-project-modal').fadeOut(200);
        window.loadProjectSidebar(id, name);
    });

    // --- FERRAMENTA: FILTRO DA SIDEBAR ---
    $('body').on('input', '#wsp-sidebar-filter', function() {
        var term = $(this).val().toLowerCase();
        
        $('.file-item').each(function() {
            var fileName = $(this).text().toLowerCase();
            if (fileName.indexOf(term) > -1) {
                $(this).show();
                // Expande as pastas pais se houver match
                $(this).parents('.folder-content').show().prev('.folder-title').find('.icon').removeClass('fa-folder').addClass('fa-folder-open');
            } else {
                $(this).hide();
            }
        });

        // Esconde pastas vazias após o filtro
        $('.folder-item').each(function() {
            var hasVisibleChildren = $(this).find('.file-item:visible').length > 0;
            $(this).toggle(hasVisibleChildren);
        });
    });

    // Inicialização automática
    window.renderTree();
}