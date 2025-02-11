<?php
declare(strict_types=1);

use WP_Ajax_Response;

class PLL_Admin_Filters_Columns {
    public function __construct(
        protected PLL_Admin_Links $links,
        protected PLL_Model $model
    ) {
        // add the language and translations columns in 'All Posts', 'All Pages' and 'Media library' panels
        foreach ($this->model->get_translated_post_types() as $type) {
            // use the latest filter late as some plugins purely overwrite what's done by others :(
            // specific case for media
            add_filter('manage_' . ('attachment' == $type ? 'upload' : 'edit-' . $type) . '_columns', [$this, 'add_post_column'], 100);
            add_action('manage_' . ('attachment' == $type ? 'media' : $type . '_posts') . '_custom_column', [$this, 'post_column'], 10, 2);
        }

        // quick edit and bulk edit
        add_filter('quick_edit_custom_box', [$this, 'quick_edit_custom_box'], 10, 2);
        add_filter('bulk_edit_custom_box', [$this, 'quick_edit_custom_box'], 10, 2);

        // adds the language column in the 'Categories' and 'Post Tags' tables
        foreach ($this->model->get_translated_taxonomies() as $tax) {
            add_filter('manage_edit-' . $tax . '_columns', [$this, 'add_term_column']);
            add_filter('manage_' . $tax . '_custom_column', [$this, 'term_column'], 10, 3);
        }

        // ajax responses to update list table rows
        add_action('wp_ajax_pll_update_post_rows', [$this, 'ajax_update_post_rows']);
        add_action('wp_ajax_pll_update_term_rows', [$this, 'ajax_update_term_rows']);
    }

    protected function add_column(array $columns, string $before): array {
        if ($n = array_search($before, array_keys($columns))) {
            $end = array_slice($columns, $n);
            $columns = array_slice($columns, 0, $n);
        }

        foreach ($this->model->get_languages_list() as $language) {
            // don't add the column for the filtered language
            if (empty($this->filter_lang) || $language->slug != $this->filter_lang->slug) {
                $columns['language_' . $language->slug] = $language->flag ? $language->flag . '<span class="screen-reader-text">' . esc_html($language->name) . '</span>' : esc_html($language->slug);
            }
        }

        return isset($end) ? array_merge($columns, $end) : $columns;
    }

    protected function get_first_language_column(): string {
        foreach ($this->model->get_languages_list() as $language) {
            if (empty($this->filter_lang) || $language->slug != $this->filter_lang->slug) {
                $columns[] = 'language_' . $language->slug;
            }
        }

        return empty($columns) ? '' : reset($columns);
    }

    public function add_post_column(array $columns): array {
        return $this->add_column($columns, 'comments');
    }

    public function post_column(string $column, int $post_id): void {
        $inline = wp_doing_ajax() && isset($_REQUEST['action']) && 'inline-save' === $_REQUEST['action'];
        $inline_lang_choice = filter_input(INPUT_POST, 'inline_lang_choice', FILTER_SANITIZE_STRING);
        $lang = $inline ? $this->model->get_language($inline_lang_choice) : $this->model->post->get_language($post_id);

        if (false === strpos($column, 'language_') || !$lang) {
            return;
        }

        $language = $this->model->get_language(substr($column, 9));

        // hidden field containing the post language for quick edit
        if ($column == $this->get_first_language_column()) {
            printf('<div class="hidden" id="lang_%d">%s</div>', esc_attr($post_id), esc_html($lang->slug));
        }

        $post_type_object = get_post_type_object(get_post_type($post_id));

        // link to edit post (or a translation)
        if ($id = $this->model->post->get($post_id, $language)) {
            if ($link = get_edit_post_link($id)) {
                if ($id === $post_id) {
                    $class = 'pll_icon_tick';
                    $s = sprintf(__('Edit this item in %s', 'polylang'), $language->name);
                } else {
                    $class = esc_attr('pll_icon_edit translation_' . $id);
                    $s = sprintf(__('Edit the translation in %s', 'polylang'), $language->name);
                }
                printf(
                    '<a class="%1$s" title="%2$s" href="%3$s"><span class="screen-reader-text">%4$s</span></a>',
                    esc_attr($class),
                    esc_attr(get_post($id)->post_title),
                    esc_url($link),
                    esc_html($s)
                );
            } elseif ($id === $post_id) {
                printf(
                    '<span class="pll_icon_tick"><span class="screen-reader-text">%s</span></span>',
                    esc_html(sprintf(__('This item is in %s', 'polylang'), $language->name))
                );
            }
        } else {
            echo $this->links->new_post_translation_link($post_id, $language);
        }
    }

    public function quick_edit_custom_box(string $column, string $type): string {
        if ($column == $this->get_first_language_column()) {
            $elements = $this->model->get_languages_list();
            if (current_filter() == 'bulk_edit_custom_box') {
                array_unshift($elements, (object)['slug' => -1, 'name' => __('&mdash; No Change &mdash;')]);
            }

            $dropdown = new PLL_Walker_Dropdown();
            printf(
                '<fieldset class="inline-edit-col-left">
                    <div class="inline-edit-col">
                        <label class="alignleft">
                            <span class="title">%s</span>
                            %s
                        </label>
                    </div>
                </fieldset>',
                esc_html__('Language', 'polylang'),
                $dropdown->walk($elements, -1, ['name' => 'inline_lang_choice', 'id' => ''])
            );
        }
        return $column;
    }

    public function add_term_column(array $columns): array {
        return $this->add_column($columns, 'posts');
    }

    public function term_column(string $out, string $column, int $term_id): string {
        $inline = wp_doing_ajax() && isset($_REQUEST['action']) && 'inline-save-tax' === $_REQUEST['action'];
        $inline_lang_choice = filter_input(INPUT_POST, 'inline_lang_choice', FILTER_SANITIZE_STRING);
        if (false === strpos($column, 'language_') || !($lang = $inline ? $this->model->get_language($inline_lang_choice) : $this->model->term->get_language($term_id))) {
            return $out;
        }

        $post_type = $_REQUEST['post_type'] ?? $GLOBALS['post_type'];
        $taxonomy = $_REQUEST['taxonomy'] ?? $GLOBALS['taxonomy'];

        if (!post_type_exists($post_type) || !taxonomy_exists($taxonomy)) {
            return $out;
        }

        $language = $this->model->get_language(substr($column, 9));

        if ($column == $this->get_first_language_column()) {
            $out = sprintf('<div class="hidden" id="lang_%d">%s</div>', $term_id, esc_html($lang->slug));

            if (in_array(get_option('default_category'), $this->model->term->get_translations($term_id))) {
                $out .= sprintf('<div class="hidden" id="default_cat_%1$d">%1$d</div>', $term_id);
            }
        }

        if (($id = $this->model->term->get($term_id, $language)) && $term = get_term($id, $taxonomy)) {
            if ($link = get_edit_term_link($id, $taxonomy, $post_type)) {
                if ($id === $term_id) {
                    $class = 'pll_icon_tick';
                    $s = sprintf(__('Edit this item in %s', 'polylang'), $language->name);
                } else {
                    $class = esc_attr('pll_icon_edit translation_' . $id);
                    $s = sprintf(__('Edit the translation in %s', 'polylang'), $language->name);
                }
                $out .= sprintf(
                    '<a class="%1$s" title="%2$s" href="%3$s"><span class="screen-reader-text">%4$s</span></a>',
                    $class,
                    esc_attr($term->name),
                    esc_url($link),
                    esc_html($s)
                );
            } elseif ($id === $term_id) {
                $out .= sprintf(
                    '<span class="pll_icon_tick"><span class="screen-reader-text">%s</span></span>',
                    esc_html(sprintf(__('This item is in %s', 'polylang'), $language->name))
                );
            }
        } else {
            $out .= $this->links->new_term_translation_link($term_id, $taxonomy, $post_type, $language);
        }

        return $out;
    }

    public function ajax_update_post_rows(): void {
        global $wp_list_table;

        $post_type = filter_input(INPUT_POST, 'post_type', FILTER_SANITIZE_STRING);
        if (!post_type_exists($post_type) || !$this->model->is_translated_post_type($post_type)) {
            wp_die(0);
        }

        check_ajax_referer('inlineeditnonce', '_pll_nonce');

        $x = new WP_Ajax_Response();
        $wp_list_table = _get_list_table('WP_Posts_List_Table', ['screen' => $_POST['screen']]);

        $translations = !empty($_POST['translations']) ? explode(',', $_POST['translations']) : [];
        $translations = array_merge($translations, [$_POST['post_id']]);
        $translations = array_map('intval', $translations);

        foreach ($translations as $post_id) {
            $level = is_post_type_hierarchical($post_type) ? count(get_ancestors($post_id, $post_type)) : 0;
            if ($post = get_post($post_id)) {
                ob_start();
                $wp_list_table->single_row($post, $level);
                $data = ob_get_clean();
                $x->add(['what' => 'row', 'data' => $data, 'supplemental' => ['post_id' => $post_id]]);
            }
        }

        $x->send();
    }

    public function ajax_update_term_rows(): void {
        global $wp_list_table;

        $taxonomy = filter_input(INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING);
        if (!taxonomy_exists($taxonomy) || !$this->model->is_translated_taxonomy($taxonomy)) {
            wp_die(0);
        }

        check_ajax_referer('pll_language', '_pll_nonce');

        $x = new WP_Ajax_Response();
        $wp_list_table = _get_list_table('WP_Terms_List_Table', ['screen' => $_POST['screen']]);

        $translations = !empty($_POST['translations']) ? explode(',', $_POST['translations']) : [];
        $translations = array_merge($translations, $this->model->term->get_translations((int)$_POST['term_id']));
        $translations = array_unique($translations);
        $translations = array_map('intval', $translations);

        foreach ($translations as $term_id) {
            $level = is_taxonomy_hierarchical($taxonomy) ? count(get_ancestors($term_id, $taxonomy)) : 0;
            if ($tag = get_term($term_id, $taxonomy)) {
                ob_start();
                $wp_list_table->single_row($tag, $level);
                $data = ob_get_clean();
                $x->add(['what' => 'row', 'data' => $data, 'supplemental' => ['term_id' => $term_id]]);
            }
        }

        $x->send();
    }
}