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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPS\PostTypes\PostTypeAdminFilter' ) ) {


	class PostTypeAdminFilter {

		protected $name;
		protected $meta_key;
		protected $post_type;

		public function __construct( $for_post_type, $post_type_to_filter, $meta_key ) {
			$this->name      = sprintf( '%s-%s', sanitize_html_class( $post_type_to_filter ), sanitize_html_class( $meta_key ) );
			$this->_post_type = $for_post_type;
			$this->post_type = $post_type_to_filter;
			$this->meta_key  = $meta_key;

			add_filter( 'parse_query', array( $this, 'admin_posts_filter' ) );
			add_filter( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		}

		protected function get_name() {
			return 'meta_post_type_filter_' . $this->name;
		}

		public function admin_posts_filter( $query ) {
			global $pagenow;

			if (
				is_admin() &&
				'edit.php' === $pagenow &&
				isset( $_GET['post_type'] ) && $_GET['post_type'] === $this->_post_type &&
				isset( $_GET[ $this->get_name() ] ) && $_GET[ $this->get_name() ] != ''
			) {
				$query->query_vars['meta_key']     = $this->meta_key;
				$query->query_vars['meta_value']   = $_GET[ $this->get_name() ];
				$query->query_vars['meta_compare'] = 'LIKE';
			}
		}

		public function restrict_manage_posts( $post_type ) {
			if ( $this->_post_type !== $post_type ) {
				return;
			}

			$post_type = get_post_type_object( $this->post_type );
			$posts     = get_posts( array(
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_type'      => $this->post_type,
				'posts_per_page' => - 1,
			) );

			?>
			<select name="<?php echo $this->get_name() ?>">
				<option value=""><?php echo __( 'Filter By ', 'wps' ) . $post_type->labels->singular_name; ?></option>
				<?php
				$current = isset( $_GET[ $this->get_name() ] ) ? $_GET[ $this->get_name() ] : '';
				foreach ( $posts as $post ) {
					printf(
						'<option value="%s"%s>%s</option>',
						$post->ID,
						$post->ID == $current ? ' selected="selected"' : '',
						$post->post_title
					);
				}
				?>
			</select>
			<?php
		}

	}
}