<?php
/**
 * plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create an Invitation link for users which already have an account.
 * When you click the link you automatically become a member with the default role.
 */
class Spaces_Invite_Link {

	public function __construct() {
		add_action( 'wp_initialize_site', array( $this, 'create_secret_once' ), 10 ); // This is triggered when a blog is created.
	}

	/**
	 * @todo: add a check: does the secret already exist. don't accidentally overwrite.
	 */
	public function create_secret_once() {
		update_option( 'spaces-invite-link-secret', 'here-goes-my-secret' );
	}

}


