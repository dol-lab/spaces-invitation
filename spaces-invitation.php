<?php
/**
 * Plugin Name: Spaces Invitation
 * Version: 0.1
 * Plugin URI: https://github.com/dol-lab/spaces-invitation
 * Description: Manages Invitations to Spaces.
 * Author URI: https://github.com/dol-lab
 * Requires at least: 5.0
 * Tested up to: 5.0
 *
 * Text Domain: spaces-invitation
 * Domain Path: /lang/
 *
 * @package WordPress
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once __DIR__ . '/includes/class-spaces-invitation-request.php';
require_once __DIR__ . '/includes/class-spaces-invitation.php';
require_once __DIR__ . '/includes/class-spaces-invitation-settings.php'; // might be nice to have some settings in the backend like renewing invite-links.

// require_once __DIR__ . '/includes/class-spaces-invite-link'; // access class functions via spaces_invitation()->invite_link->...

/**
 * Returns the main instance of Spaces_Invitation to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return Spaces_Invitation
 */
function spaces_invitation() {
	$instance = Spaces_Invitation::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		// $instance->settings = Spaces_Invitation_Settings::instance( $instance );
	}

	return $instance;
}

spaces_invitation();
