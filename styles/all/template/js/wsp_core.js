/**
 * Mundo phpBB Workspace - Core & State
 * Centraliza o estado global, configurações do editor e validações de segurança.
 */
(function (window, $) {
    'use strict';

    // Fallback de segurança para evitar quebras em páginas comuns do fórum
    if (typeof window.wspVars === 'undefined' || !window.wspVars) {
        window.wspVars = {
            basePath: '',
            allowedExt: '',
            activeProjectId: 0,
            lang: {
                welcome_msg: 'Selecione um projeto para começar.',
                select_file: 'Selecione um arquivo'
            }
        };
    }

    window.WSP = {
        activeFileId: null,
        activeProjectId: null,
        activeFolderPath: '', // Armazena a pasta selecionada na árvore
        originalContent: "",
        editor: null,
        allowedExtensions: [],

        // Mapa de linguagens do Ace Editor
        modes: {
            'php': 'ace/mode/php', 'js': 'ace/mode/javascript', 'ts': 'ace/mode/typescript',
            'html': 'ace/mode/html', 'htm': 'ace/mode/html', 'css': 'ace/mode/css',
            'json': 'ace/mode/json', 'xml': 'ace/mode/xml', 'yml': 'ace/mode/yaml',
            'yaml': 'ace/mode/yaml', 'sql': 'ace/mode/sql', 'md': 'ace/mode/markdown',
            'txt': 'ace/mode/text', 'twig': 'ace/mode/twig', 'htaccess': 'ace/mode/apache_conf'
        },

        /**
         * Inicializa o Editor ACE
         */
        initEditor: function () {
            if (typeof window.ace === 'undefined') return false;
            if (!document.getElementById('editor')) return false;

            // Se o editor já foi inicializado, não faz nada (evita duplicidade em refresh)
            if (this.editor) return true;

            // Configura caminhos internos do Ace
            if (window.wspVars.basePath) {
                ace.config.set("basePath", window.wspVars.basePath);
                ace.config.set("modePath", window.wspVars.basePath);
                ace.config.set("themePath", window.wspVars.basePath);
            }

            this.editor = ace.edit("editor");
            this.editor.setTheme("ace/theme/monokai");
            this.editor.setOptions({
                fontSize: "14px",
                fontFamily: "Consolas, 'Courier New', monospace",
                showPrintMargin: false,
                displayIndentGuides: true,
                highlightActiveLine: true,
                behavioursEnabled: true,
                wrap: true,
                tabSize: 4,
                useSoftTabs: true,
                scrollPastEnd: 0.5,
                readOnly: true // Começa travado até abrir um arquivo
            });

            this.editor.session.setUseWorker(false);

            // Carrega whitelist do PHP
            this.allowedExtensions = this.parseAllowedExtensions(window.wspVars.allowedExt);

            // Sincroniza Projeto Ativo
            this.activeProjectId = this.normalizeProjectId(window.wspVars.activeProjectId);

            // Ajuste de tamanho automático
            var self = this;
            window.addEventListener('resize', function() {
                if (self.editor) self.editor.resize();
            });

            this.updateUIState();
            return true;
        },

        /**
         * Normaliza o ID do projeto para evitar conflitos de tipo (string vs int)
         */
        normalizeProjectId: function (value) {
            if (!value || value === '0' || value === 0) return null;
            return parseInt(value, 10);
        },

        parseAllowedExtensions: function (str) {
            if (!str || typeof str !== 'string') return [];
            return str.split(',').map(s => s.trim().toLowerCase()).filter(s => s.length > 0);
        },

        /**
         * Validação de segurança no Frontend
         */
        isExtensionAllowed: function (filename) {
            if (!filename) return false;
            const name = filename.trim().toLowerCase();

            // Arquivos técnicos sempre permitidos
            if (name === '.placeholder' || name === '.htaccess' || name === 'changelog.txt') return true;

            const parts = name.split('.');
            if (parts.length === 1) return true; // Permite arquivos sem extensão (ex: LICENSE)

            const ext = parts.pop();
            return this.allowedExtensions.indexOf(ext) !== -1;
        },

        /**
         * Gerencia o estado visual da Toolbar e do Editor
         */
        updateUIState: function () {
            const hasProject = !!this.activeProjectId;
            const hasFile = !!this.activeFileId;

            const $toolbarActions = $('.actions-active');
            const $saveBtn = $('#save-file');
            const $bbcodeBtn = $('#copy-bbcode');
            const $currentFileLabel = $('#current-file');

            if (!hasProject) {
                // Estado: Nada aberto
                $toolbarActions.css({ opacity: '0.4', pointerEvents: 'none' });
                if (this.editor) {
                    this.editor.setReadOnly(true);
                    this.editor.setValue(window.wspVars.lang.welcome_msg || "Selecione um projeto.", -1);
                }
                $saveBtn.hide();
                $bbcodeBtn.hide();
                $currentFileLabel.text(window.wspVars.lang.select_file || 'Selecione um arquivo');
            } else {
                // Estado: Projeto Ativo
                $toolbarActions.css({ opacity: '1', pointerEvents: 'auto' });
                
                if (!hasFile) {
                    if (this.editor) this.editor.setReadOnly(true);
                    $saveBtn.hide();
                    $bbcodeBtn.hide();
                    // Não limpa o label aqui para não apagar o nome do projeto na sidebar
                } else {
                    if (this.editor) this.editor.setReadOnly(false);
                }
            }
        }
    };

})(window, jQuery);