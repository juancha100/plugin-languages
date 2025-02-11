<?php
/**
 * @package Polylang
 */

/**
 * Base class for both admin
 *
 * @since 1.8
 */
abstract class PLL_Admin_Filters_Post_Base extends PLL_Admin_Filters {
	/**
	 * @var PLL_Model
	 */
	protected $model;

	/**
	 * @var PLL_Admin_Links
	 */
	protected $links;

	/**
	 * Language selected in the admin language filter.
	 *
	 * @var PLL_Language|null
	 */
	protected $filter_lang;

	/**
	 * Constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param PLL_Admin_Base $polylang
	 */
	public function __construct( &$polylang ) {
		parent::__construct( $polylang );

		$this->links = &$polylang->links;
		$this->model = &$polylang->model;
		$this->filter_lang = &$polylang->filter_lang;

		// Filters posts, pages and media by language
		add_filter( 'parse_query', array( $this, 'parse_query' ) );

		// Adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

		// Ajax response for changing the language in the post metabox
		add_action( 'wp_ajax_post_lang_choice', array( $this, 'post_lang_choice' ) );
		add_action( 'wp_ajax_pll_posts_not_translated', array( $this, 'pll_posts_not_translated' ) );

		// Adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_action( 'save_post', array( $this, 'save_post' ), 21, 3 ); // Priority 21 to come after advanced custom fields (20) and before the event calendar which breaks everything after 25
		add_action( 'set_object_terms', array( $this, 'set_object_terms' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'delete_post' ) );

		// Filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'page_attributes_dropdown_pages_args' ), 10, 2 );
	}

	/**
	 * Filters posts, pages and media by language
	 *
	 * @since 0.1
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function parse_query( $query ) {
		$qvars = &$query->query_vars;

		// Do not filter post types such as nav_menu_item
		if ( isset( $qvars['post_type'] ) && ! $this->model->is_translated_post_type( $qvars['post_type'] ) ) {
			unset( $qvars['lang'] );
			return;
		}

		if ( isset( $qvars['post_type'] ) && ! isset( $qvars['lang'] ) && ! isset( $qvars['pll_ajax_backend'] ) ) {
			$qvars['lang'] = $this->filter_lang ? $this->filter_lang->slug : $this->model->get_languages_list( array( 'fields' => 'slug' ) );
		}
	}

	/**
	 * Adds the Language box in the 'Edit Post' and 'Edit Page' panels (as well as in custom post types panels)
	 *
	 * @since 0.1
	 *
	 * @param string $post_type Current post type
	 * @param WP_Post $post
	 * @return void
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( $this->model->is_translated_post_type( $post_type ) ) {
			add_meta_box(
				'ml_box',
				__( 'Languages', 'polylang' ),
				array( $this, 'post_language' ),
				$post_type,
				'side',
				'high',
				array(
					'__back_compat_meta_box' => pll_use_block_editor_plugin(),
				)
			);
		}
	}

	/**
	 * Displays the Languages metabox in the 'Edit Post' and 'Edit Page' panels
	 *
	 * @since 0.1
	 *
	 * @param WP_Post $post
	 * @return void
	 */
	public function post_language( $post ) {
		$lang = $this->model->post->get_language( $post->ID );
		$lang = $lang ? $lang : $this->pref_lang;

		$dropdown = new PLL_Walker_Dropdown();

		$dropdown_html = $dropdown->walk(
			$this->model->get_languages_list(),
			array(
				'name'     => $post->ID ? 'post_lang_choice' : 'lang_choice',
				'class'    => 'tags-input',
				'selected' => $lang ? $lang->slug : '',
				'flag'     => true,
			)
		);

		wp_nonce_field( 'pll_language', '_pll_nonce' );

		// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
		printf(
			'<p><strong>%1$s</strong></p>
			<label class="screen-reader-text" for="%2$s">%1$s</label>
			<div id="select-%3$s-language">%4$s</div>',
			esc_html__( 'Language', 'polylang' ),
			$post->ID ? 'post_lang_choice' : 'lang_choice',
			'post',
			$dropdown_html // phpcs:ignore WordPress.Security.EscapeOutput
		);

		/**
		 * Fires after the 'Languages' metabox has been displayed
		 *
		 * @since 1.7
		 */
		do_action( 'pll_' . $post->post_type . '_languages_metabox', $post->ID );
	}

	/**
	 * Ajax response for changing the language in the post metabox
	 *
	 * @since 0.2
	 *
	 * @return void
	 */
	public function post_lang_choice() {
		check_ajax_referer( 'pll_language', '_pll_nonce' );

		if ( ! isset( $_POST['lang'] ) ) {
			wp_die( 0 );
		}

		$lang = $this->model->get_language( sanitize_key( $_POST['lang'] ) );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';

		if ( ! post_type_exists( $post_type ) ) {
			wp_die( 0 );
		}

		$post_type_object = get_post_type_object( $post_type );

		if ( ! current_user_can( $post_type_object->cap->edit_posts ) || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) {
			wp_die( -1 );
		}

		$this->model->post->set_language( $post_id, $lang );

		ob_start();
		if ( $lang ) {
			include __DIR__ . '/view-translations-' . ( $post_id ? 'post' : 'term' ) . '.php';
		}
		$x = new WP_Ajax_Response( array( 'what' => 'translations', 'data' => ob_get_contents() ) );
		ob_end_clean();

		// Translations
		$translations = $this->model->post->get_translations( $post_id );

		$default_cat = $lang ? $this->model->term->get_translation( get_option( 'default_category' ), $lang ) : false;
		$data = compact( 'post_type', 'post_id', 'lang', 'translations', 'default_cat' );

		/**
		 * Fires after the language is changed in the post metabox
		 *
		 * @since 1.7
		 *
		 * @param array $data
		 */
		do_action( 'pll_post_lang_choice', $data );

		// Categories
		if ( isset( $_POST['taxonomies'] ) ) {
			$taxonomies = array_map( 'sanitize_key', $_POST['taxonomies'] );
			foreach ( $taxonomies as $taxname ) {
				$this->model->term->set_language( (int) $_POST['term_id'][ $taxname ], $lang );
			}
		}

		// Parent dropdown list
		$x->Add(
			array(
				'what' => 'parent',
				'data' => wp_dropdown_pages(
					array(
						'post_type'         => $post_type,
						'selected'          => $post_id ? wp_get_post_parent_id( $post_id ) : 0,
						'name'              => 'parent_id',
						'show_option_none'  => __( '(no parent)', 'polylang' ),
						'sort_column'       => 'menu_order, post_title',
						'echo'              => 0,
						'language'          => $lang ? $lang->slug : '',
						'child_of'          => $post_id,
						'exclude_tree'      => $post_id,
						'hierarchical'      => true,
					)
				),
			)
		);

		// Flag
		$x->Add( array( 'what' => 'flag', 'data' => empty( $lang->flag ) ? esc_html( $lang->slug ) : $lang->flag ) );

		// Sample permalink
		$x->Add( array( 'what' => 'permalink', 'data' => get_sample_permalink( $post_id ? $post_id : get_post() ) ) );

		$x->send();
	}

	/**
	 * Ajax response for input in translation autocomplete input box
	 *
	 * @since 1.5
	 *
	 * @return void
	 */
	public function pll_posts_not_translated() {
		check_ajax_referer( 'pll_language', '_pll_nonce' );

		if ( ! isset( $_GET['post_type'], $_GET['post_language'], $_GET['translation_language'], $_GET['term'], $_GET['pll_post_id'] ) ) {
			wp_die( 0 );
		}

		$post_type = sanitize_key( $_GET['post_type'] );
		if ( ! post_type_exists( $post_type ) ) {
			wp_die( 0 );
		}

		$post_language = $this->model->get_language( sanitize_key( $_GET['post_language'] ) );
		$translation_language = $this->model->get_language( sanitize_key( $_GET['translation_language'] ) );

		$term = wp_unslash( $_GET['term'] );
		$term = trim( $term );

		$post_id = (int) $_GET['pll_post_id'];

		$return = array();

		// Don't order by title: see https://wordpress.org/support/topic/find-translated-post-when-10-is-not-enough
		$posts = get_posts(
			array(
				's'                => $term,
				'suppress_filters' => 0, // To make the post_fields filter work
				'lang'             => $translation_language,
				'numberposts'      => 20,
				'post_status'      => 'any',
				'post_type'        => $post_type,
				'exclude'          => $post_id,
				'pll_ajax_backend' => true,
			)
		);

		// Format results
		foreach ( $posts as $post ) {
			if ( ! $this->model->post->get_translation( $post->ID, $post_language ) ) {
				$return[] = array(
					'id' => $post->ID,
					'value' => $post->post_title,
					'link' => $this->links->edit_post_translation_link( $post->ID ),
				);
			}
		}

		// Add current translation in list
		if ( $post_id && ( $post = get_post( $post_id ) ) ) {
			if ( $this->model->post->get_translation( $post->ID, $translation_language ) ) {
				array_unshift(
					$return,
					array(
						'id' => $post->ID,
						'value' => $post->post_title,
						'link' => $this->links->edit_post_translation_link( $post->ID ),
					)
				);
			}
		}

		wp_die( wp_json_encode( $return ) );
	}

	/**
	 * Saves language
	 * Checks the terms saved are in the right language
	 *
	 * @since 1.5
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update Whether it is an update or not
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {
		// Does nothing except on post types which are filterable
		if ( ! $this->model->is_translated_post_type( $post->post_type ) ) {
			return;
		}

		if ( $id = wp_is_post_revision( $post_id ) ) {
			$post_id = $id;
		}

		// Capability check
		// As 'wp_insert_post' can be called from outside WP admin
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		if ( isset( $_POST['post_lang_choice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			check_admin_referer( 'pll_language', '_pll_nonce' );
			$this->model->post->set_language( $post_id, $this->model->get_language( sanitize_key( $_POST['post_lang_choice'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		elseif ( ! $this->model->post->get_language( $post_id ) ) {
			// Sets the language from admin language filter, the default category or the default language
			$this->model->post->set_language( $post_id, $this->filter_lang ? $this->filter_lang : $this->pref_lang );
		}

		$lang = $this->model->post->get_language( $post_id );

		if ( ! empty( $_POST['post_tr_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			check_admin_referer( 'pll_language', '_pll_nonce' );

			// Save translations after checking the translated post is in the right language
			foreach ( array_map( 'absint', $_POST['post_tr_lang'] ) as $lang_slug => $tr_id ) { // phpcs:ignore WordPress.Security.NonceVerification
				$tr_lang = $this->model->post->get_language( $tr_id );
				if ( $tr_id === $post_id || ( $tr_lang && $tr_lang->slug === $lang_slug ) ) {
					$translations[ $lang_slug ] = $tr_id;
				}
			}

			if ( ! empty( $translations ) ) {
				$this->model->post->save_translations( $post_id, $translations );
			}
		}

		// Make sure we get save terms in the right language (especially tags with same name in different languages)
		if ( ! empty( $_POST['tax_input'] ) && is_array( $_POST['tax_input'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			foreach ( array_keys( $_POST['tax_input'] ) as $tax ) { // phpcs:ignore WordPress.Security.NonceVerification
				$terms = get_the_terms( $post_id, $tax );
				if ( is_array( $terms ) ) {
					$newterms = array();
					foreach ( $terms as $term ) {
						// Check if the term is in the correct language or if a translation exist ( mainly for default category )
						if ( $newterm = $this->model->term->get( $term->term_id, $lang ) ) {
							$newterms[] = (int) $newterm;
						}
					}

					wp_set_object_terms( $post_id, $newterms, $tax );
				}
			}
		}

		// Attempts to set a default language for 'post_format' contents to prevent errors
		// See https://wordpress.org/support/topic/php-errors-with-post-formats-after-upgrade-to-1-8-3
		if ( 'post_format' === get_option( 'permalink_structure' ) && empty( $_REQUEST['post_format'] ) && ! has_post_format( $post_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_set_post_terms( $post_id, array( 'post-format-standard' ), 'post_format' );
		}
	}

	/**
	 * Called when a category or post tag is created or edited
	 * Checks if the term is assigned to a post type translated by Polylang
	 *
	 * @since 1.5
	 *
	 * @param int    $term_id      Term id
	 * @param int    $tt_id        Term taxonomy id
	 * @param string $taxonomy     Taxonomy name
	 * @param bool   $update       Whether it is an update or not
	 * @return void
	 */
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		static $avoid_recursion = false;
		$post = get_post( $object_id );

		if ( ! $avoid_recursion && ! empty( $post ) && $this->model->is_translated_post_type( $post->post_type ) && is_array( $terms ) ) {
			$lang = $this->model->post->get_language( $post->ID );

			if ( ! empty( $lang ) ) {
				// Convert to term ids if we got tag names
				$terms = array_map( 'intval', $terms );
				$newterms = array();

				foreach ( $terms as $term ) {
					// Check if the term is in the correct language or if a translation exist ( mainly for default category )
					if ( $term_id = $this->model->term->get( $term, $lang ) ) {
						$newterms[] = (int) $term_id;
					}
				}

				// We may need to set a default language for post format when creating a post
				if ( 'post_format' === $taxonomy ) {
					$newterms = array_merge( $newterms, array_filter( $terms, 'is_numeric' ) );
				}

				if ( ! empty( $newterms ) && array_diff( $newterms, $terms ) ) {
					$avoid_recursion = true;
					wp_set_object_terms( $object_id, $newterms, $taxonomy );
				}

				$avoid_recursion = false;
			}
		}
	}

	/**
	 * Called when a post (or page) is saved, published or updated
	 * Does nothing except on post types which are filterable
	 * Sets the language but does not allow to modify it
	 *
	 * @since 1.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function delete_post( $post_id ) {
		if ( $this->model->is_translated_post_type( get_post_type( $post_id ) ) ) {
			$this->model->post->delete_translation( $post_id );
		}
	}

	/**
	 * Filters the pages by language in the parent dropdown list in the page attributes metabox
	 *
	 * @since 0.6
	 *
	 * @param array  $dropdown_args Arguments passed to wp_dropdown_pages
	 * @param object $post
	 * @return array Modified arguments
	 */
	public function page_attributes_dropdown_pages_args( $dropdown_args, $post ) {
		$dropdown_args['lang'] = isset( $_POST['lang'] ) ? $this->model->get_language( sanitize_key( $_POST['lang'] ) ) : $this->model->post->get_language( $post->ID ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $dropdown_args['lang'] ) {
			$dropdown_args['lang'] = $this->pref_lang;
		}

		return $dropdown_args;
	}
}