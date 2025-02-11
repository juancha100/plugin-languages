<?php
/**
 * @package Polylang
 */

/**
 * Manages filters and actions related to posts on admin side
 *
 * @since 1.2
 */
class PLL_Admin_Filters_Post extends PLL_Admin_Filters_Post_Base {
	/**
	 * @var PLL_Admin_Links
	 */
	public $links;

	/**
	 * Constructor: setups filters and actions
	 *
	 * @since 1.2
	 *
	 * @param object $polylang
	 */
	public function __construct( &$polylang ) {
		parent::__construct( $polylang );
		$this->links = &$polylang->links;

		// Filters posts, pages and media by language
		add_filter( 'parse_query', array( $this, 'parse_query' ) );

		// Adds the Languages box in the 'Edit Post' and 'Edit Page' panels
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

		// Ajax response for changing the language in the post metabox
		add_action( 'wp_ajax_post_lang_choice', array( $this, 'post_lang_choice' ) );
		add_action( 'wp_ajax_pll_posts_not_translated', array( $this, 'pll_posts_not_translated' ) );

		// Adds actions and filters related to languages when creating, saving or deleting posts and pages
		add_action( 'save_post', array( $this, 'save_post' ), 21, 3 ); // Priority 21 to come after advanced custom fields (20) and before the event calendar which breaks everything after 25
		add_action( 'set_object_terms', array( $this, 'set_object_terms' ), 10, 6 );
		add_action( 'before_delete_post', array( $this, 'delete_post' ) );

		// Filters the pages by language in the parent dropdown list in the page attributes metabox
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'page_attributes_dropdown_pages_args' ), 10, 2 );

		// Adds actions related to the language column in the 'All Posts' or 'All Pages' panels
		add_filter( 'manage_posts_columns', array( $this, 'add_post_column' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'add_post_column' ), 10, 2 );
		add_action( 'manage_posts_custom_column', array( $this, 'post_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'post_column' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );

		// Filters the pages by language in the parent dropdown list in quick edit
		add_filter( 'quick_edit_dropdown_pages_args', array( $this, 'quick_edit_dropdown_pages_args' ) );

		// Adds the language column (before the posts_custom_column filter) in the 'All Posts' or 'All Pages' panels
		add_filter( 'manage_posts_columns', array( $this, 'add_column' ), 1 );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ), 1 );

		// Adds the language filter and the translations input in the 'upload.php' panel
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Ajax response for changing the post's language in the languages metabox
		add_action( 'wp_ajax_post_translation_choice', array( $this, 'post_translation_choice' ) );

		// Adds actions and filters related to languages when creating, saving or deleting media
		add_action( 'add_attachment', array( $this, 'set_default_language' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'attachment_fields_to_save' ), 10, 2 );

		// Creates a new translation
		if ( isset( $_GET['from_post'], $_GET['new_lang'] ) && $this->model->is_translated_post_type( $this->get_post_type() ) ) {
			add_action( 'admin_init', array( $this, 'new_post_translation' ) );
		}
	}

	/**
	 * Filters posts, pages and media by language
	 *
	 * @since 0.1
	 *
	 * @param object $query WP_Query object
	 * @return object modified $query
	 */
	public function parse_query( $query ) {
		$qvars = &$query->query_vars;

		// Do not filter post types such as nav_menu_item
		if ( isset( $qvars['post_type'] ) && ! $this->model->is_translated_post_type( $qvars['post_type'] ) ) {
			unset( $qvars['lang'] );
			return $query;
		}

		if ( isset( $qvars['post_type'] ) && $qvars['post_type'] == 'any' ) {
			$post_types = $this->model->get_translated_post_types();
			$qvars['post_type'] = array_merge( $post_types, get_post_types( array( 'exclude_from_search' => true ) ) );
		}

		// Filter by language
		if ( ! isset( $qvars['lang'] ) && ! empty( $this->curlang ) ) {
			$qvars['lang'] = $this->curlang->slug;
		}

		return $query;
	}

	/**
	 * Adds the Language box in the 'Edit Post' and 'Edit Page' panels (as well as in custom post types panels)
	 *
	 * @since 0.1
	 *
	 * @param string $post_type Current post type
	 * @param object $post      Current post
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
	 */
	public function post_language() {
		global $post_ID;
		$post_id = $post_ID;
		$post_type = get_post_type( $post_ID );

		$lang = ( $lg = $this->model->post->get_language( $post_ID ) ) ? $lg :
			( isset( $_GET['new_lang'] ) ? $this->model->get_language( $_GET['new_lang'] ) :
			$this->pref_lang );

		$dropdown = new PLL_Walker_Dropdown();

		$id = ( 'attachment' === $post_type ) ? sprintf( 'attachments[%d][language]', $post_ID ) : 'post_lang_choice';

		$dropdown_html = $dropdown->walk(
			$this->model->get_languages_list(),
			array(
				'name'     => $id,
				'class'    => 'post_lang_choice tags-input',
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
			esc_attr( $id ),
			( 'attachment' === $post_type ? 'media' : 'post' ),
			$dropdown_html // phpcs:ignore WordPress.Security.EscapeOutput
		);

		/**
		 * Fires after the 'Language' metabox has been displayed
		 *
		 * @since 1.7
		 */
		do_action( 'pll_' . $post_type . '_language' );

		if ( 'attachment' !== $post_type ) {
			echo '<p>';
			if ( ( isset( $this->options['media_support'] ) || 'attachment' !== $post_type ) && $lang && isset( $post_ID ) ) {
				include PLL_ADMIN_INC . '/view-translations-media.php';
			}
			echo '</p>';
		}
	}

	/**
	 * Ajax response for changing the language in the post metabox
	 *
	 * @since 0.2
	 */
	public function post_lang_choice() {
		check_ajax_referer( 'pll_language', '_pll_nonce' );

		if ( ! isset( $_POST['lang'] ) ) {
			wp_die( 0 );
		}

		$lang = $this->model->get_language( $_POST['lang'] );
		$post_type = isset( $_POST['post_type'] ) ? $_POST['post_type'] : 'post';

		ob_start();
		if ( ! empty( $lang ) ) {
			include PLL_ADMIN_INC . '/view-translations-' . ( 'attachment' == $post_type ? 'media' : 'post' ) . '.php';
		}
		$x = new WP_Ajax_Response( array( 'what' => 'translations', 'data' => ob_get_contents() ) );
		ob_end_clean();

		// Flag
		$x->Add( array( 'what' => 'flag', 'data' => empty( $lang ) ? '' : $lang->flag ) );

		// Sample permalink
		$x->Add( array( 'what' => 'permalink', 'data' => empty( $_POST['post_id'] ) ? '' : get_sample_permalink_html( (int) $_POST['post_id'], $_POST['post_title'], $lang ? $lang->slug : '' ) ) );

		$x->send();
	}

	/**
	 * Ajax response for input in translation autocomplete input box
	 *
	 * @since 1.5
	 */
	public function pll_posts_not_translated() {
		check_ajax_referer( 'pll_language', '_pll_nonce' );

		if ( ! isset( $_GET['post_language'], $_GET['translation_language'], $_GET['post_type'], $_GET['term'], $_GET['pll_post_id'] ) ) {
			wp_die( 0 );
		}

		$args = array(
			's'                => wp_unslash( $_GET['term'] ),
			'post_type'        => $_GET['post_type'],
			'post_status'      => 'any',
			'suppress_filters' => 0, // To make the post_fields filter work
			'lang'             => $_GET['translation_language'],
			'numberposts'      => 20,
		);

		$args = apply_filters(
			'pll_ajax_posts_not_translated_args',
			$args,
			array(
				'post_language'        => $_GET['post_language'],
				'translation_language' => $_GET['translation_language'],
			)
		);

		// Don't order by title: see https://wordpress.org/support/topic/find-translated-post-when-10-is-not-enough
		add_filter( 'posts_fields', array( $this, 'posts_fields' ) );

		$posts = get_posts( $args );
		$pll_post_id = (int) $_GET['pll_post_id'];

		$return = array();

		foreach ( $posts as $post ) {
			if ( $post->ID !== $pll_post_id ) {
				$return[] = array(
					'id'    => $post->ID,
					'value' => $post->post_title,
					'link'  => $this->links->edit_post_translation_link( $post->ID ),
				);
			}
		}

		// Add current translation in list
		if ( $pll_post_id && ( $post = get_post( $pll_post_id ) ) && $post->post_title === $_GET['term'] ) {
			array_unshift(
				$return,
				array(
					'id'    => $post->ID,
					'value' => $post->post_title,
					'link'  => $this->links->edit_post_translation_link( $post->ID ),
				)
			);
		}

		wp_die( wp_json_encode( $return ) );
	}

	/**
	 * Ajax response for changing a translation in the languages metabox
	 *
	 * @since 1.5
	 */
	public function post_translation_choice() {
		check_ajax_referer( 'pll_language', '_pll_nonce' );

		if ( ! isset( $_POST['translation_language'], $_POST['post_id'], $_POST['value'] ) ) {
			wp_die( 0 );
		}

		$new_post_id = (int) $_POST['value'];
		$new_post = get_post( $new_post_id );

		if ( ! $new_post ) {
			wp_die( 0 );
		}

		$post_id = (int) $_POST['post_id'];
		$lang = $this->model->get_language( $_POST['translation_language'] );

		$this->model->post->set_language( $new_post_id, $lang );

		$translations = $this->model->post->get_translations( $post_id );

		if ( ! $translations && $post_id ) {
			// It's a new translation
			$translations = $this->model->post->get_translations( $post_id );
			$translations[ $lang->slug ] = $new_post_id;
			$this->model->post->save_translations( $post_id, $translations );
		}

		$d = array(
			'post_id'   => $new_post_id,
			'lang'      => $lang->slug,
			'link'      => $this->links->edit_post_translation_link( $new_post_id ),
			'edit_link' => get_edit_post_link( $new_post_id, 'html' ),
			'title'     => $new_post->post_title,
		);

		wp_die( wp_json_encode( $d ) );
	}

	/**
	 * Saves language
	 * Checks the terms saved are in the right language
	 *
	 * @since 1.5
	 *
	 * @param int    $post_id
	 * @param object $post
	 * @param bool   $update  Whether it is an update or not
	 */
	public function save_post( $post_id, $post, $update ) {
		// Does nothing except on post types which are filterable
		if ( ! $this->model->is_translated_post_type( $post->post_type ) ) {
			return;
		}

		if ( $id = wp_is_post_revision( $post_id ) ) {
			$post_id = $id;
		}

		$lang = $this->model->post->get_language( $post_id );

		// Make sure we get save the language
		// $lang not always existing on load, for example when saving a draft, a new post...
		if ( ! $lang ) {
			$lang = empty( $_POST['post_lang_choice'] ) ? $this->pref_lang : $this->model->get_language( $_POST['post_lang_choice'] );
			$this->model->post->set_language( $post_id, $lang );
		}

		// Make sure we have translations (again)
		// Test $update to avoid conflict with the multisite language switcher
		if ( $update && isset( $_POST['post_tr_lang'] ) && is_array( $_POST['post_tr_lang'] ) ) {
			$translations = array();

			foreach ( $_POST['post_tr_lang'] as $key => $tr_id ) {
				if ( $tr_id ) {
					$tr_lang = $this->model->get_language( $key );
					$translations[ $tr_lang->slug ] = (int) $tr_id;
				}
			}

			$this->model->post->save_translations( $post_id, $translations );
		}

		// Checks the terms are in the right language (especially tags with same name in different languages)
		if ( ! empty( $_POST['tax_input'] ) && is_array( $_POST['tax_input'] ) ) {
			foreach ( array_keys( $_POST['tax_input'] ) as $tax ) {
				$terms = get_the_terms( $post_id, $tax );

				if ( is_array( $terms ) ) {
					$newterms = array();
					foreach ( $terms as $term ) {
						if ( $this->model->term->get_language( $term->term_id ) == $lang ) {
							$newterms[] = (int) $term->term_id;
						}
					}

					wp_set_object_terms( $post_id, $newterms, $tax );
				}
			}
		}

		// Attempts to set a default language for 'post_format' contents when it has no language yet
		if ( 'post_format' === $post->post_type && ! $this->model->post->get_language( $post->ID ) ) {
			$this->model->post->set_language( $post->ID, $lang );
		}
	}

	/**
	 * Called when a post (or page) is saved, published or updated
	 * Does nothing except on post types which are filterable
	 * Sets the language but does not allow to modify it
	 *
	 * @since 1.1
	 *
	 * @param int    $post_id
	 * @param string $taxonomy
	 */
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $this->model->is_translated_taxonomy( $taxonomy ) && ! $append ) {
			$post = get_post( $object_id );
			if ( $this->model->is_translated_post_type( $post->post_type ) && ! empty( $old_tt_ids ) && $old_tt_ids != $tt_ids ) {
				$lang = $this->model->post->get_language( $object_id );
				if ( ! empty( $lang ) ) {
					$newterms = array();

					// Old terms
					$term_ids = array_map( 'intval', wp_list_pluck( get_the_terms( $object_id, $taxonomy ), 'term_id' ) );

					// New terms: keep only terms with the correct language
					foreach ( $terms as $term ) {
						$term_id = (int) ( is_object( $term ) ? $term->term_id : $term );
						if ( $this->model->term->get_language( $term_id ) == $lang ) {
							$newterms[] = $term_id;
						} elseif ( in_array( $term_id, $term_ids ) ) {
							$newterms[] = $term_id;
						}
					}

					wp_set_object_terms( $object_id, $newterms, $taxonomy );
				}
			}
		}
	}

	/**
	 * Called when a post, page or media is deleted
	 * Don't delete translations if this is a post revision thanks to AndyDeGroo who catched this bug
	 * http://wordpress.org/support/topic/plugin-polylang-quick-edit-still-breaks-translation-linking-of-pages-in-072
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function delete_post( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) ) {
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
		$dropdown_args['lang'] = isset( $_POST['lang'] ) ? $this->model->get_language( $_POST['lang'] ) : $this->model->post->get_language( $post->ID ); // ajax or not ?
		if ( ! $dropdown_args['lang'] ) {
			$dropdown_args['lang'] = $this->pref_lang;
		}

		return $dropdown_args;
	}

	/**
	 * Adds the Language box in the 'Edit Post' and 'Edit Page' panels
	 *
	 * @since 0.1
	 *
	 * @param string $column  Column name
	 * @param string $type    Post type
	 * @return string
	 */
	public function add_post_column( $columns, $post_type ) {
		if ( $this->model->is_translated_post_type( $post_type ) ) {
			// Don't add the column when not on the 'All posts' page
			if ( array_key_exists( 'name', $columns ) && 'post' === $post_type ) {
				return $columns;
			}

			$n = array_search( 'date', array_keys( $columns ) );
			if ( $n ) {
				$end = array_slice( $columns, $n );
				$columns = array_slice( $columns, 0, $n );
			}
			$columns['language'] = __( 'Language', 'polylang' );
			return isset( $end ) ? array_merge( $columns, $end ) : $columns;
		}
		return $columns;
	}

	/**
	 * Fills the language column in the posts list table
	 *
	 * @since 0.1
	 *
	 * @param string $column  Column name
	 * @param int    $post_id
	 */
	public function post_column( $column, $post_id ) {
		if ( 'language' === $column ) {
			$post_type = get_post_type( $post_id );
			if ( $this->model->is_translated_post_type( $post_type ) ) {
				$lang = $this->model->post->get_language( $post_id );
				if ( $lang ) {
					echo $lang->flag ? $lang->flag : esc_html( $lang->slug ); // phpcs:ignore WordPress.Security.EscapeOutput
					$translations = $this->model->post->get_translations( $post_id );
					unset( $translations[ $lang->slug ] );
					foreach ( $translations as $translation ) {
						$language = $this->model->post->get_language( $translation );
						if ( $language ) {
							echo '<br />' . ( $language->flag ? $language->flag : esc_html( $language->slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
					}
				} else {
					echo '<span class="pll_icon_tick"></span>';
				}
			}
		}
	}

	/**
	 * Adds the Language filter and handle the delete translation action in the posts list table
	 *
	 * @since 0.1
	 *
	 * @param string $post_type
	 * @param string $which
	 */
	public function restrict_manage_posts( $post_type, $which ) {
		if ( $this->model->is_translated_post_type( $post_type ) && 'top' === $which ) {
			$dropdown = new PLL_Walker_Dropdown();

			$dropdown->output(
				array(
					'name'     => 'lang',
					'class'    => 'lang_dropdown',
					'selected' => isset( $_GET['lang'] ) ? $_GET['lang'] : '',
					'flag'     => true,
				)
			);

			// Adds the translation filter
			$dropdown = new PLL_Walker_Dropdown();

			$dropdown->output(
				array(
					'name'     => 'pll_filter_post_type',
					'class'    => 'lang_dropdown',
					'selected' => isset( $_GET['pll_filter_post_type'] ) ? $_GET['pll_filter_post_type'] : '',
					'flag'     => true,
					'items'    => array(
						-1 => __( 'Show all translations', 'polylang' ),
						0  => __( 'Untranslated', 'polylang' ),
					),
				)
			);
		}
	}

	/**
	 * Adds the language filter to the pages list table
	 * Adds the delete translation action
	 *
	 * @since 1.8
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( in_array( $screen->base, array( 'edit', 'upload' ) ) && $this->model->is_translated_post_type( $screen->post_type ) ) {
			$post_type_object = get_post_type_object( $screen->post_type );
			if ( $post_type_object ) {
				if ( current_user_can( $post_type_object->cap->create_posts ) ) {
					$text = __( 'Add new translation', 'polylang' );
				} elseif ( 'attachment' === $screen->post_type ) {
					$text = __( 'Edit media translation', 'polylang' );
				} else {
					$text = __( 'Edit translation', 'polylang' );
				}

				$params = array(
					'screen'     => $screen->post_type,
					'addNew'     => $text,
					'fromPost'   => isset( $_GET['from_post'] ) ? (int) $_GET['from_post'] : 0,
					'strings'    => array(
						'addNewTranslation' => __( 'Add new translation', 'polylang' ),
						'editTranslation'   => __( 'Edit translation', 'polylang' ),
						'addLink'           => __( 'Add link to translation', 'polylang' ),
					),
					'nonces'     => array(
						'switchLang'      => wp_create_nonce( 'pll_language' ),
						'editPost'        => wp_create_nonce( 'pll_edit_post' ),
						'addPost'         => wp_create_nonce( 'pll_add_post' ),
						'addMedia'        => wp_create_nonce( 'pll_add_media' ),
						'editTranslation' => wp_create_nonce( 'pll_edit_translation' ),
					),
				);

				wp_localize_script( 'pll_post', 'pll_post_edit', $params );
			}
		}
	}

	/**
	 * Filters the pages by language in the parent dropdown list in quick edit
	 *
	 * @since 0.9
	 *
	 * @param array $args Arguments passed to wp_dropdown_pages
	 * @return array Modified arguments
	 */
	public function quick_edit_dropdown_pages_args( $args ) {
		if ( isset( $_POST['post_lang'] ) ) {
			$args['lang'] = $this->model->get_language( $_POST['post_lang'] );
		}
		return $args;
	}

	/**
	 * Called when creating a new post translation
	 *
	 * @since 1.5
	 *
	 * @param string $post_type Current post type
	 * @param object $post      Current post
	 */
	public function new_post_translation( $post_type, $post ) {
		if ( isset( $_GET['from_post'], $_GET['new_lang'] ) && $this->model->is_translated_post_type( $post_type ) ) {
			$this->model->post->save_translations( (int) $_GET['from_post'], array( $_GET['new_lang'] => $post->ID ) );
			$this->model->post->set_language( $post->ID, $_GET['new_lang'] );

			$from_post = get_post( (int) $_GET['from_post'] );
			$lang = $this->model->get_language( $_GET['new_lang'] );

			if ( $from_post && $lang ) {
				$_POST['post_title'] = $from_post->post_title;

				$data = array(
					'post_content' => $from_post->post_content,
					'post_excerpt' => $from_post->post_excerpt,
				);

				if ( 'attachment' !== $post_type ) {
					$data['post_name'] = wp_unique_post_slug( sanitize_title( $from_post->post_title ), $post->ID, $post->post_status, $post->post_type, $post->post_parent );
				}

				$data = $this->model->post->translate_post_fields( $data, $lang, $from_post->post_type );

				foreach ( $data as $key => $value ) {
					$_POST[ $key ] = $value;
				}

				// Copy featured image
				if ( 'attachment' !== $post_type ) {
					$thumbnail_id = get_post_thumbnail_id( $from_post->ID );
					if ( ! empty( $thumbnail_id ) ) {
						set_post_thumbnail( $post->ID, $thumbnail_id );
					}
				}

				// Copy taxonomies
				$taxonomies = get_object_taxonomies( $post_type );

				// Copy post metas and allow plugins to do their stuff
				$meta_keys = $this->model->post->copy_post_metas( $from_post->ID, $post->ID, $lang->slug );

				// Maybe add sticky posts
				if ( 'post' === $post_type && is_sticky( $from_post->ID ) ) {
					stick_post( $post->ID );
				}

				/**
				 * Fires after copying post metas
				 *
				 * @since 0.6
				 *
				 * @param int    $from_post_id Post id from which we copy inform
				 /**
				 * Fires after copying post metas
				 *
				 * @since 0.6
				 *
				 * @param int    $from_post_id Post id from which we copy information
				 * @param int    $to_post_id   New post id
				 * @param string $lang         Language slug
				 * @param array  $meta_keys    List of copied meta keys
				 */
				do_action( 'pll_copy_post_metas', $from_post->ID, $post->ID, $lang->slug, $meta_keys );

				// Copy categories and post tags
				foreach ( $taxonomies as $taxonomy ) {
					if ( isset( $_POST['tax_input'][ $taxonomy ] ) ) {
						continue; // Already done by wp
					}

					$terms = get_the_terms( $from_post->ID, $taxonomy );
					if ( is_array( $terms ) ) {
						$terms = wp_list_pluck( $terms, 'term_id' );
						$terms = array_map( 'intval', $terms );
						wp_set_object_terms( $post->ID, $terms, $taxonomy );
					}
				}
			}

			// Set default language for post format when there is no translation
			if ( 'post_format' === $post_type ) {
				$post_id = $post->ID;
				wp_set_object_terms( $post_id, array( 'standard' ), 'post_format' );
			}
		}
	}

	/**
	 * Displays the Languages box in the 'Edit Post' and 'Edit Page' screens
	 *
	 * @since 0.1
	 *
	 * @param WP_Post $post
	 */
	public function post_language_meta_box( $post ) {
		$post_id = $post->ID;
		$post_type = $post->post_type;

		$lang = ( $lg = $this->model->post->get_language( $post_id ) ) ? $lg :
			( isset( $_GET['new_lang'] ) ? $this->model->get_language( $_GET['new_lang'] ) :
			$this->pref_lang );

		$dropdown = new PLL_Walker_Dropdown();

		wp_nonce_field( 'pll_language', '_pll_nonce' );

		// NOTE: the class "tags-input" allows to include the field in the autosave $_POST (see autosave.js)
		printf(
			'<p><strong>%1$s</strong></p>
			<label class="screen-reader-text" for="%2$s">%1$s</label>
			<div id="select-%3$s-language">%4$s</div>',
			esc_html__( 'Language', 'polylang' ),
			$post_type,
			'post',
			$dropdown->walk(
				$this->model->get_languages_list(),
				array(
					'name'     => 'post_lang_choice',
					'class'    => 'post_lang_choice tags-input',
					'selected' => $lang ? $lang->slug : '',
					'flag'     => true,
				)
			)
		);

		/**
		 * Fires after the 'Language' metabox has been displayed
		 *
		 * @since 1.7
		 */
		do_action( 'pll_' . $post_type . '_language' );
	}

	/**
	 * Called when a post (or page) is saved, published or updated
	 *
	 * @since 1.5
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update  Whether it is an update or not
	 */
	public function save_post_metas( $post_id, $post, $update ) {
		if ( $this->model->is_translated_post_type( $post->post_type ) ) {
			if ( isset( $_POST['post_lang_choice'] ) ) {
				if ( 'attachment' === $post->post_type ) {
					$this->model->post->set_language( $post_id, $_POST['post_lang_choice'] );
				} else {
					$lang = $this->model->get_language( $_POST['post_lang_choice'] );
					$this->model->post->set_language( $post_id, $lang );
				}
			}

			if ( isset( $_POST['post_tr_lang'] ) ) {
				$translations = array();

				foreach ( $_POST['post_tr_lang'] as $lang => $tr_id ) {
					if ( ! empty( $tr_id ) ) {
						$translations[ $lang ] = (int) $tr_id;
					}
				}

				$this->model->post->save_translations( $post_id, $translations );
			}
		}
	}

	/**
	 * Called when a post, page or media is deleted
	 * Don't delete translations if this is a post revision thanks to AndyDeGroo who catched this bug
	 * http://wordpress.org/support/topic/plugin-polylang-quick-edit-still-breaks-translation-linking-of-pages-in-072
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function delete_post( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) ) {
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
		$dropdown_args['lang'] = isset( $_POST['lang'] ) ? $this->model->get_language( $_POST['lang'] ) : $this->model->post->get_language( $post->ID ); // ajax or not ?
		if ( ! $dropdown_args['lang'] ) {
			$dropdown_args['lang'] = $this->pref_lang;
		}

		return $dropdown_args;
	}
}
