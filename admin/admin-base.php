<?php
declare(strict_types=1);

/**
 * Base class for both admin
 *
 * @since 1.8
 */
class PLL_Admin_Base extends PLL_Base {
    public ?PLL_Language $filter_lang = null;
    public ?PLL_Language $curlang = null;
    public ?PLL_Language $pref_lang = null;

    /**
     * Loads the polylang text domain
     * Setups actions needed on all admin pages
     *
     * @since 1.8
     *
     * @param PLL_Links_Model $links_model
     */
    public function __construct(PLL_Links_Model $links_model) {
        parent::__construct($links_model);

        // Plugin i18n, only needed for backend
        load_plugin_textdomain('polylang', false, dirname(POLYLANG_DIR) . '/languages');

        // Adds the link to the languages panel in the WordPress admin menu
        add_action('admin_menu', [$this, 'add_menus']);

        // Setup js scripts and css styles
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('admin_print_footer_scripts', [$this, 'admin_print_footer_scripts']);
    }

    /**
     * Setups filters and action needed on all admin pages and on plugins page
     * Loads the settings pages or the filters base on the request
     *
     * @since 1.2
     */
    public function init(): void {
        if (empty($this->model->get_languages_list())) {
            return;
        }

        $this->links = new PLL_Admin_Links($this);
        $this->static_pages = new PLL_Admin_Static_Pages($this);
        $this->filters_links = new PLL_Filters_Links($this);

        // Filter admin language for users
        add_filter('setup_theme', [$this, 'init_user']);
        add_filter('request', [$this, 'request']);

        // Adds the languages in admin bar
        add_action('admin_bar_menu', [$this, 'admin_bar_menu'], 100);
    }

    /**
     * Adds the link to the languages panel in the WordPress admin menu
     *
     * @since 0.1
     */
    public function add_menus(): void {
        add_submenu_page(
            'options-general.php',
            $title = __('Languages', 'polylang'),
            $title,
            'manage_options',
            'mlang',
            '__return_null'
        );
    }

    /**
     * Setup js scripts & css styles (only on the relevant pages)
     *
     * @since 0.6
     */
    public function admin_enqueue_scripts(): void {
        $screen = get_current_screen();
        if (empty($screen)) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        // For each script:
        // 0 => the pages on which to load the script
        // 1 => the scripts it needs to work
        // 2 => 1 if loaded even if languages have not been defined yet, 0 otherwise
        // 3 => 1 if loaded in footer
        $scripts = [
            'post'  => [['post', 'media', 'async-upload', 'edit'], ['jquery', 'wp-ajax-response', 'post', 'jquery-ui-autocomplete'], 0, 1],
            'media' => [['upload'], ['jquery'], 0, 1],
            'term'  => [['edit-tags', 'term'], ['jquery', 'wp-ajax-response', 'jquery-ui-autocomplete'], 0, 1],
            'user'  => [['profile', 'user-edit'], ['jquery'], 0, 0],
        ];

        foreach ($scripts as $script => $v) {
            if (in_array($screen->base, $v[0], true) && ($v[2] || $this->model->get_languages_list())) {
                wp_enqueue_script("pll_$script", POLYLANG_URL . "/js/$script$suffix.js", $v[1], POLYLANG_VERSION, (bool)$v[3]);
            }
        }

        wp_enqueue_style('polylang_admin', POLYLANG_URL . "/css/admin$suffix.css", [], POLYLANG_VERSION);
    }

    /**
     * Sets pll_ajax_backend on all backend ajax request
     */
    public function admin_print_footer_scripts(): void {
        global $post_ID;

        $params = ['pll_ajax_backend' => 1];
        if (!empty($post_ID)) {
            $params['pll_post_id'] = (int)$post_ID;
        }

        $str = http_build_query($params);
        $arr = wp_json_encode($params);
        ?>
        <script type="text/javascript">
            if (typeof jQuery != 'undefined') {
                (function($){
                    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                        if (options.url.indexOf(ajaxurl) !== -1) {
                            if (typeof options.data === 'undefined') {
                                options.data = (options.type.toLowerCase() === 'get') ? '<?php echo $str; ?>' : <?php echo $arr; ?>;
                            } else {
                                if (typeof options.data === 'string') {
                                    if (options.data === '' && options.type.toLowerCase() === 'get') {
                                        options.url = options.url+'&<?php echo $str; ?>';
                                    } else {
                                        try {
                                            var o = $.parseJSON(options.data);
                                            o = $.extend(o, <?php echo $arr; ?>);
                                            options.data = JSON.stringify(o);
                                        }
                                        catch(e) {
                                            options.data = '<?php echo $str; ?>&'+options.data;
                                        }
                                    }
                                } else {
                                    options.data = $.extend(options.data, <?php echo $arr; ?>);
                                }
                            }
                        }
                    });
                })(jQuery)
            }
        </script>
        <?php
    }

    /**
     * Sets the admin current language, used to filter the content
     *
     * @since 2.0
     */
    public function set_current_language(): void {
        $this->curlang = $this->filter_lang;

        // Edit Post
        if (isset($_REQUEST['pll_post_id']) && $lang = $this->model->post->get_language((int)$_REQUEST['pll_post_id'])) {
            $this->curlang = $lang;
        } elseif ($GLOBALS['pagenow'] === 'post.php' && isset($_GET['post']) && is_numeric($_GET['post']) && $lang = $this->model->post->get_language((int)$_GET['post'])) {
            $this->curlang = $lang;
        } elseif ($GLOBALS['pagenow'] === 'post-new.php' && (empty($_GET['post_type']) || $this->model->is_translated_post_type($_GET['post_type']))) {
            $this->curlang = empty($_GET['new_lang']) ? $this->pref_lang : $this->model->get_language($_GET['new_lang']);
        }

        // Edit Term
        elseif (in_array($GLOBALS['pagenow'], ['edit-tags.php', 'term.php'], true) && isset($_GET['tag_ID']) && $lang = $this->model->term->get_language((int)$_GET['tag_ID'])) {
            $this->curlang = $lang;
        } elseif ($GLOBALS['pagenow'] === 'edit-tags.php' && isset($_GET['taxonomy']) && $this->model->is_translated_taxonomy($_GET['taxonomy'])) {
            if (!empty($_GET['new_lang'])) {
                $this->curlang = $this->model->get_language($_GET['new_lang']);
            } elseif (empty($this->curlang)) {
                $this->curlang = $this->pref_lang;
            }
        }

        // Ajax
        if (wp_doing_ajax() && !empty($_REQUEST['lang'])) {
            $this->curlang = $this->model->get_language($_REQUEST['lang']);
        }
    }

    /**
     * Defines the backend language and the admin language filter based on user preferences
     *
     * @since 1.2.3
     */
    public function init_user(): void {
        // Language for admin language filter: may be empty
        if (!wp_doing_ajax() && !empty($_GET['lang']) && !is_numeric($_GET['lang']) && current_user_can('edit_user', $user_id = get_current_user_id())) {
            update_user_meta($user_id, 'pll_filter_content', ($lang = $this->model->get_language($_GET['lang'])) ? $lang->slug : '');
        }

        $this->filter_lang = $this->model->get_language(get_user_meta(get_current_user_id(), 'pll_filter_content', true));

        // Set preferred language for use when saving posts and terms: must not be empty
        $this->pref_lang = empty($this->filter_lang) ? $this->model->get_language($this->options['default_lang']) : $this->filter_lang;

        /**
         * Filter the preferred language on admin side
         * The preferred language is used for example to determine the language of a new post
         *
         * @since 1.2.3
         *
         * @param PLL_Language $pref_lang preferred language
         */
        $this->pref_lang = apply_filters('pll_admin_preferred_language', $this->pref_lang);

        $this->set_current_language();

        // Inform that the admin language has been set
        if ($curlang = $this->model->get_language(get_locale())) {
            /** This action is documented in frontend/choose-lang.php */
            do_action('pll_language_defined', $curlang->slug, $curlang);
        } else {
            /** This action is documented in include/class-polylang.php */
            do_action('pll_no_language_defined'); // to load overridden textdomains
        }
    }

    /**
     * Avoids parsing a tax query when all languages are requested
     *
     * @since 1.6.5
     *
     * @param array $qvars
     * @return array
     */
    public function request(array $qvars): array {
        if (isset($qvars['lang']) && $qvars['lang'] === 'all') {
            unset($qvars['lang']);
        }

        return $qvars;
    }

    /**
     * Adds the languages list in admin bar for the admin languages filter
     *
     * @since 0.9
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function admin_bar_menu(WP_Admin_Bar $wp_admin_bar): void {
        $url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $all_item = (object)[
            'slug' => 'all',
            'name' => __('Show all languages', 'polylang'),
            'flag' => '<span class="ab-icon"></span>',
        ];

        $selected = empty($this->filter_lang) ? $all_item : $this->filter_lang;

        $title = sprintf(
            '<span class="ab-label"%s>%s</span>',
            $selected->slug === 'all' ? '' : sprintf(' lang="%s"', esc_attr($selected->get_locale('display'))),
            esc_html($selected->name)
        );

        $wp_admin_bar->add_menu([
            'id'     => 'languages',
            'title'  => $selected->flag . $title,
            'meta'   => ['title' => __('Filters content by language', 'polylang')],
        ]);

        foreach (array_merge([$all_item], $this->model->get_languages_list()) as $lang) {
            if ($selected->slug === $lang->slug) {
                continue;
            }

            $wp_admin_bar->add_menu([
                'parent' => 'languages',
                'id'     => $lang->slug,
                'title'  => $lang->flag . esc_html($lang->name),
                'href'   => esc_url(add_query_arg('lang', $lang->slug, remove_query_arg('paged', $url))),
                'meta'   => $lang->slug === 'all' ? [] : ['lang' => esc_attr($lang->get_locale('display'))],
            ]);
        }
    }
}