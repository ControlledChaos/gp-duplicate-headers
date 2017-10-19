<?php
/*
Plugin Name: Duplicate GeneratePress Page Headers
Plugin URI: https://github.com/ControlledChaos/gp-duplicate-headers
Description: Duplicate page headers in the GeneratePress theme. Requires the GP Premium plugin.
Version: 0.0.1
Author: Greg Sweet
Author URI: https://ccdzine.com
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gp-duplicate-headers
*/

namespace GP_Duplicate_Headers;

// No direct access, please
if ( ! defined( 'ABSPATH' ) ) exit;

class GP_Duplicate_Headers_Functions {

	/**
	 * Constructor magic method
	 */
	public function __construct() {

		// Create the duplicate & redirect function.
		add_action( 'admin_action_generate_page_header_duplicate', array( $this, 'generate_page_header_duplicate' ) );

		// Add the duplicate link.
		add_filter( 'post_row_actions', array( $this, 'generate_page_header_duplicate_post_link' ), 10, 2 );

	}

	/**
	 * Function creates post duplicate as a draft and redirects then to the edit post screen.
	 */
	function generate_page_header_duplicate() {
		global $wpdb;
		if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'generate_page_header_duplicate' == $_REQUEST['action'] ) ) ) {
			wp_die('No page header to duplicate has been supplied!');
		}

		/**
		 * Nonce verification.
		 */
		if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
			return;

		/**
		 * Get the original post id.
		 */
		$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );

		/**
		 * And all the original post data then.
		 */
		$post = get_post( $post_id );

		/**
		 * If you don't want current user to be the new post author,
		 * then change next couple of lines to this: $new_post_author = $post->post_author;
		 */
		$current_user = wp_get_current_user();
		$new_post_author = $current_user->ID;

		/**
		 * If post data exists, create the post duplicate.
		 */
		if (isset( $post ) && $post != null) {

			/**
			 * New post data array.
			 */
			$args = array(
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_author'    => $new_post_author,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_name'      => $post->post_name,
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_status'    => 'draft',
				'post_title'     => $post->post_title,
				'post_type'      => $post->post_type,
				'to_ping'        => $post->to_ping,
				'menu_order'     => $post->menu_order
			);

			/**
			 * Insert the post by wp_insert_post() function.
			 */
			$new_post_id = wp_insert_post( $args );

			/**
			 * Get all current post terms ad set them to the new post draft.
			 */
			$taxonomies = get_object_taxonomies($post->post_type); // Returns array of taxonomy names for post type, ex array("category", "post_tag");
			foreach ($taxonomies as $taxonomy) {
				$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
			}

			/**
			 * Duplicate all post meta just in two SQL queries.
			 */
			$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
			if (count($post_meta_infos)!=0) {
				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
				foreach ($post_meta_infos as $meta_info) {
					$meta_key = $meta_info->meta_key;
					if( $meta_key == '_wp_old_slug' ) continue;
					$meta_value = addslashes($meta_info->meta_value);
					$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
				}
				$sql_query.= implode(" UNION ALL ", $sql_query_sel);
				$wpdb->query($sql_query);
			}


			/**
			 * Finally, redirect to the edit post screen for the new draft.
			 */
			wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
			exit;
		} else {
			wp_die('Header creation failed, could not find original post: ' . $post_id);
		}
	}

	/**
	 * Add the duplicate link to action list for post_row_actions.
	 */
	function generate_page_header_duplicate_post_link( $actions, $post ) {
		if ($post->post_type=='generate_page_header' && current_user_can('edit_posts')) {
			$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=generate_page_header_duplicate&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicate this Page Header" rel="permalink">Duplicate</a>';
		}
		return $actions;
	}

}

// Run the GP_Duplicate_Headers_Functions class.
$gp_duplicate_headers_functions = new GP_Duplicate_Headers_Functions;
