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
	 * @author  Travis Smith <t@wpsmith.net>
	 */
	abstract class PostType extends WPS\Core\Singleton {

		/**
		 * Post Type registered name
		 *
		 * @var string
		 */
		public $post_type;

		/**
		 * Singular Post Type registered name
		 *
		 * @var string
		 */
		public $singular;

		/**
		 * Plural Post Type registered name
		 *
		 * @var string
		 */
		public $plural;

		/**
		 * Whether to add envira gallery after the entry content.
		 *
		 * @var bool
		 */
		public $gallery;

		/**
		 * Args for the gallery shortcode.
		 *
		 * @var array
		 */
		public $gallery_args = array(
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
		private $template_loader;

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
		public $remove_metaboxes = array();

		/**
		 * Whether to remove meta functions from post type display.
		 *
		 * @var bool
		 */
		public $remove_post_type_entry_meta = false;

		/**
		 * Whether to remove footer functions from post type display.
		 *
		 * @var bool
		 */
		public $remove_post_type_entry_footer = false;

		/**
		 * Whether to create a related types taxonomy.
		 *
		 * @var bool
		 */
		public $types = false;

		/**
		 * Sets the priority of the metabox.
		 * Accepts 'high', 'default', or 'low'.
		 *
		 * @var string
		 */
		public $mb_priority;

		/**
		 * Sets the priority of the yoast metabox.
		 * Accepts 'high', 'default', or 'low'.
		 *
		 * @var string|bool
		 */
		public $yoast_priority;

		/**
		 * Array of posts.
		 *
		 * @var \WP_Post[]
		 */
		public $posts;

		/**
		 * Whether to remove SEO support for post type.
		 *
		 * @var bool
		 */
		public $remove_seo_support = false;

		/**
		 * Taxonomies
		 *
		 * @var array
		 */
		public $taxonomies = array();

		/**
		 * Post_Type constructor.
		 */
		protected function __construct() {

			$this->plural   = $this->plural ? $this->plural : $this->post_type;
			$this->singular = $this->singular ? $this->singular : $this->post_type;

			// Maybe remove post type entry meta.
			if ( $this->remove_post_type_entry_meta ) {
				add_action( 'genesis_header', array( $this, 'remove_post_type_entry_meta' ) );
			}

			// Maybe remove post type entry footer.
			if ( $this->remove_post_type_entry_footer ) {
				add_action( 'genesis_header', array( $this, 'remove_post_type_entry_footer' ) );
			}

			// Create the post type.
			$this->add_action( 'init', array( $this, 'create_post_type' ), 0 );

			// Maybe create Types taxonomy.
			if ( $this->types ) {
				$this->add_action( 'init', array( $this, 'create_types' ), 0 );
			}

			// Maybe append gallery or have envira gallery support.
			if ( $this->gallery ) {
				add_filter( 'envira_gallery_pre_data', array( $this, 'envira_gallery_pre_data' ), 10 );
				add_action( 'genesis_entry_content', array( $this, 'gallery' ), 15 );
			}

			// Maybe fix yoast priority
			if ( $this->yoast_priority ) {
				add_filter( 'wpseo_metabox_prio', array( $this, 'wpseo_metabox_priority' ) );
			}

			// Maybe set priority of ACF metabox.
			if ( $this->mb_priority && ( 'high' === $this->mb_priority || 'default' === $this->mb_priority || 'low' === $this->mb_priority ) ) {
				add_filter( 'acf/input/meta_box_priority', array( $this, 'set_acf_metabox_priority' ), 10, 2 );
			}

			// Maybe remove metaboxes.
			$remove_mbs = array();
			foreach ( $this->remove_metaboxes as $metabox ) {
				switch ( $metabox ) {
					case 'layout':
					case 'genesis-cpt-archives-layout-settings':
						add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_layout_settings_metaboxes',
						) );
						break;
					case 'seo':
					case 'genesis-cpt-archives-seo-settings':
						add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_seo_settings_metaboxes',
						) );
						break;
					case 'settings':
					case 'genesis-cpt-archives-settings':
						add_action( 'genesis_cpt_archives_settings_metaboxes', array(
							$this,
							'genesis_remove_cpt_archives_settings_metaboxes',
						) );
						break;
					default:
						$remove_mbs[] = $metabox;
						break;
				}
			}
			if ( ! empty( $remove_mbs ) ) {
				add_action( 'add_meta_boxes', array( $this, 'remove_metaboxes' ), 500 );
			}

			if ( $this->remove_seo_support ) {
				add_action( 'genesis_cpt_archives_settings_metaboxes', array(
					$this,
					'genesis_remove_cpt_archives_seo_settings_metaboxes',
				) );

				add_filter( 'wpseo_accessible_post_types', array( $this, 'remove_wpseo_accessible_post_type' ) );
			}

			// Initialize fields for ACF.
			$this->add_action( 'plugins_loaded', array( $this, 'initialize_fields' ) );

			$this->maybe_run_optional_methods();
		}

		/**
		 * Runs optional meethods.
		 *
		 * If method exists, it will run them on the correct hook.
		 */
		protected function maybe_run_optional_methods() {
			// Maybe create taxonomy.
			if ( method_exists( $this, 'create_taxonomy' ) ) {
				$this->add_action( 'init', array( $this, 'create_taxonomy' ), 0 );
			}

			// Maybe run init method.
			if ( method_exists( $this, 'init' ) ) {
				$this->init();
			}

			// Maybe create ACF fields.
			if ( method_exists( $this, 'core_acf_fields' ) ) {
				add_action( 'core_acf_fields', array( $this, 'core_acf_fields' ) );
			}

			// Maybe manage post columns.
			if ( method_exists( $this, 'manage_posts_columns' ) ) {
				add_filter( "manage_{$this->post_type}_posts_columns", array( $this, 'manage_posts_columns' ) );
			}

			// Maybe manage post custom columns.
			if ( method_exists( $this, 'manage_posts_custom_column' ) ) {
				add_action( "manage_{$this->post_type}_posts_custom_column", array(
					$this,
					'manage_posts_custom_column',
				), 10, 2 );
			}

			// Maybe run plugins_loaded method.
			foreach (
				array(
					'admin_menu'     => array(),
					'admin_init'     => array(),
					'admin_head'     => array(),
					'plugins_loaded' => array(),
				) as $hook => $args
			) {
				if ( method_exists( $this, $hook ) ) {
					$args = wp_parse_args( $args, $this->get_hook_defaults() );
					$this->add_action( $hook, array(
						$this,
						$hook,
					), $args['priority'], $args['accepted_args'], $args['args'] );
				}
			}
		}

		/**
		 * Remove Post Type from WP SEO's accessible post types.
		 *
		 * @param array $post_types The public post types.
		 *
		 * @return array
		 */
		public function remove_wpseo_accessible_post_type( $post_types ) {
			return array_diff( $post_types, array( $this->post_type ) );
		}

		/**
		 * Hook default args.
		 *
		 * @return array
		 */
		private function get_hook_defaults() {
			return array(
				'priority'      => 10,
				'accepted_args' => 1,
				'args'          => null,
			);
		}

		/**
		 * Set WP SEO Metabox Priority to default.
		 *
		 * @return string
		 */
		public function wpseo_metabox_priority() {
			if ( is_string( $this->yoast_priority ) && in_array( $this->yoast_priority, array(
					'default',
					'low',
					'high'
				), true ) ) {
				return $this->yoast_priority;
			}

			return 'default';
		}

		/**
		 * Initializes ACF Fields on plugins_loaded hook.
		 */
		public function initialize_fields() {
			WPS\Core\Fields::get_instance();
		}

		/**
		 * Removes Genesis Layouts Metabox
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_layout_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-layout-settings', $pagehook, 'main' );
		}

		/**
		 * Removes Genesis SEO Settings Metabox.
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_seo_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-seo-settings', $pagehook, 'main' );
		}

		/**
		 * Removes Genesis CPT Archives Metabox
		 *
		 * @param string $pagehook Page hook for the CPT archive settings page.
		 */
		public function genesis_remove_cpt_archives_settings_metaboxes( $pagehook ) {
			remove_meta_box( 'genesis-cpt-archives-settings', $pagehook, 'main' );
		}

		/**
		 * Register custom post type
		 */
		abstract public function create_post_type();

		/**
		 * Gets supports array.
		 *
		 * @return array Array of post type supports.
		 */
		protected function get_supports() {
			return array();
		}

		/**
		 * Gets rewrite args.
		 *
		 * @return array Array of rewrite post type args.
		 */
		protected function get_rewrite() {
			return array(
				'slug'       => $this->post_type,
				'with_front' => true,
				'pages'      => true,
				'feeds'      => true,
			);
		}

		/**
		 * Registers the post type helper method.
		 *
		 * @param array $args Array of post type args.
		 */
		protected function register_post_type( $args ) {
			$plural_proper = ucwords( $this->get_post_type_word( $this->plural ) );
			register_post_type( $this->post_type, wp_parse_args( $args, array(
				'label'       => __( $plural_proper, 'wps' ),
				'description' => __( 'For ' . $plural_proper, 'wps' ),
				'labels'      => $this->get_labels(),
				'rewrite'     => $this->get_rewrite(),
				'supports'    => $this->get_supports(),
			) ) );
		}

		/**
		 * Gets the post type as words
		 *
		 * @param string $str String to capitalize.
		 *
		 * @return string Capitalized string.
		 */
		protected function get_post_type_word( $str ) {
			return str_replace( '-', ' ', str_replace( '_', ' ', $str ) );
		}

		/**
		 * Remove Genesis Meta functions from Post Type.
		 */
		public function remove_post_type_entry_meta() {
			if ( $this->is_post_type() ) {
				WPS\remove_post_type_entry_meta();
			}
		}

		/**
		 * Remove Genesis Entry Footer functions from Post Type.
		 */
		public function remove_post_type_entry_footer() {
			if ( $this->is_post_type() ) {
				WPS\remove_post_type_entry_footer();
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
		 * Determines whether the given/current post type is the correct post type.
		 *
		 * @param string $post_type The post type in question.
		 *
		 * @return bool Whether given/current post type is this current post type.
		 */
		public function is_post_type( $post_type = '' ) {
			if ( '' === $post_type ) {
				return ( get_post_type() === $this->post_type );
			}

			return ( $this->post_type === $post_type );
		}

		/**
		 * Add the gallery after the end of the content
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
		 * Filter the envira gallery $data and replace with the image data for our images in the ACF gallery field
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
		 * Register Custom Types Taxonomy
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
		 * Gets the template loader.
		 *
		 * @param array $args Template Loader args.
		 *
		 * @return WPS\Templates\Template_Loader
		 */
		protected function get_template_loader( $args = array() ) {
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
		 * Gets the template loader default args.
		 *
		 * @return array
		 */
		private function get_template_loader_defaults() {
			return array(
				'filter_prefix'    => 'wps_' . $this->post_type,
				'plugin_directory' => plugin_dir_path( dirname( dirname( __FILE__ ) ) ),
			);
		}

		/**
		 * Instantiates a new FieldsBuilder
		 *
		 * @param string $key  Key for fields.
		 * @param array  $args Args for fields.
		 *
		 * @return \StoutLogic\AcfBuilder\FieldsBuilder
		 */
		protected function new_fields_builder( $key = '', $args = array() ) {
			$key = $key ? $key : $this->post_type;

			return WPS\Core\Fields::get_instance()->new_fields_builder( $key, $args );
		}

		/**
		 * Set Advanced Custom Fields metabox priority.
		 *
		 * @param  string $priority    The metabox priority.
		 * @param  array  $field_group The field group data.
		 *
		 * @return string  $priority    The metabox priority, modified.
		 */
		public function set_acf_metabox_priority( $priority, $field_group ) {
			if ( 'group_' . $this->post_type === $field_group['key'] ) {
				$priority = $this->mb_priority;
			}

			return $priority;
		}

		/**
		 * Gets the context of a metabox by ID.
		 *
		 * @param string $metabox Maetabox ID.
		 *
		 * @return mixed|void
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
		 * Remove metaboxes
		 */
		public function remove_metaboxes() {
			foreach ( $this->remove_metaboxes as $key => $metabox ) {
				remove_meta_box( $metabox, $this->post_type, $this->get_metabox_context( $metabox ) );
			}

		}

		/**
		 * Manage posts custom column.
		 *
		 * @param string $column  Column slug.
		 * @param int    $post_id Post ID.
		 */
		public function manage_posts_custom_column( $column, $post_id ) {
			switch ( $column ) {
				case 'thumbnail':
					if ( has_post_thumbnail( $post_id ) ) {
						echo get_the_post_thumbnail( $post_id, array( 50, 50 ) );
					}
					break;
			}
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
		 * Hooks a function on to a specific action.
		 *
		 * Actions are the hooks that the WordPress core launches at specific points
		 * during execution, or when specific events occur. Plugins can specify that
		 * one or more of its PHP functions are executed at these points, using the
		 * Action API.
		 *
		 * @since 1.2.0
		 *
		 * @param string   $tag             The name of the action to which the $function_to_add is hooked.
		 * @param callable $function_to_add The name of the function you wish to be called.
		 * @param int      $priority        Optional. Used to specify the order in which the functions
		 *                                  associated with a particular action are executed. Default 10.
		 *                                  Lower numbers correspond with earlier execution,
		 *                                  and functions with the same priority are executed
		 *                                  in the order in which they were added to the action.
		 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
		 * @param array    $args            Args to pass to the function.
		 */
		public function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1, $args = array() ) {
			if ( did_action( $tag ) || doing_action( $tag ) ) {
//				WPS\write_log( array( $tag, \get_class( $function_to_add[0] ), $function_to_add[1] ), 'DOING' );
				call_user_func_array( $function_to_add, (array) $args );
			} else {
				add_action( $tag, $function_to_add, $priority, $accepted_args );
			}
		}
	}
}

