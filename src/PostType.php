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

namespace WPS\PostType;

use WPS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPS\PostType\PostType' ) ) {
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
		 * Post_Type constructor.
		 */
		protected function __construct() {

			$this->plural   = $this->plural ? $this->plural : $this->post_type;
			$this->singular = $this->singular ? $this->singular : $this->post_type;

			// Create the post type.
			add_action( 'init', array( $this, 'create_post_type' ), 0 );

			// Maybe create Types taxonomy.
			if ( $this->types ) {
				add_action( 'init', array( $this, 'create_types' ), 0 );
			}

			// Maybe remove post type entry meta.
			if ( $this->remove_post_type_entry_meta ) {
				add_action( 'genesis_header', array( $this, 'remove_post_type_entry_meta' ) );
			}

			// Maybe remove post type entry footer.
			if ( $this->remove_post_type_entry_footer ) {
				add_action( 'genesis_header', array( $this, 'remove_post_type_entry_footer' ) );
			}

			// Maybe run init method.
			if ( method_exists( $this, 'init' ) ) {
				$this->init();
			}

			// Maybe create taxonomy.
			if ( method_exists( $this, 'create_taxonomy' ) ) {
				add_action( 'init', array( $this, 'create_taxonomy' ), 0 );
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

			// Maybe append gallery or have envira gallery support.
			if ( $this->gallery ) {
				add_filter( 'envira_gallery_pre_data', array( $this, 'envira_gallery_pre_data' ), 10 );
				add_action( 'genesis_entry_content', array( $this, 'gallery' ), 15 );
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

			// Initialize fields for ACF.
			add_action( 'plugins_loaded', array( $this, 'initialize_fields' ) );

			// Maybe run plugins_loaded method.
			if ( method_exists( $this, 'plugins_loaded' ) ) {
				add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			}
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
				'label'       => __( $plural_proper, WPS_TEXT_DOMAIN ),
				'description' => __( 'For ' . $plural_proper, WPS_TEXT_DOMAIN ),
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
				'name'                       => _x( 'Types', 'Taxonomy General Name', WPS_TEXT_DOMAIN ),
				'singular_name'              => _x( 'Type', 'Taxonomy Singular Name', WPS_TEXT_DOMAIN ),
				'menu_name'                  => __( 'Types', WPS_TEXT_DOMAIN ),
				'all_items'                  => __( 'All Items', WPS_TEXT_DOMAIN ),
				'parent_item'                => __( 'Parent Item', WPS_TEXT_DOMAIN ),
				'parent_item_colon'          => __( 'Parent Item:', WPS_TEXT_DOMAIN ),
				'new_item_name'              => __( 'New Item Name', WPS_TEXT_DOMAIN ),
				'add_new_item'               => __( 'Add New Item', WPS_TEXT_DOMAIN ),
				'edit_item'                  => __( 'Edit Item', WPS_TEXT_DOMAIN ),
				'update_item'                => __( 'Update Item', WPS_TEXT_DOMAIN ),
				'view_item'                  => __( 'View Item', WPS_TEXT_DOMAIN ),
				'separate_items_with_commas' => __( 'Separate items with commas', WPS_TEXT_DOMAIN ),
				'add_or_remove_items'        => __( 'Add or remove items', WPS_TEXT_DOMAIN ),
				'choose_from_most_used'      => __( 'Choose from the most used', WPS_TEXT_DOMAIN ),
				'popular_items'              => __( 'Popular Items', WPS_TEXT_DOMAIN ),
				'search_items'               => __( 'Search Items', WPS_TEXT_DOMAIN ),
				'not_found'                  => __( 'Not Found', WPS_TEXT_DOMAIN ),
				'no_terms'                   => __( 'No items', WPS_TEXT_DOMAIN ),
				'items_list'                 => __( 'Items list', WPS_TEXT_DOMAIN ),
				'items_list_navigation'      => __( 'Items list navigation', WPS_TEXT_DOMAIN ),
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
		 * @return WPS\Templates\Template_Loader
		 */
		protected function get_template_loader( $args = array() ) {
			if ( $this->template_loader ) {
				return $this->template_loader;
			}

			// Merge Args
			$args = wp_parse_args( $args, $this->get_template_loader_defaults() );

			// Create template loader
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

			return Fields::get_instance()->new_fields_builder( $key, $args );
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
		 * Remove metaboxes
		 */
		public function remove_metaboxes() {
			foreach ( $this->remove_metaboxes as $metabox ) {
				$context = 'normal';
				if ( in_array( $metabox, array(
					'categorydiv',
					'tagsdiv-post_tag',
					'pageparentdiv',
				), true ) ) {
					$context = 'side';
				}

				remove_meta_box( $metabox, $this->post_type, $context );
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
				'name'                  => _x( $plural_proper, 'Post Type General Name', WPS_TEXT_DOMAIN ),
				'singular_name'         => _x( $singular_proper, 'Post Type Singular Name', WPS_TEXT_DOMAIN ),
				'menu_name'             => __( $plural_proper, WPS_TEXT_DOMAIN ),
				'name_admin_bar'        => __( $plural_proper, WPS_TEXT_DOMAIN ),
				'archives'              => __( "$singular_proper Archives", WPS_TEXT_DOMAIN ),
				'attributes'            => __( "$singular_proper Attributes", WPS_TEXT_DOMAIN ),
				'parent_item_colon'     => __( "Parent $singular_proper:", WPS_TEXT_DOMAIN ),
				'all_items'             => __( "All $plural_proper", WPS_TEXT_DOMAIN ),
				'add_new_item'          => __( "Add New $singular_proper", WPS_TEXT_DOMAIN ),
				'add_new'               => __( 'Add New', WPS_TEXT_DOMAIN ),
				'new_item'              => __( "New $singular_proper", WPS_TEXT_DOMAIN ),
				'edit_item'             => __( "Edit $singular_proper", WPS_TEXT_DOMAIN ),
				'update_item'           => __( "Update $singular_proper", WPS_TEXT_DOMAIN ),
				'view_item'             => __( "View $singular_proper", WPS_TEXT_DOMAIN ),
				'view_items'            => __( "View $plural_proper", WPS_TEXT_DOMAIN ),
				'search_items'          => __( "Search $singular_proper", WPS_TEXT_DOMAIN ),
				'not_found'             => __( 'Not found', WPS_TEXT_DOMAIN ),
				'not_found_in_trash'    => __( 'Not found in Trash', WPS_TEXT_DOMAIN ),
				'featured_image'        => __( "$singular_proper Image", WPS_TEXT_DOMAIN ),
				'set_featured_image'    => __( "Set $singular image", WPS_TEXT_DOMAIN ),
				'remove_featured_image' => __( "Remove $singular image", WPS_TEXT_DOMAIN ),
				'use_featured_image'    => __( "Use as $singular image", WPS_TEXT_DOMAIN ),
				'insert_into_item'      => __( "Insert into $singular", WPS_TEXT_DOMAIN ),
				'uploaded_to_this_item' => __( "Uploaded to this $singular", WPS_TEXT_DOMAIN ),
				'items_list'            => __( "$plural_proper list", WPS_TEXT_DOMAIN ),
				'items_list_navigation' => __( "$plural_proper list navigation", WPS_TEXT_DOMAIN ),
				'filter_items_list'     => __( "Filter $plural list", WPS_TEXT_DOMAIN ),
			);
		}
	}
}

