<?php
/**
 * Post Type Prevent Slug/PostName Update Class
 *
 * Prevents the slug/post_name from being changed after initially being set.
 *
 * You may copy, distribute and modify the software as long as you track
 * changes/dates in source files. Any modifications to or software including
 * (via compiler) GPL-licensed code must also be made available under the GPL
 * along with build & install instructions.
 *
 * PHP Version 7.2
 *
 * @category   WPS\PostTypes
 * @package    WPS\PostTypes
 * @author    Travis Smith <t@wpsmith.net>
 * @copyright 2018 Travis Smith
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link      https://wpsmith.net/
 * @since     0.0.1
 */

namespace WPS\PostTypes;

if ( ! class_exists( 'WPS\PostTypes\PreventSlugUpdate' ) ) {
	/**
	 * Class PreventSlugUpdate.
	 *
	 * @package WPS\PostTypes
	 */
	class PreventSlugUpdate {
		/**
		 * Post Type registered name
		 *
		 * @var string
		 */
		public $post_type;

		/**
		 * Whether reverting post name back or not.
		 * 
		 * @var bool
		 */
		private $reverting_post_name = false;

		/**
		 * PreventSlugUpdate constructor.
		 *
		 * @param string $post_type Registered post type name.
		 */
		public function __construct( $post_type ) {

			$this->post_type = $post_type;
			add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );

		}

		/**
		 * Checks if the slug changed and reverts back if it does.
		 *
		 * @param int $post_ID Post ID.
		 * @param \WP_Post $post_after Post object following the update.
		 * @param \WP_Post $post_before Post object before the update.
		 */
		public function post_updated( $post_ID, $post_after, $post_before ) {

			if ( $this->reverting_post_name || $this->post_type !== $post_before->post_type ) {
				return;
			}

			if ( $post_before->post_name !== $post_after->post_name ) {
				$this->reverting_post_name = true;
				$post_after->post_name = $post_before->post_name;
				wp_update_post( $post_after );
				$this->reverting_post_name = false;
			}

		}

	}
}