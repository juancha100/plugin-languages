<?php
/**
 * Plugin Name: AIT Languages
 * Plugin URI: http://www.ait-themes.club
 * Description: Language plugin for AIT Themes
 * Version: 2.0
 * Author: Guia33 SL
 * Author URI: https://guia33.com
 * License: GPLv2 or later
 * Text Domain: ait-languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

define('AIT_LANGUAGES_VERSION', '2.0');
define('AIT_LANGUAGES_DIR', plugin_dir_path(__FILE__));
define('AIT_LANGUAGES_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AitLanguages\\';
    $base_dir = AIT_LANGUAGES_DIR . 'includes/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function ait_languages_init(): void
{
    if (!class_exists('AitLanguages\\AitLanguagesPlugin')) {
        require_once AIT_LANGUAGES_DIR . 'includes/AitLanguagesPlugin.php';
    }
    
    $plugin = new AitLanguages\AitLanguagesPlugin();
    $plugin->init();
}

// Hook into WordPress
add_action('plugins_loaded', 'ait_languages_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('AIT Languages requires PHP 7.4 or higher.');
    }
    
    // Add any additional activation tasks here
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Add any deactivation tasks here
    flush_rewrite_rules();
});

// Uninstall hook
register_uninstall_hook(__FILE__, function() {
    // Clean up plugin data if needed
    delete_option('ait_languages_settings');
});

// Load text domain for translations
add_action('init', function() {
    load_plugin_textdomain('ait-languages', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="admin.php?page=ait-languages-settings">' . __('Settings', 'ait-languages') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});