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

if ( ! class_exists( 'WPS\PostTypes\CustomFieldAdminFilter' ) ) {


	class CustomFieldAdminFilter extends \WPS\Core\Singleton {

		protected $name;

		public function __construct( $name = 'custom_field' ) {
			$this->name = sanitize_html_class( $name );

			add_filter( 'parse_query', array( $this, 'admin_posts_filter' ) );
			add_filter( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		}

		protected function get_name() {
			return 'admin_filter_' . $this->name;
		}

		public function admin_posts_filter( $query ) {
			global $pagenow;
			if (
				is_admin() &&
				'edit.php' === $pagenow &&
				isset( $_GET[ $this->get_name() ] ) && $_GET[ $this->get_name() ] != ''
			) {
				$query->query_vars['meta_key'] = $_GET[ $this->get_name() ];
				if (
					isset( $_GET['admin_filter_field_value'] ) &&
					$_GET['admin_filter_field_value'] != ''
				) {
					$query->query_vars['meta_value'] = $_GET['admin_filter_field_value'];
				}
			}
		}

		public function restrict_manage_posts() {
			global $wpdb;
			$sql = "SELECT DISTINCT $wpdb->postmeta.meta_key FROM $wpdb->postmeta";
			$sql .= "INNER JOIN $wpdb->posts ON ( $wpdb->postmeta.post_id = $wpdb->posts.ID )";
			$sql .= "WHERE 1=1 AND wp_posts.post_type = 'seller' AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'acf-disabled' OR $wpdb->posts.post_status = 'private')";
			$sql .= "ORDER BY 1";

			$fields = $wpdb->get_results( $sql, ARRAY_N );
			?>
			<select name="<?php echo $this->get_name() ?>">
				<option value=""><?php _e( 'Filter By Custom Fields', 'wps' ); ?></option>
				<?php
				$current   = isset( $_GET[ $this->get_name() ] ) ? $_GET[ $this->get_name() ] : '';
				$current_v = isset( $_GET['admin_filter_field_value'] ) ? $_GET['admin_filter_field_value'] : '';
				foreach ( $fields as $field ) {
					if ( substr( $field[0], 0, 1 ) != "_" ) {
						printf
						(
							'<option value="%s"%s>%s</option>',
							$field[0],
							$field[0] == $current ? ' selected="selected"' : '',
							$field[0]
						);
					}
				}
				?>
			</select> <?php _e( 'Value:', 'wps' ); ?>
			<input type="text" name="admin_filter_field_value" value="<?php echo $current_v; ?>"/>
			<?php
		}
	}
}