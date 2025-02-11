<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class AitOltManager extends PLL_OLT_Manager
{
    private static ?self $instance = null;

    public static function run(): void
    {
        self::instance();
        add_action('plugins_loaded', [__CLASS__, 'loadAitPluginsTextdomains']);
        add_action('ait-after-framework-load', [__CLASS__, 'loadAitThemesTextdomains']);
        add_filter('woocommerce_shortcode_products_query', [__CLASS__, 'addShortcodeLanguageFilter'], 10, 2);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function loadAitPluginsTextdomains(): void
    {
        $plugins = wp_get_active_and_valid_plugins();
        $network_plugins = is_multisite() ? wp_get_active_network_plugins() : [];

        $all_active_plugins = array_merge($plugins, $network_plugins);

        $locale = (is_admin() && function_exists('get_user_locale')) ? get_user_locale() : get_locale();

        foreach ($all_active_plugins as $plugin) {
            $slug = dirname(plugin_basename($plugin));
            if ($slug === 'ait-languages') continue;
            if (strncmp($slug, "ait-", 4) === 0 || $slug === 'revslider') {
                load_plugin_textdomain($slug, false, "$slug/languages");
                load_textdomain($slug, POLYLANG_DIR . "/ait/languages/{$slug}/{$slug}-{$locale}.mo");
            }
        }
    }

    public static function addShortcodeLanguageFilter(array $query_args, array $atts): array
    {
        if (function_exists('pll_current_language')) {
            $query_args['lang'] = $query_args['lang'] ?? pll_current_language();
        }
        return $query_args;
    }

    public static function loadAitThemesTextdomains(): void
    {
        $currentTheme = get_stylesheet();
        $locale = (is_admin() && function_exists('get_user_locale')) ? get_user_locale() : get_locale();

        if (defined('PLL_ADMIN') && PLL_ADMIN) {
            $maybeFilteredLocale = apply_filters('theme_locale', $locale, 'ait-admin') ?: $locale;
            $themeAdminOverrideFile = aitPath('languages', "/admin-{$maybeFilteredLocale}.mo");
            if ($themeAdminOverrideFile) {
                load_textdomain('ait-admin', $themeAdminOverrideFile);
            }
            load_textdomain('ait-admin', WP_LANG_DIR . "/themes/{$currentTheme}-admin-{$locale}.mo");
            load_textdomain('ait-admin', POLYLANG_DIR . "/ait/languages/ait-theme/admin-{$maybeFilteredLocale}.mo");
        } else {
            $maybeFilteredLocale = apply_filters('theme_locale', $locale, 'ait') ?: $locale;
            $themeOverrideFile = aitPath('languages', "/{$maybeFilteredLocale}.mo");
            if ($themeOverrideFile) {
                load_textdomain('ait', $themeOverrideFile);
            }
            load_textdomain('ait', WP_LANG_DIR . "/themes/{$currentTheme}-{$locale}.mo");
            load_textdomain('ait', POLYLANG_DIR . "/ait/languages/ait-theme/{$maybeFilteredLocale}.mo");
        }
    }

    public function load_textdomain_mofile(string $mofile, string $domain): string
    {
        $this->list_textdomains[] = [
            'mo' => $mofile,
            'domain' => $domain
        ];
        return '';
    }
}