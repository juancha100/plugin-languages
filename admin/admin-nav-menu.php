<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PLL_Admin_Nav_Menu {
    public $links, $model, $options;

    public function __construct(&$polylang) {
        $this->links = &$polylang->links;
        $this->model = &$polylang->model;
        $this->options = &$polylang->options;

        // Adds the language switcher metabox in nav menus
        add_action('admin_init', [$this, 'add_nav_menu_metabox']);

        // Filters menus by language in nav menus panel
        add_filter('get_terms', [$this, 'get_terms'], 10, 2);
    }

    public function add_nav_menu_metabox() {
        add_meta_box('pll_lang_switch_box', __('Language switcher', 'polylang'), [$this, 'lang_switch'], 'nav-menus', 'side', 'high');
    }

    public function lang_switch() {
        // Security check
        wp_nonce_field('pll_lang_switch', '_pll_nonce');

        echo '<div id="pll-lang-switch">';
        echo '<p>' . __('Add a language switcher to this menu.', 'polylang') . '</p>';
        
        foreach ($this->model->get_languages_list() as $language) {
            printf(
                '<label><input type="checkbox" name="pll_lang_switch[]" value="%s" /> %s</label><br />',
                esc_attr($language->slug),
                esc_html($language->name)
            );
        }
        
        echo '</div>';
    }

    public function get_terms($terms, $taxonomies) {
        if (in_array('nav_menu', $taxonomies) && !empty($_GET['lang'])) {
            $lang = $this->model->get_language($_GET['lang']);
            if ($lang) {
                $terms = array_filter($terms, function($term) use ($lang) {
                    return !$this->model->term->get_language($term->term_id) || $this->model->term->get_language($term->term_id) == $lang;
                });
            }
        }
        return $terms;
    }
}