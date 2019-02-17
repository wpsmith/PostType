<?php
/**
 * Post Type Abstract Class
 *
 * Assists in the creation and management of Post Types.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    WPS\PostTypes
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2018 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\PostTypes;

use WPS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPS\PostTypes\PostType' ) ) {
	/**
	 * Post Type Abstract Class
	 *
	 * Assists in creating and managing Post Types.
	 *
	 * @package WPS\PostTypes
	 */
	class PostType extends WPS\Core\Registerable {

		/**
		 * Post Type registered name
		 *
		 * @var string
		 */
		protected $post_type;

		/**
		 * Post type args.
		 *
		 * @var array
		 */
		protected $args = array();

		/**
		 * Post type defaults.
		 *
		 * @var array
		 */
		protected $defaults = array();

		/**
		 * Post type rewrite args.
		 *
		 * @var array
		 */
		protected $rewrite = array();

		/**
		 * Array of post type supports.
		 *
		 * @var []string
		 */
		protected $supports = array( 'title', );

		/**
		 * Args for the gallery shortcode.
		 *
		 * @var array
		 */
		protected $gallery_args = array(
			'orderby'    => '',
			'columns'    => '',
			'id'         => '',
			'size'       => '',
			'itemtag'    => '',
			'icontag'    => '',
			'captiontag' => '',
			'link'       => '',
			'include'    => '',
			'exclude'    => '',
		);

		/**
		 * Template loader.
		 *
		 * @var WPS\Templates\Template_Loader
		 */
		protected $template_loader;

		/**
		 * What metaboxes to remove.
		 *
		 * Supports:
		 *  'genesis-cpt-archives-layout-settings'
		 *  'genesis-cpt-archives-seo-settings'
		 *  'genesis-cpt-archives-settings'
		 *  'wpseo_meta'
		 *  'rcp_meta_box'
		 *  'trackbacksdiv'
		 *  'postcustom'
		 *  'commentsdiv'
		 *  'slugdiv'
		 *  'authordiv'
		 *  'revisionsdiv'
		 *  'formatdiv'
		 *  'commentstatusdiv'
		 *  'categorydiv'
		 *  'tagsdiv-post_tag'
		 *  'pageparentdiv'
		 *
		 * @var array
		 */
		protected $metaboxes_to_remove = array();

		/**
		 * Roles to which to add the post type custom capabilities.
		 *
		 * @var []string
		 */
		protected $roles = array( 'administrator' );

		/**
		 * ACF Fields Builder
		 *
		 * @var \StoutLogic\AcfBuilder\FieldsBuilder
		 */
		protected $builder;

		/**
		 * Template Loader Defaults.
		 *
		 * @var array
		 */
		protected $template_loader_defaults = array(
			'filter_prefix' => 'wps_',
		);

		/**
		 * Post type specific custom capabilities.
		 *
		 * @var array
		 */
		protected $capabilities = array();

		/**
		 * Array of posts.
		 *
		 * @var \WP_Post[]
		 */
		public $posts;

		/**
		 * PostType constructor.
		 *
		 * @param string $post_type Post type registration name.
		 * @param array $args Array of post type args.
		 * @param bool $create Whether to auto-create the post type.
		 *
		 * @throws \Exception If initialized after the init hook.
		 */
		public function __construct( $post_type, array $args = array(), $create = true ) {

			$is_valid = $this->validate_construct( $post_type );
			if ( is_wp_error( $is_valid ) ) {
				throw new \Exception( $is_valid->get_error_message() );
			}

			if ( is_array( $post_type ) && isset( $args['post_type'] ) ) {
				$args      = empty( $args ) ? $post_type : wp_parse_args( $post_type, $args );
				$post_type = $args['post_type'];
			}

			$this->post_type = $post_type;
			if ( ! isset( $args['plural'] ) ) {

				$this->plural = WPS\Core\Utils\Inflect::pluralize( $post_type );

			} else {
				$this->plural = $args['plural'];
			}

			$this->singular = $this->singular ? $this->singular : $this->post_type;

			// Set the rewrite.
			$this->rewrite = array(
				'slug'       => $this->post_type,
				'with_front' => true,
				'pages'      => true,
				'feeds'      => true,
			);

			// Set the template loader defaults.
			$this->template_loader_defaults = array(
				'filter_prefix'    => 'wps_' . $this->post_type,
				'plugin_directory' => plugin_dir_path( dirname( dirname( __FILE__ ) ) ),
			);

			// Set the post type default args.
			$plural_proper  = $this->get_post_type_word( $this->plural );
			$this->defaults = array(
				'label'       => __( $plural_proper, 'wps' ),
				'description' => __( 'For ' . $plural_proper, 'wps' ),
				'labels'      => $this->get_labels(),
				'rewrite'     => $this->rewrite,
				'supports'    => $this->supports,
				'public'      => true,
			);

			// Set Post Type args.
			$this->args = $args;

			// Create the post type.
			if ( $create ) {
				$this->add_action( 'init', array( $this, 'register_post_type' ), 0 );
			}

		}


		/** PUBLIC API */

		/**
		 * Determines whether the given/current post type is the correct post type.
		 *
		 * @param string $post_type The post type in question.
		 *
		 * @return bool Whether given/current post type is this current post type.
		 */
		public function is_post_type( $post_type = '' ) {
			if ( '' === $post_type ) {
				return ( $this->get_post_type() === $this->post_type );
			}

			return ( $this->post_type === $post_type );
		}

//		/**
//		 * Register custom post type.
//		 *
//		 * Alias for create() to support backwards comparability.
//		 */
//		public function create_post_type() {
//			$this->create();
//		}

		/**
		 * Register custom post type
		 */
		public function create() {
			$this->register_post_type();
		}


		/** PUBLIC API - Setters */

		/**
		 * Set custom post type capabilities.
		 *
		 * @param string $slug Custom capability slug.
		 */
		public function set_custom_capabilities( $slug = '' ) {

			$slug               = $slug ? $slug : $this->post_type;
			$this->capabilities = array(
				// Meta capabilities.
				'edit_post'              => "edit_{$slug}",
				'read_post'              => "read_{$slug}",
				'delete_post'            => "delete_{$slug}",

				// Primitive capabilities used within map_meta_cap().
				'create_posts'           => "edit_{$slug}s",

				// Primitive capabilities used outside of map_meta_cap().
				'edit_posts'             => "edit_{$slug}s",
				'edit_others_posts'      => "edit_others_{$slug}s",
				'publish_posts'          => "publish_{$slug}s",
				'read_private_posts'     => "read_private_{$slug}s",

				// Post type.
				'read'                   => 'read',
				'delete_posts'           => "delete_{$slug}s",
				'delete_private_posts'   => "delete_private_{$slug}s",
				'delete_published_posts' => "delete_published_{$slug}s",
				'delete_others_posts'    => "delete_others_{$slug}s",
				'edit_private_posts'     => "edit_private_{$slug}s",
				'edit_published_posts'   => "edit_published_{$slug}s",

				// Terms.
				'manage_post_terms'      => "manage_{$slug}_terms",
				'edit_post_terms'        => "edit_{$slug}_terms",
				'delete_post_terms'      => "delete_{$slug}_terms",
				'assign_post_terms'      => "assign_{$slug}_terms"
			);
		}


		/** PUBLIC API - Getters */

		/**
		 * Get custom post type capabilities.
		 *
		 * @return array
		 */
		public function get_capabilities() {
			return $this->capabilities;
		}

		/**
		 * Gets post type labels.
		 *
		 * @return array Array of post type labels.
		 */
		public function get_labels() {
			$singular        = $this->get_post_type_word( $this->singular );
			$singular_proper = ucwords( $singular );
			$plural          = $this->get_post_type_word( $this->plural );
			$plural_proper   = ucwords( $plural );

			return array(
				'name'                  => _x( $plural_proper, 'Post Type General Name', 'wps' ),
				'singular_name'         => _x( $singular_proper, 'Post Type Singular Name', 'wps' ),
				'menu_name'             => __( $plural_proper, 'wps' ),
				'name_admin_bar'        => __( $plural_proper, 'wps' ),
				'archives'              => __( "$singular_proper Archives", 'wps' ),
				'attributes'            => __( "$singular_proper Attributes", 'wps' ),
				'parent_item_colon'     => __( "Parent $singular_proper:", 'wps' ),
				'all_items'             => __( "All $plural_proper", 'wps' ),
				'add_new_item'          => __( "Add New $singular_proper", 'wps' ),
				'add_new'               => __( 'Add New', 'wps' ),
				'new_item'              => __( "New $singular_proper", 'wps' ),
				'edit_item'             => __( "Edit $singular_proper", 'wps' ),
				'update_item'           => __( "Update $singular_proper", 'wps' ),
				'view_item'             => __( "View $singular_proper", 'wps' ),
				'view_items'            => __( "View $plural_proper", 'wps' ),
				'search_items'          => __( "Search $singular_proper", 'wps' ),
				'not_found'             => __( 'Not found', 'wps' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'wps' ),
				'featured_image'        => __( "$singular_proper Image", 'wps' ),
				'set_featured_image'    => __( "Set $singular image", 'wps' ),
				'remove_featured_image' => __( "Remove $singular image", 'wps' ),
				'use_featured_image'    => __( "Use as $singular image", 'wps' ),
				'insert_into_item'      => __( "Insert into $singular", 'wps' ),
				'uploaded_to_this_item' => __( "Uploaded to this $singular", 'wps' ),
				'items_list'            => __( "$plural_proper list", 'wps' ),
				'items_list_navigation' => __( "$plural_proper list navigation", 'wps' ),
				'filter_items_list'     => __( "Filter $plural list", 'wps' ),
			);
		}

		/**
		 * Gets the ID.
		 *
		 * @return false|int|string
		 */
		public function get_the_ID() {

			$post_id = get_the_ID() ? get_the_ID() : '';
			$post_id = '' === $post_id && isset( $_GET['post'] ) && '' !== $_GET['post'] ? $_GET['post'] : $post_id;
			$post_id = '' === $post_id && isset( $_POST['post_ID'] ) && '' !== $_POST['post_ID'] ? $_POST['post_ID'] : $post_id;

			return intval( $post_id );

		}

		/**
		 * Gets the post type.
		 *
		 * @return bool|false|string
		 */
		public function get_post_type() {

			$post_type = get_post_type();
			if ( $post_type ) {
				return $post_type;
			}

			$post_type = isset( $_REQUEST['post_type'] ) && '' !== $_REQUEST['post_type'] ? $_REQUEST['post_type'] : '';
			if ( $post_type ) {
				return $post_type;
			}

			$id = $this->get_the_ID();
			if ( 0 !== $id ) {
				$post = get_post( $id );

				return $post->post_type;
			}

			return false;

		}

		/**
		 * Gets the posts for specific post type.
		 *
		 * @param array $args
		 *
		 * @return \WP_Post[]
		 */
		public function get_posts( $args = array() ) {
			if ( ! empty( $this->posts ) ) {
				return $this->posts;
			}

			$args        = wp_parse_args( $args, array( 'post_type' => $this->post_type ) );
			$this->posts = get_posts( $args );

			return $this->posts;
		}

		/**
		 * Post type args for register_post_type.
		 *
		 * @return array
		 */
		public function get_args() {
			return wp_parse_args( $this->args, $this->get_defaults() );
		}

		/**
		 * Getter method for retrieving post type registration defaults.
		 */
		public function get_defaults() {
			return $this->defaults;
		}

		/**
		 * Gets supports array.
		 *
		 * @return array Array of post type supports.
		 */
		protected function get_supports() {
			return $this->supports;
		}

		/**
		 * Gets rewrite args.
		 *
		 * @return array Array of rewrite post type args.
		 */
		protected function get_rewrite() {
			return $this->rewrite;
		}


		/** PUBLIC API - Admin Functions */

		/**
		 * Determines whether the current admin page is an edit or add new page.
		 *
		 * @return bool
		 */
		public function is_edit_or_new_page() {
			global $pagenow;

			if ( ! is_admin() || $this->post_type !== $this->get_post_type() ) {
				return false;
			}

			return ( 'post.php' === $pagenow || 'post-new.php' === $pagenow );
		}

		/**
		 * Determines whether the current admin page is the add new page.
		 *
		 * @return bool
		 */
		public function is_new_page() {
			global $pagenow;

			if ( ! is_admin() || $this->post_type !== $this->get_post_type() ) {
				return false;
			}

			return ( 'post-new.php' === $pagenow );
		}

		/**
		 * Determines whether the current admin page is the edit page.
		 *
		 * @return bool
		 */
		public function is_edit_page() {
			global $pagenow;

			if ( ! is_admin() || $this->post_type !== $this->get_post_type() ) {
				return false;
			}

			return ( 'post.php' === $pagenow );
		}

		/**
		 * Removes given metaboxes.
		 *
		 * Supports:
		 *  'genesis-cpt-archives-layout-settings'
		 *  'genesis-cpt-archives-seo-settings'
		 *  'genesis-cpt-archives-settings'
		 *  'wpseo_meta'
		 *  'rcp_meta_box'
		 *  'trackbacksdiv'
		 *  'postcustom'
		 *  'commentsdiv'
		 *  'slugdiv'
		 *  'authordiv'
		 *  'revisionsdiv'
		 *  'formatdiv'
		 *  'commentstatusdiv'
		 *  'categorydiv'
		 *  'tagsdiv-post_tag'
		 *  'pageparentdiv'
		 *
		 * @param []string $metaboxes Array of metabox slugs.
		 */
		public function remove_metaboxes( $metaboxes ) {

			foreach ( $metaboxes as $metabox ) {
				switch ( $metabox ) {
					case 'layout':
					case 'genesis-cpt-archives-layout-settings':
						// @todo check to see whether to use $this->add_action or add_action
						$this->add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_layout_settings_metaboxes',
						) );
						break;
					case 'seo':
					case 'genesis-cpt-archives-seo-settings':
						// @todo check to see whether to use $this->add_action or add_action
						$this->add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_seo_settings_metaboxes',
						) );
						break;
					case 'settings':
					case 'genesis-cpt-archives-settings':
						// @todo check to see whether to use $this->add_action or add_action
						$this->add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_settings_metaboxes',
						) );
						break;
					default:
						$this->metaboxes_to_remove[] = $metabox;
						break;
				}
			}

			if ( ! empty( $this->metaboxes_to_remove ) ) {
				// @todo check to see whether to use $this->add_action or add_action
				$this->add_action( 'add_meta_boxes', array( $this, '_remove_metaboxes' ), 500 );
			}
		}

		/**
		 * Manage post columns filters.
		 *
		 * @param callable $fn Callable function to filter posts.
		 * @param callable|null $pre_get_posts_fn Callable function to filter posts on pre_get_posts.
		 */
		public function restrict_manage_posts( $fn, $pre_get_posts_fn = null ) {
			if ( is_callable( $fn ) ) {
				/**
				 * Fires before the Filter button on the Posts and Pages list tables.
				 *
				 * The Filter button allows sorting by date and/or category on the
				 * Posts list table, and sorting by date on the Pages list table.
				 *
				 * @param string $post_type The post type slug.
				 * @param string $which The location of the extra table nav markup:
				 *                          'top' or 'bottom' for WP_Posts_List_Table,
				 *                          'bar' for WP_Media_List_Table.
				 */
				add_filter( 'restrict_manage_posts', function ( $post_type, $which ) use ( $fn ) {
					if ( $this->post_type !== $post_type ) {
						return $post_type;
					}

					return call_user_func( $fn, $post_type, $which );
				}, 10, 2 );
			}

			if ( is_callable( $pre_get_posts_fn ) ) {
				$this->add_action( 'pre_get_posts', $pre_get_posts_fn, 10, 2 );
			}
		}

		/**
		 * Manage post columns.
		 *
		 * @param callable $fn Callable function to manage posts columns.
		 */
		public function manage_posts_columns( $fn ) {
			$this->add_action( "manage_{$this->post_type}_posts_columns", $fn );
		}

		/**
		 * Manage sortable columns.
		 *
		 * @param callable $fn Callable function to manage sortable columns.
		 */
		public function manage_sortable_columns( $fn ) {
			$this->add_action( "manage_edit-{$this->post_type}_sortable_columns", $fn );
		}

		/**
		 * Manage post custom columns.
		 *
		 * @param callable $fn Callable function to manage custom columns.
		 */
		public function manage_posts_custom_column( $fn ) {
			$this->add_action( "manage_{$this->post_type}_posts_custom_column", function ( $column, $post_id ) {
				switch ( $column ) {
					case 'thumbnail':
						if ( has_post_thumbnail( $post_id ) ) {
							echo get_the_post_thumbnail( $post_id, array( 50, 50 ) );
						}
						break;
				}
			}, 10, 2 );
			$this->add_action( "manage_{$this->post_type}_posts_custom_column", $fn, 10, 2 );
		}

		/**
		 * Admin menu hook.
		 *
		 * @param callable $fn Callable function to run during admin_menu.
		 */
		public function admin_menu( $fn ) {
			$this->add_action( 'admin_menu', $fn );
		}

		/**
		 * Admin init hook.
		 *
		 * @param callable $fn Callable function to run during admin_init.
		 */
		public function admin_init( $fn ) {
			$this->add_action( 'admin_init', $fn );
		}

		/**
		 * Admin head hook.
		 *
		 * @param callable $fn Callable function to run during admin_head.
		 */
		public function admin_head( $fn ) {
			$this->add_action( 'admin_head', $fn );
		}


		/** PUBLIC API - ACF Functions */

		/**
		 * Instantiates a new FieldsBuilder
		 *
		 * @param string $key Key for fields.
		 * @param array $args Args for fields.
		 *
		 * @return \StoutLogic\AcfBuilder\FieldsBuilder
		 */
		public function get_fields_builder( $key = '', $args = array() ) {
			if ( $this->builder ) {

				$key = $key ? $key : $this->post_type;

				$this->builder = WPS\Core\Fields::get_instance()->new_fields_builder( $key, $args );

			}

			return $this->builder;
		}

		/**
		 * Sets the priority of the metabox.
		 *
		 * @see set_acf_metabox_priority()
		 *
		 * @param string $mb_priority Accepts 'high', 'default', or 'low'.
		 */
		public function set_acf_mb_priority( $mb_priority ) {
			// Maybe set priority of ACF metabox.
			if ( 'high' === $mb_priority ||
			     'default' === $mb_priority ||
			     'low' === $mb_priority ) {

				$post_type = $this->post_type;
				/**
				 * Set Advanced Custom Fields metabox priority.
				 *
				 * @param  string $priority The metabox priority.
				 * @param  array $field_group The field group data.
				 *
				 * @return string  $priority    The metabox priority, modified.
				 */
				$this->add_filter( 'acf/input/meta_box_priority', function ( $priority, $field_group ) use ( $post_type, $mb_priority ) {
					if ( 'group_' . $post_type === $field_group['key'] ) {
						$priority = $mb_priority;
					}

					return $priority;
				}, 10, 2 );
			}
		}

		/**
		 * Initialize fields for ACF.
		 */
		public function init_acf_fields() {
			$this->add_action( 'plugins_loaded', array( $this, 'initialize_fields' ) );
		}

		/**
		 * Create ACF fields function.
		 */
		public function core_acf_fields( $fn ) {
			$this->add_action( 'core_acf_fields', $fn );
		}


		/** PUBLIC API - Taxonomies */

		/**
		 * Maybe create Types taxonomy.
		 */
		public function add_types_taxonomy() {
			$this->add_action( 'init', array( $this, 'create_types' ), 0 );
		}

		/**
		 * Create taxonomy.
		 */
		public function create_taxonomy( $fn ) {
			$this->add_action( 'init', $fn );
		}


		/** PUBLIC API - Genesis, WP SEO, Gallery */

		/**
		 * Remove meta & footer functions from post type display.
		 */
		public function remove_genesis_meta() {
			// Remove post type entry meta.
			$this->add_action( 'genesis_header', array( $this, 'remove_post_type_entry_meta' ) );

			// Remove post type entry footer.
			$this->add_action( 'genesis_header', array( $this, 'remove_post_type_entry_footer' ) );
		}

		/**
		 * Append gallery and/or add envira gallery support after the entry content.
		 */
		public function add_gallery() {
			// @todo check whether to use add_filter or $this->add_filter
			$this->add_filter( 'envira_gallery_pre_data', array( $this, 'envira_gallery_pre_data' ), 10 );

			// @todo check whether to use add_action or $this->add_action
			$this->add_action( 'genesis_entry_content', array( $this, 'gallery' ), 15 );
		}

		/**
		 * Fix yoast priority.
		 *
		 * By default, this sets the WP SEO Metabox to default priority but can be changed to high or low.
		 */
		public function fix_wpseo( $mb_priority = 'default' ) {

			if ( 'high' !== $mb_priority &&
			     'default' !== $mb_priority &&
			     'low' !== $mb_priority ) {
				$mb_priority = 'default';
			}

			/**
			 * Set WP SEO Metabox Priority to given priority.
			 *
			 * @param  string $priority The metabox priority.
			 *
			 * @return string $mb_priority The metabox priority, modified.
			 */
			$this->add_filter( 'wpseo_metabox_prio', function ( $priority ) use ( $mb_priority ) {
				return $mb_priority;
			} );

		}

		/**
		 * Removes SEO Support from Post Type.
		 */
		public function remove_seo_support() {
			$this->add_action( 'genesis_cpt_archives_settings_metaboxes', array(
				$this,
				'genesis_remove_cpt_archives_seo_settings_metaboxes',
			) );

			$this->add_filter( 'wpseo_accessible_post_types', array( $this, 'remove_wpseo_accessible_post_type' ) );
		}


		/** PUBLIC API - Capabilities */

		/**
		 * Adds capabilities integrations.
		 *
		 * @param callable $map_meta_cap_fn Map Meta Cap function.
		 */
		public function add_caps( $map_meta_cap_fn ) {

			// Maybe add map_meta_cap filter.
			$this->map_meta_cap( $map_meta_cap_fn );

			// Integrate with members plugin.
			// @todo check to see whether to use $this->add_action or add_action
			$this->add_action( 'members_register_caps', array( $this, 'members_register_default_caps' ), 5 );

			// Add capabilities to $this->get_roles().
			// @todo check to see whether to use $this->add_action or add_action
			$this->add_action( 'admin_init', array( $this, 'add_caps_to_admin' ) );
		}

		/**
		 * Map meta capabilities.
		 */
		public function map_meta_cap( $map_meta_cap_fn ) {
			if ( is_callable( $map_meta_cap_fn ) ) {
				$this->add_action( 'plugins_loaded', function () use ( $map_meta_cap_fn ) {
					add_filter( 'map_meta_cap', $map_meta_cap_fn, 10, 4 );
				} );
			}
		}

		/**
		 * Gets the roles to which to add the capabilities.
		 *
		 * @return array
		 */
		public function get_roles() {
			return $this->roles;
		}


		/** PUBLIC API - Additional Methods */

		/**
		 * Init function.
		 *
		 * @param callable $fn Callable function to run during init.
		 */
		public function init( $fn ) {
			$this->add_action( 'init', $fn, - 1 );
		}

		/**
		 * Filter query function.
		 *
		 * @param callable $fn Callable function to run during pre_get_posts.
		 */
		public function pre_get_posts( $fn ) {
			$this->add_action( 'pre_get_posts', $fn );
		}

		/**
		 * Plugins Loaded hook.
		 *
		 * @param callable $fn Callable function to run during plugins_loaded.
		 */
		public function plugins_loaded( $fn ) {
			$this->add_action( 'plugins_loaded', $fn );
		}


		/** PUBLIC API - Template Loader */

		/**
		 * Gets the template loader within some initial data.
		 *
		 * @param array $args Template Loader args.
		 * @param string $name Template name.
		 * @param array $data Template Data args.
		 *
		 * @return WPS\Templates\Template_Loader
		 */
		public function get_template_loader_with_data( $args = array(), $name = '', $data = array() ) {

			$template_loader = $this->get_template_loader( $args );
			$template_data   = WPS\Templates\TemplateData::get_instance();

			if ( ! empty( $data ) ) {
				foreach ( $data as $key => $value ) {
					$template_data->update( $name, $key, $value );
				}
			}

			$template_data->update( $name, 'template_loader', $template_loader );

			return $template_loader;

		}

		/**
		 * Gets the template loader.
		 *
		 * @param array $args Template Loader args.
		 *
		 * @return WPS\Templates\Template_Loader
		 */
		public function get_template_loader( $args = array() ) {
			if ( $this->template_loader ) {
				return $this->template_loader;
			}

			// Merge Args.
			$args = wp_parse_args( $args, $this->get_template_loader_defaults() );

			// Create template loader.
			$this->template_loader = new WPS\Templates\Template_Loader( $args );

			return $this->template_loader;
		}

		/**
		 * Retrieve a template part.
		 *
		 * @uses  self::get_template_possble_parts() Create file names of templates.
		 * @uses  self::locate_template() Retrieve the name of the highest priority template file that exists.
		 *
		 * @param \WPS\Templates\Template_Loader $loader Template slug.
		 * @param string $slug Template slug.
		 * @param string $name Optional. Default null.
		 * @param bool $load Optional. Default false.
		 *
		 * @return string
		 */
		public function get_template_part( $loader, $slug, $name = null, $load = true ) {
			ob_start();
			$loader->get_template_part( $slug, $name, $load );

			return ob_get_clean();
		}


		/** PRIVATE API */

		/**
		 * Doing it wrong helper that also returns a WP Error object.
		 *
		 * @param callable $fn Function name.
		 * @param string $code Code slug.
		 * @param string $message Error message.
		 * @param mixed $data Data.
		 *
		 * @return \WP_Error
		 */
		protected function _doing_it_wrong( $fn, $code, $message, $data ) {

			_doing_it_wrong( __FUNCTION__, $message, $GLOBALS['wp_version'] );

			return new \WP_Error( $code, $message, $data );

		}

		/**
		 * Validates the constructor inputs.
		 *
		 * @uses $wp_current_filter To check to see if we are in an activation hook.
		 *
		 * @param string|array $post_type Post type name or post type args.
		 *
		 * @return bool|\WP_Error
		 */
		protected function validate_construct( $post_type ) {

			if ( did_action( 'init' ) && ! doing_action( 'init' ) ) {
				global $wp_current_filter;

				foreach ( $wp_current_filter as $index => $filter ) {
					if ( strpos( $filter, 'activate' ) !== false ) {
						return true;
					}
				}

				return $this->_doing_it_wrong( __FUNCTION__, 'init_post_type_too_late', __( 'Initializing post type too late. Should be done in the init hook or before.', 'wps' ), $this );

			} elseif ( ! is_string( $post_type ) && is_array( $post_type ) && ! isset( $args['post_type'] ) ) {

				return $this->_doing_it_wrong( __FUNCTION__, 'no_post_type_name', __( 'Post Type name is required.', 'wps' ), $post_type );
			}

			return true;

		}

		/**
		 * Gets the template loader default args.
		 *
		 * @return array
		 */
		protected function get_template_loader_defaults() {
			return $this->template_loader_defaults;
		}

		/**
		 * Registers the post type helper method.
		 *
		 * @param array $args Array of post type args.
		 */
		public function register_post_type() {

			register_post_type( $this->post_type, $this->get_args() );

		}

		/**
		 * Gets the post type as words
		 *
		 * @param string $str String to capitalize.
		 *
		 * @return string Capitalized string.
		 */
		protected function get_post_type_word( $str ) {
			return ucwords( $this->get_word( $str ) );
		}

		/**
		 * Register Custom Types Taxonomy
		 *
		 * @access private
		 * @todo Use WPS\Taxonomy
		 */
		public function create_types() {

			$labels  = array(
				'name'                       => _x( 'Types', 'Taxonomy General Name', 'wps' ),
				'singular_name'              => _x( 'Type', 'Taxonomy Singular Name', 'wps' ),
				'menu_name'                  => __( 'Types', 'wps' ),
				'all_items'                  => __( 'All Items', 'wps' ),
				'parent_item'                => __( 'Parent Item', 'wps' ),
				'parent_item_colon'          => __( 'Parent Item:', 'wps' ),
				'new_item_name'              => __( 'New Item Name', 'wps' ),
				'add_new_item'               => __( 'Add New Item', 'wps' ),
				'edit_item'                  => __( 'Edit Item', 'wps' ),
				'update_item'                => __( 'Update Item', 'wps' ),
				'view_item'                  => __( 'View Item', 'wps' ),
				'separate_items_with_commas' => __( 'Separate items with commas', 'wps' ),
				'add_or_remove_items'        => __( 'Add or remove items', 'wps' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'wps' ),
				'popular_items'              => __( 'Popular Items', 'wps' ),
				'search_items'               => __( 'Search Items', 'wps' ),
				'not_found'                  => __( 'Not Found', 'wps' ),
				'no_terms'                   => __( 'No items', 'wps' ),
				'items_list'                 => __( 'Items list', 'wps' ),
				'items_list_navigation'      => __( 'Items list navigation', 'wps' ),
			);
			$rewrite = array(
				'slug'         => $this->post_type . '-type',
				'with_front'   => true,
				'hierarchical' => false,
			);
			$args    = array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => true,
				'rewrite'           => $rewrite,
				'show_in_rest'      => true,
			);
			register_taxonomy( $this->post_type . '-type', array( $this->post_type ), $args );

		}

		/**
		 * Remove Genesis Meta functions from Post Type.
		 *
		 * @access private
		 */
		public function remove_post_type_entry_meta() {
			if ( $this->is_post_type() ) {
				WPS\remove_post_type_entry_meta();
			}
		}

		/**
		 * Remove Genesis Entry Footer functions from Post Type.
		 *
		 * @access private
		 */
		public function remove_post_type_entry_footer() {
			if ( $this->is_post_type() ) {
				WPS\remove_post_type_entry_footer();
			}
		}

		/**
		 * Add the gallery after the end of the content.
		 *
		 * @access private
		 */
		public function gallery() {
			if ( ! $this->is_post_type() ) {
				return;
			}

			$gallery = get_post_meta( get_the_ID(), 'gallery' );

			// If we have something output the gallery.
			if ( is_array( $gallery[0] ) ) {
				echo do_shortcode( $this->get_gallery_sc( $gallery[0] ) );
			} else {
				$gallery = get_post_meta( get_the_ID(), 'related_gallery', true );
				if ( shortcode_exists( 'envira-gallery' ) ) {
					echo do_shortcode( sprintf( '[envira-gallery id="%s"]', $gallery ) );
				}
			}

		}

		/**
		 * Gets the gallery shortcode. Returns envira-gallery or gallery.
		 *
		 * @param array $image_ids Array of image IDs.
		 *
		 * @return string Shortcode string.
		 */
		private function get_gallery_sc( $image_ids = array() ) {
			$sc = '[';
			if ( shortcode_exists( 'envira-gallery' ) ) {
				$sc .= 'envira-gallery slug="envira-dynamic-gallery"';
			} else { // Fall back to WordPress inbuilt gallery.
				$sc .= 'gallery envira="true" ids="' . implode( ',', $image_ids ) . '"';
			}

			foreach ( $this->gallery_args as $k => $v ) {
				if ( '' !== $v ) {
					$sc .= sprintf( ' %s="%s"', $k, $v );
				}
			}

			$sc .= ']';

			return $sc;
		}

		/**
		 * Filter the envira gallery $data and replace with the image data for our images in the ACF gallery field
		 *
		 * @access private
		 *
		 * @param array $data Gallery data.
		 *
		 * @return array Maybe modified gallery data.
		 */
		public function envira_gallery_pre_data( $data ) {

			if ( ! $this->is_post_type() || ( $data['config']['type'] !== 'fc' ) ) {
				return $data;
			}

			$newdata = array();

			// Don't lose the original gallery id and configuration.
			$newdata['id']     = $data['id'];
			$newdata['config'] = $data['config'];

			// Get list of images from our ACF gallery field.
			$gallery   = get_post_meta( get_the_ID(), 'gallery' );
			$image_ids = $gallery[0]; // It's an array within an array.

			// If we have some images loop around and populate a new data array.
			if ( is_array( $image_ids ) ) {

				foreach ( $image_ids as $image_id ) {

					$newdata['gallery'][ $image_id ]['status']            = 'active';
					$newdata['gallery'][ $image_id ]['src']               = esc_url( wp_get_attachment_url( $image_id ) );
					$newdata['gallery'][ $image_id ]['title']             = esc_html( get_the_title( $image_id ) );
					$newdata['gallery'][ $image_id ]['link']              = esc_url( wp_get_attachment_url( $image_id ) );
					$newdata['gallery'][ $image_id ]['alt']               = trim( strip_tags( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) );
					$newdata['gallery'][ $image_id ]['thumb']             = esc_url( wp_get_attachment_thumb_url( $image_id ) );
					$newdata['gallery'][ $image_id ]['data-featherlight'] = 'image';

				}
			}

			return $newdata;
		}

		/**
		 * Remove metaboxes
		 *
		 * @access private
		 */
		public function _remove_metaboxes() {
			foreach ( $this->metaboxes_to_remove as $key => $metabox ) {
				remove_meta_box( $metabox, $this->post_type, $this->get_metabox_context( $metabox ) );
			}

		}

		/**
		 * Gets the context of a metabox by ID.
		 *
		 * @access private
		 *
		 * @param string $metabox Maetabox ID.
		 *
		 * @return mixed
		 */
		protected function get_metabox_context( $metabox ) {
			$context = 'normal';

			if ( in_array( $metabox, array(
				'categorydiv',
				'tagsdiv-post_tag',
				'pageparentdiv',
			), true ) ) {
				$context = 'side';
			} elseif ( in_array( $metabox, array(
				'members-cp',
			), true ) ) {
				$context = 'advanced';
			}

			return apply_filters( 'wps_posttype_get_metabox_context', $context, $metabox, $this->post_type, $this );
		}

		/**
		 * Removes Genesis SEO Settings Metabox.
		 *
		 * @access private
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_seo_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-seo-settings', $pagehook, 'main' );
		}

		/**
		 * Remove Post Type from WP SEO's accessible post types.
		 *
		 * @access private
		 *
		 * @param array $post_types The public post types.
		 *
		 * @return array
		 */
		public function remove_wpseo_accessible_post_type( $post_types ) {
			return array_diff( $post_types, array( $this->post_type ) );
		}

		/**
		 * Initializes ACF Fields on plugins_loaded hook.
		 *
		 * @access private
		 */
		public function initialize_fields() {
			WPS\Core\Fields::get_instance();
		}

		/**
		 * Instantiates a new FieldsBuilder
		 *
		 * @param string $key Key for fields.
		 * @param array $args Args for fields.
		 *
		 * @return \StoutLogic\AcfBuilder\FieldsBuilder
		 */
		protected function new_fields_builder( $key = '', $args = array() ) {
			$key = $key ? $key : $this->post_type;

			return WPS\Core\Fields::get_instance()->new_fields_builder( $key, $args );
		}

		/**
		 * Add the capabilities to the administrator.
		 */
		public function add_caps_to_admin() {
			$args = $this->get_args();
			if ( isset( $args['capabilities'] ) && ! empty( $args['capabilities'] ) ) {
				foreach ( $this->get_roles() as $role ) {
					$admin = get_role( $role );
					foreach ( $args['capabilities'] as $meta => $capability ) {
						$admin->add_cap( $capability );
					}
				}

			}
		}

		/**
		 * Integration into Members Plugin.
		 */
		public function members_register_default_caps() {

			if ( ! function_exists( 'members_register_cap' ) ) {
				return;
			}

			$args = $this->get_args();

			if ( ! empty( $args['capability_type'] ) || ! empty( $args['capabilities'] ) ) {
				$capabilities = $this->get_capabilities();
				foreach ( $capabilities as $cap ) {
					members_register_cap( $cap, array( 'label' => $this->get_post_type_word( $cap ) ) );
				}
			}
		}

		/**
		 * Removes Genesis Layouts Metabox
		 *
		 * @access private
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_layout_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-layout-settings', $pagehook, 'main' );
		}

		/**
		 * Removes Genesis CPT Archives Metabox
		 *
		 * @access private
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-settings', $pagehook, 'main' );
		}

	}
}
