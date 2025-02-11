<?php

declare(strict_types=1);

class AitLanguages
{
    public static string $pageSlug;

    public static function before(): void
    {
        static::maybeAddDefaultOptions();
        static::maybeUpdateOptionsFor20();
    }

    public static function after(): void
    {
        AitOltManager::run();

        static::modifyLanguageList();
        static::adminBarLanguageSwitcher();
        static::adminMenu();
        static::enqueueAssets();
        static::afterDemoContentImport();
        static::clearThemeCache();
        static::migrateTo20();
        static::handleUserLang();
        static::removeSomeModules();
        static::changeAdminPageUlr();
        static::updateUserLocaleToFirstLang();
        static::adminNotices();
        static::wcAjaxEndpoint();
        static::langParamInRestTermQuery();
    }

    public static function maybeUpdateOptionsFor20(): void
    {
        $options = get_option('polylang', []);
        if (!empty($options['version']) && version_compare($options['version'], '1.4-dev', '<=')) {
            update_option('polylang_13x', $options); // backup old options just for case

            // Change some default settings of polylang, they are needed for WooPoly correct behaviour
            $options['force_lang'] = 1;

            // Add WooCommerce CPTs and Tax
            $options['post_types'] = array_unique(array_merge($options['post_types'] ?? [], ['product']));
            $options['taxonomies'] = array_unique(array_merge($options['taxonomies'] ?? [], ['product_cat', 'product_tag', 'product_shipping_class']));

            $options = apply_filters('ait-languages-options', $options);

            update_option('polylang', $options);
            update_option('_ait-languages_should_migrate', 'yes');
        }
    }

    protected static function modifyLanguageList(): void
    {
        add_filter('pll_predefined_languages', function($languages) {
            $supportedByAit = apply_filters('ait-supported-languages', [
                'bg_BG', 'cs_CZ', 'da_DK', 'de_DE', 'el', 'en_US', 'es_ES', 'fi', 'fr_FR',
                'hi_IN', 'hr', 'hu_HU', 'id_ID', 'it_IT', 'nl_NL', 'pl_PL', 'pt_BR', 'pt_PT',
                'ro_RO', 'ru_RU', 'sk_SK', 'sr_RS', 'sq', 'sv_SE', 'tr_TR', 'uk', 'vi',
                'zh_CN', 'zh_TW',
            ]);

            $aitLanguages = array_intersect_key($languages, array_flip($supportedByAit));

            $aitLanguages['zh_CN'][0] = 'cn';
            $aitLanguages['zh_TW'][0] = 'tw';
            $aitLanguages['pt_BR'][0] = 'br';

            return $aitLanguages;
        });
    }

    protected static function maybeAddDefaultOptions(): void
    {
        add_filter('pre_update_option_polylang', function($options, $oldOptions) {
            if (empty($oldOptions)) {
                // Add all translatable AIT CPTs and WooCommerce CPTs to options
                if (class_exists('AitToolkit')) {
                    $aitCpts = AitToolkit::getManager('cpts')->getTranslatable('list');
                    $options['post_types'] = array_unique(array_merge(
                        array_filter($options['post_types'], fn($cpt) => substr($cpt, 0, 4) !== 'ait-' || in_array($cpt, $aitCpts)),
                        $aitCpts
                    ));

                    $aitTaxs = [];
                    foreach (AitToolkit::getManager('cpts')->getAll() as $cpt) {
                        $aitTaxs = array_merge($aitTaxs, $cpt->getTranslatableTaxonomyList());
                    }
                    $options['taxonomies'] = array_unique(array_merge(
                        array_filter($options['taxonomies'], fn($tax) => substr($tax, 0, 4) !== 'ait-' || in_array($tax, $aitTaxs)),
                        $aitTaxs
                    ));
                }

                // Change some default settings of Polylang
                $options['browser'] = 0;
                $options['hide_default'] = 1;
                $options['force_lang'] = 1;
                $options['redirect_lang'] = 1;
                $options['rewrite'] = 1;
            } else {
                // on every save override these settings
                $options['hide_default'] = 1;
                $options['force_lang'] = 1;
                $options['redirect_lang'] = 1;
                $options['rewrite'] = 1;
            }

            return apply_filters('ait-languages-options', $options);
        }, 10, 2);
    }

    // ... (rest of the methods with similar updates)

    public static function updateUserLocale(?string $locale = null): void
    {
        $user_locale = isset($_POST['user_locale']) && in_array($_POST['user_locale'], PLL()->model->get_languages_list(['fields' => 'locale']), true)
            ? $_POST['user_locale']
            : 'en_US';
        if (!$user_locale && $locale) {
            $user_locale = $locale;
        }

        update_user_meta(get_current_user_id(), 'locale', $user_locale);
    }

    protected static function useBlockEditorPlugin(): bool
    {
        return class_exists('PLL_Block_Editor_Plugin') && apply_filters('pll_use_block_editor_plugin', !defined('PLL_USE_BLOCK_EDITOR_PLUGIN') || PLL_USE_BLOCK_EDITOR_PLUGIN);
    }
}
