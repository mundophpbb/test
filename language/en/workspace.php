<?php
/**
 * mundophpbb workspace extension [English]
 *
 * @package mundophpbb workspace
 * @copyright (c) 2026 mundophpbb
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
/**
 * DO NOT CHANGE
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
// Main Interface and Branding
'WSP_TITLE' => 'phpBB World Workspace',
'WSP_VERSION_TAG' => 'v2.9',
'WSP_EXPLORER' => 'Explorer',
'WSP_PROJECT_LABEL' => 'Project',
'WSP_SELECT_FILE' => 'Select a file to edit',
'WSP_LOADING' => 'Loading...',
'WSP_PROCESSING' => 'Processing...',
// Toolbar Menus (Synchronized with v2.9)
'WSP_MENU_FILE' => 'File',
'WSP_MENU_PROJECT' => 'Project',
'WSP_MENU_TOOLS' => 'Tools',
'WSP_MENU_VIEW' => 'View',
// Submenus: File
'WSP_NEW_PROJECT' => 'New Project',
'WSP_OPEN_PROJECT' => 'Open Project...',
'WSP_SAVE' => 'Save',
'WSP_EXPORT_GIST' => 'Export Gist (GitHub)',
// Submenus: Project
'WSP_NEW_FOLDER' => 'New Folder',
'WSP_NEW_FILE' => 'New File',
'WSP_GENERATE_SKELETON' => 'Skeleton Generator (PSR-4)',
'WSP_DOWNLOAD_ZIP' => 'Download Project (ZIP)',
'WSP_GENERATE_LOG' => 'Generate Changelog',
// Submenus: Tools
'WSP_DIFF_WIZARD' => 'Difference Wizard (Diff)',
'WSP_SEARCH_REPLACE' => 'Search and Replace',
'WSP_PURGE_CACHE' => 'Purge phpBB Cache',
'WSP_SHORTCUTS' => 'Keyboard Shortcuts',
// Submenus: View
'WSP_CHANGE_THEME' => 'Change Theme',
'WSP_ZEN_MODE' => 'Zen Mode (Full Focus)',
'WSP_TOGGLE_CONSOLE' => 'Toggle Output Console',
// Welcome Messages
'WSP_WELCOME_MSG' => "/*\n * PHPBB WORLD WORKSPACE v2.9\n * =========================\n * \n * ENVIRONMENT READY FOR DEVELOPMENT.\n * \n * 1. Select a project in the top Toolbar.\n * 2. Explore or drag files (Drag & Drop).\n * 3. View images or edit PHP/JS/CSS codes.\n */\n",
'WSP_WELCOME_MSG_SIDEBAR' => 'No project loaded.',
'WSP_NO_FILES' => 'Empty project.',
// Dynamic Dialogs (Used by JS)
'WSP_PROMPT_PROJECT_NAME' => 'Enter the name of the new project:',
'WSP_PROMPT_FILE_NAME' => 'File or folder name (use / at the end for folders):',
'WSP_PROMPT_RENAME' => 'Enter the new name:',
'WSP_PROMPT_DUPLICATE' => 'Duplicate as:',
'WSP_SKEL_VENDOR' => 'Vendor:',
'WSP_SKEL_NAME' => 'Extension Name:',
'WSP_RUN_GENERATOR' => 'Generate Structure',
// Status and Success
'WSP_SAVE_CHANGES' => 'SAVE CHANGES',
'WSP_SAVING' => 'SAVING...',
'WSP_SAVED' => 'SAVED!',
'WSP_OK' => 'OK',
'WSP_CANCEL' => 'Cancel',
'WSP_CLOSE' => 'Close',
'WSP_RENAME' => 'Rename',
'WSP_DUPLICATE' => 'Duplicate',
'WSP_COPIED' => 'Copied!',
// Errors and Alerts
'WSP_CONFIRM_DELETE' => 'Delete this project and all files permanently?',
'WSP_CONFIRM_FILE_DELETE' => 'Do you really want to delete this file?',
'WSP_CONFIRM_REPLACE_ALL' => 'Do you really want to replace in the ENTIRE project?',
'WSP_ERR_PERMISSION' => 'Error: You do not have Founder permission.',
'WSP_ERR_FILE_NOT_FOUND' => 'File not found.',
'WSP_ERR_FILE_EXISTS' => 'A file with this name already exists.',
'WSP_ERR_SERVER_500' => 'Server Error: ZIP or DIFF libraries missing.',
'WSP_ERR_CHANGELOG_EMPTY' => 'Not enough changes to generate log.',
// ACP Permissions
'ACL_U_WORKSPACE_ACCESS' => 'Can access the phpBB World Workspace IDE',
));