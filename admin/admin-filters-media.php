<?php
declare(strict_types=1);

class PLL_Admin_Filters_Media extends PLL_Admin_Filters_Post_Base {
    public function __construct(PLL_Admin_Links $links, PLL_Model $model) {
        parent::__construct($links, $model);
        $this->post_type = 'attachment';
    }

    public function filter_upload_dir(array $uploads): array {
        $lang = $this->model->get_language(
            filter_input(INPUT_POST, 'lang', FILTER_SANITIZE_STRING) ?: 
            $this->get_preferred_language()->slug
        );

        if ($lang) {
            $uploads['subdir'] = '/' . $lang->slug . $uploads['subdir'];
            $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
            $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
        }
        return $uploads;
    }

    public function wp_read_image_metadata(array $metadata, string $file, string $mime_type): array {
        if (empty($metadata['title']) && !empty($_POST['lang'])) {
            $lang = $this->model->get_language(filter_input(INPUT_POST, 'lang', FILTER_SANITIZE_STRING));
            if ($lang) {
                $metadata['title'] = wp_basename($file, ".$lang->slug");
            }
        }
        return $metadata;
    }

    public function ajax_query_attachments_args(array $query): array {
        if (!empty($query['lang'])) {
            $query['lang'] = $query['lang'] === 'all' ? '' : $query['lang'];
        }
        return $query;
    }

    protected function get_preferred_language(): PLL_Language {
        $pref_lang = $this->model->get_language(
            get_user_meta(get_current_user_id(), 'pll_filter_content', true)
        );
        return $pref_lang ?: $this->model->get_language($this->options['default_lang']);
    }
}