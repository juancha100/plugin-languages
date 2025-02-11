<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PLL_Admin extends PLL_Base {
    public $filters, $filters_columns, $filters_post, $filters_term, $nav_menu, $links, $static_pages, $filters_media, $settings_page, $filters_columns_base;

    public function __construct($links_model) {
        parent::__construct($links_model);

        add_action('wp_loaded', [$this, 'init']);

        // Adds the languages in admin bar
        add_action('admin_bar_menu', [$this, 'admin_bar_menu'], 100);
    }

    public function init() {
        $this->links = new PLL_Admin_Links($this);
        $this->static_pages = new PLL_Admin_Static_Pages($this);
        $this->filters_media = new PLL_Admin_Filters_Media($this);
        $this->filters_term = new PLL_Admin_Filters_Term($this);
        $this->filters_post = new PLL_Admin_Filters_Post($this);
        $this->filters = new PLL_Admin_Filters($this);
        $this->nav_menu = new PLL_Admin_Nav_Menu($this);
        $this->sync = new PLL_Admin_Sync($this);
        $this->filters_columns = new PLL_Admin_Filters_Columns($this);
        $this->filters_columns_base = new PLL_Admin_Filters_Columns_Base($this);
    }

    public function admin_bar_menu($wp_admin_bar) {
        if (!$this->model->get_languages_list()) {
            return;
        }

        $current_language = $this->model->get_language(get_user_meta(get_current_user_id(), 'pll_filter_content', true));
        $current_language = $current_language ? $current_language : $this->model->get_language($this->options['default_lang']);

        $wp_admin_bar->add_menu([
            'id'     => 'languages',
            'title'  => $current_language ? $current_language->flag . ' ' . $current_language->slug : __('Languages', 'polylang'),
            'href'   => admin_url('admin.php?page=mlang'),
        ]);

        foreach ($this->model->get_languages_list() as $language) {
            if ($language->slug != $current_language->slug) {
                $wp_admin_bar->add_menu([
                    'parent' => 'languages',
                    'id'     => 'language-' . $language->slug,
                    'title'  => $language->flag . ' ' . $language->name,
                    'href'   => esc_url(add_query_arg('lang', $language->slug, remove_query_arg('lang'))),
                ]);
            }
        }
    }
}