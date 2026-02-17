/**
 * Mundo phpBB Workspace - Módulo Editor & Arquivos
 * Integrado ao Core Modular
 */

// --- GAVETA DO EDITOR (Ace Interface) ---
WSP.editor = {
    ace: null,
    originalContent: "",
    modes: {
        'php': 'ace/mode/php', 'js': 'ace/mode/javascript', 'ts': 'ace/mode/typescript',
        'css': 'ace/mode/css', 'scss': 'ace/mode/scss', 'html': 'ace/mode/html',
        'py': 'ace/mode/python', 'sql': 'ace/mode/sql', 'yml': 'ace/mode/yaml',
        'json': 'ace/mode/json', 'md': 'ace/mode/markdown', 'diff': 'ace/mode/diff'
    },

    init: function() {
        if (typeof ace === 'undefined') return console.error('Ace Editor não carregado!');

        this.ace = ace.edit("editor");
        this.ace.setTheme("ace/theme/monokai");
        this.ace.setOptions({
            fontSize: "14px",
            fontFamily: "Consolas, 'Courier New', monospace",
            showPrintMargin: false,
            wrap: true,
            useSoftTabs: true,
            tabSize: 4,
            highlightActiveLine: true,
            enableBasicAutocompletion: true
        });

        // Mensagem inicial bloqueada
        this.ace.setValue("Selecione um arquivo na sidebar para começar...", -1);
        this.ace.setReadOnly(true);

        // Monitor de modificações (Dirty indicator)
        this.ace.on("input", () => this.syncDirtyState());

        console.log('[WSP] Editor Inicializado.');
    },

    // Sincroniza o asterisco (*) na sidebar se o texto mudar
    syncDirtyState: function() {
        if (!WSP.state.activeFileId) return;
        
        var current = this.ace.getValue();
        var changed = (current !== this.originalContent);
        
        if (WSP.state.isDirty !== changed) {
            WSP.state.isDirty = changed;
            WSP.ui.toggleFileAsterisk(WSP.state.activeFileId, changed);
        }
    }
};

// --- GAVETA DE ARQUIVOS (I/O) ---
WSP.file = {
    // Carrega arquivo do servidor para o editor
    open: function(fileId) {
        // Proteção: Não deixa fechar se tiver alteração não salva
        if (WSP.state.isDirty && !confirm("Você tem alterações não salvas. Deseja descartá-las?")) {
            return;
        }

        WSP.ui.setLoading(true);

        WSP.api(WSP.config.loadUrl, { file_id: fileId }, function(r) {
            WSP.state.activeFileId = fileId;
            WSP.state.isDirty = false;
            WSP.editor.originalContent = r.content;

            // Prepara o Ace Editor
            WSP.editor.ace.setReadOnly(false);
            WSP.editor.ace.session.setMode(WSP.editor.modes[r.type] || 'ace/mode/text');
            WSP.editor.ace.setValue(r.content, -1);
            WSP.editor.ace.focus();

            // Atualiza a Interface
            $('#current-file').html('<i class="fa fa-file-code-o"></i> ' + r.name).css('color', '#569cd6');
            $('.file-item').removeClass('active-file');
            $('.load-file[data-id="'+fileId+'"]').closest('.file-item').addClass('active-file');
            
            WSP.ui.setLoading(false);
            console.log('[WSP] Arquivo Aberto:', r.name);
        });
    },

    // Salva o conteúdo atual no banco de dados
    save: function() {
        if (!WSP.state.activeFileId) return;

        var content = WSP.editor.ace.getValue();
        var $btn = $('#save-file');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

        WSP.api(WSP.config.saveUrl, { 
            file_id: WSP.state.activeFileId, 
            content: content 
        }, function(r) {
            WSP.editor.originalContent = content;
            WSP.state.isDirty = false;
            
            // UI Feedback
            WSP.ui.toggleFileAsterisk(WSP.state.activeFileId, false);
            WSP.ui.notify('Arquivo salvo com sucesso!', 'success');
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar');
        });
    }
};