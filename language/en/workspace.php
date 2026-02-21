<?php
/**
 * mundophpbb workspace extension [English]
 *
 * @package mundophpbb workspace
 * @copyright (c) 2026 mundophpbb
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(

    // =====================================================
    // Main Interface
    // =====================================================
    'WSP_TITLE'                 => 'Mundo phpBB Workspace',
    'WSP_EXPLORER'              => 'Explorer',
    'WSP_PROJECT_LABEL'         => 'Project',
    'WSP_SELECT_FILE'           => 'Select a file to edit',
    'WSP_SELECT_TO_BEGIN'       => 'Open or create a project to list files.',

    // Active folder (header)
    'WSP_ACTIVE_FOLDER'         => 'Folder',
    'WSP_ACTIVE_FOLDER_TITLE'   => 'Currently selected folder',
    'WSP_ROOT'                  => 'Root',

    // Welcome editor
    'WSP_WELCOME_MSG' => "/*\n * MUNDO PHPBB WORKSPACE\n * =====================\n * \n * NO FILE OPEN.\n * \n * 1. Select a file from the sidebar.\n * 2. Edit the code.\n * 3. Use CTRL + S to save quickly.\n */\n",

    // =====================================================
    // General Status
    // =====================================================
    'WSP_LOADING'               => 'Loading...',
    'WSP_PROCESSING'            => 'Processing...',
    'WSP_SAVING'                => 'Saving...',
    'WSP_SAVED'                 => 'Changes saved!',
    'WSP_UPLOADING'             => 'Uploading files...',
    'WSP_COPIED'                => 'Copied!',
    'WSP_HISTORY_CLEANED'       => 'Project history reset.',
    'WSP_CACHE_CLEANED'         => 'phpBB cache cleared successfully.',

    'WSP_OK'                    => 'OK',
    'WSP_CANCEL'                => 'Cancel',
    'WSP_CLOSE'                 => 'Close',
    'WSP_RENAME'                => 'Rename',

    // =====================================================
    // Projects
    // =====================================================
    'WSP_NEW_PROJECT'           => 'New Project',
    'WSP_OPEN_PROJECT'          => 'Open Project',
    'WSP_DEFAULT_DESC'          => 'Created via Workspace IDE',
    'WSP_NO_PROJECTS'           => 'No projects found.',
    'WSP_EMPTY_PROJECT'         => 'Empty project',
    'WSP_EMPTY_PROJECT_DESC'    => 'This project does not have any files yet.',
    'WSP_DRAG_UPLOAD_HINT'      => 'Drag folders here or use the upload button.',
    'WSP_RENAME_PROJECT'        => 'Rename Project',
    'WSP_DOWNLOAD_PROJECT'      => 'Download ZIP',

    // =====================================================
    // Files and Folders
    // =====================================================
    'WSP_ADD_FILE'              => 'New file',
    'WSP_NEW_ROOT_FILE'         => 'New file in project root',
    'WSP_NEW_ROOT_FOLDER'       => 'Folder name in root',
    'WSP_NEW_ROOT_FOLDER_TITLE' => 'New Folder',
    'WSP_NEW_FILE_IN'           => 'New file in ',
    'WSP_NEW_FOLDER_IN'         => 'New subfolder in ',

    'WSP_UPLOAD_FILES'          => 'Upload Files',
    'WSP_DRAG_UPLOAD'           => 'Drag files or folders here to upload',

    // Context menu tree
    'WSP_CTX_NEW_FILE'          => 'New file here',
    'WSP_CTX_NEW_FOLDER'        => 'New subfolder here',
    'WSP_CTX_DELETE_FOLDER'     => 'Delete folder',

    // =====================================================
    // Rich Toolbar
    // =====================================================
    'WSP_SAVE_CHANGES'          => 'Save changes',
    'WSP_SEARCH_REPLACE'        => 'Search & Replace',
    'WSP_GENERATE_CHANGELOG'    => 'Generate changelog',
    'WSP_CLEAR_CHANGELOG'       => 'Clear changelog',
    'WSP_REFRESH_CACHE'         => 'Clear phpBB cache',
    'WSP_TOGGLE_FULLSCREEN'     => 'Full screen',

    // =====================================================
    // Diff
    // =====================================================
    'WSP_DIFF_TITLE'            => 'File Comparison',
    'WSP_DIFF_GENERATE'         => 'Generate comparison',
    'WSP_DIFF_GENERATING'       => 'Generating...',
    'WSP_DIFF_PREVIEW'          => 'Diff preview',
    'WSP_DIFF_SELECT_ORIG'      => 'Original file',
    'WSP_DIFF_SELECT_MOD'       => 'Modified file',
    'WSP_COPY_BBCODE'           => 'Copy BBCode',

    // =====================================================
    // Search and Replace
    // =====================================================
    'WSP_SEARCH_TERM'           => 'Search term',
    'WSP_REPLACE_TERM'          => 'Replace with',
    'WSP_REPLACE_ALL'           => 'Replace all',
    'WSP_REPLACE_SUCCESS'       => 'Success! %d change(s) made.',
    'WSP_SEARCH_NO_RESULTS'     => 'No files found.',
    'WSP_SEARCH_EMPTY_ERR'      => 'Please enter a search term.',
    'WSP_REPLACE_ONLY_FILE'     => 'Replace only in this file: ',
    'WSP_REPLACE_IN_PROJECT'    => 'Replace in entire project: ',

    // =====================================================
    // Prompts
    // =====================================================
    'WSP_PROMPT_NAME'           => 'Enter name:',
    'WSP_PROMPT_FILE_NAME'      => 'File name (e.g., includes/functions.php):',

    // =====================================================
    // Confirmations
    // =====================================================
    'WSP_CONFIRM_DELETE'        => 'Are you sure you want to permanently delete this project?',
    'WSP_CONFIRM_FILE_DELETE'   => 'Do you really want to delete this file?',
    'WSP_CONFIRM_DELETE_FOLDER' => "Delete folder '{path}' and all its files?",
    'WSP_CONFIRM_CLEAR_CHANGELOG'=> 'Do you want to clear the entire project history?',
    'WSP_CONFIRM_REPLACE_ALL'   => 'Do you want to replace in the entire project?',

    // =====================================================
    // System Messages
    // =====================================================
    'WSP_FILE_ELIMINATED'       => 'File removed from project.',
    'WSP_CHANGELOG_TITLE'       => 'Workspace - Automatic Changelog',
    'WSP_GENERATED_ON'          => 'Generated on',

    // =====================================================
    // Errors
    // =====================================================
    'WSP_ERR_PERMISSION'        => 'You do not have permission to access the Workspace.',
    'WSP_ERR_INVALID_ID'        => 'Invalid ID.',
    'WSP_ERR_INVALID_DATA'      => 'Invalid data sent.',
    'WSP_ERR_INVALID_NAME'      => 'Name cannot be empty.',
    'WSP_ERR_FILE_NOT_FOUND'    => 'File not found.',
    'WSP_ERR_FILE_EXISTS'       => 'A file with this name already exists.',
    'WSP_ERR_INVALID_FILES'     => 'Please select valid files.',
    'WSP_ERR_SAVE_FAILED'       => 'Error saving the file.',
    'WSP_ERR_SERVER_500'        => "Internal server error.\nCheck the Diff library.",
    'WSP_ERR_COMM'              => 'Communication error with the server.',
    'WSP_ERR_COPY'              => 'Error while copying.',
));