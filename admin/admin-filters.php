<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PLL_Admin_Filters extends PLL_Filters {
    public function __construct(&$polylang) {
        parent::__construct($polylang);
        
        // Filters posts, pages and media by language
        add_filter('parse_query', [$this, 'parse_query']);
        
        // Filters categories and post tags by language
        add_filter('terms_clauses', [$this, 'terms_clauses'], 10, 3);
        
        // Filters comments by language
        add_action('parse_comment_query', [$this, 'parse_comment_query']);
        
        // Filters get_pages() by language
        add_filter('get_pages', [$this, 'get_pages'], 10, 2);
    }

    public function parse_query($query) {
        if (!empty($query->query_vars['lang'])) {
            $lang = $this->model->get_language($query->query_vars['lang']);
            if ($lang) {
                $query->set('lang', $lang->slug);
            }
        }
        return $query;
    }

    public function terms_clauses($clauses, $taxonomies, $args) {
        if (!empty($args['lang'])) {
            $lang = $this->model->get_language($args['lang']);
            if ($lang) {
                $clauses['where'] .= " AND t.term_id IN (
                    SELECT object_id FROM {$this->model->term_language_table}
                    WHERE language_code = '" . esc_sql($lang->slug) . "'
                )";
            }
        }
        return $clauses;
    }

    public function parse_comment_query($query) {
        if (!empty($query->query_vars['lang'])) {
            $lang = $this->model->get_language($query->query_vars['lang']);
            if ($lang) {
                $query->query_vars['post_type'] = $this->model->get_translated_post_types();
                $query->query_vars['lang'] = $lang->slug;
            }
        }
    }

    public function get_pages($pages, $args) {
        if (!empty($args['lang'])) {
            $lang = $this->model->get_language($args['lang']);
            if ($lang) {
                $pages = array_filter($pages, function($page) use ($lang) {
                    return $this->model->post->get_language($page->ID) == $lang;
                });
            }
        }
        return $pages;
    }
}
