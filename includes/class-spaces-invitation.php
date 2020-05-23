<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Spaces_Invitation {

	const PRIVATE   = -2;
	const COMMUNITY = -1;

	/**
	 * The single instance of Spaces_Invitation.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * Local instance of Spaces_Invitation_Admin_API
	 *
	 * @var Spaces_Invitation_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * This value is used to cache the invitation link (use the method invitation_link())
	 *
	 * @var string|null
	 */
	private $invite_link;

	/**
	 * WordPress Database Class.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		global $wpdb;

		$this->_version = $version;
		$this->_token   = 'Spaces_Invitation';
		$this->db       = $wpdb;

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		add_filter( 'default_space_setting', array( $this, 'add_settings_item' ) );
		add_filter( 'privacy_description', array( $this, 'invalid_invitation_link' ) );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		add_action( 'wp_loaded', array( $this, 'maybe_add_user_and_redirect' ) );

		add_action( 'wp_ajax_invitation_link', array( $this, 'on_ajax_call' ) );
		add_action( 'wp_ajax_nopriv_invitation_link', array( $this, 'on_ajax_call' ) );
		add_action( 'wp_ajax_self_registration', array( $this, 'toggle_self_registration' ) );
		add_action( 'wp_ajax_nopriv_self_registration', array( $this, 'toggle_self_registration' ) );

		$this->load_plugin_textdomain();// Handle localisation.
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
		add_filter(
			'is_self_registration_enabled',
			function() {
				return $this->is_self_registration_enabled( get_current_blog_id() );
			}
		);
	}

	/**
	 * Add an option field to the spaces "defaultspace"-theme.
	 */
	public function add_settings_item( $settings_items ) {
		if ( $this->can_change_invitation_options() ) {
			$is_private_or_community = $this->blog_is_private_or_community();
			// update_option( 'invitation_link_active', (string) ! $is_private_or_community );
			$link                = get_home_url() . '?invitation_link=' . $this->get_invitation_link();
			$link_enabled        = get_option( 'invitation_link_active' );
			$toggle_button_class = $is_private_or_community ? '' : 'link-disabled';
			$default_role        = get_option( 'default_role' );

			$item = array(
				'id'   => 'invitation-item',
				'html' => $this->render(
					'settings',
					array(
						'text'                => $link_enabled ? 'enabled' : 'disabled',
						'link'                => $link,
						'link_enabled'        => $link_enabled,
						'toggle_button_class' => $toggle_button_class,
						'default_role'        => $default_role,
					)
				),
			);

			$self_registration_item = $this->self_registration_build_item();
			array_splice( $settings_items, count( $settings_items ) - 1, 0, array( $item, $self_registration_item ) );
		}

		$leave_space_items = $this->build_leave_space_items();
		array_splice( $settings_items, count( $settings_items ), 0, $leave_space_items );

		return $settings_items;
	}

	/**
	 * Triggered by the filter 'privacy_description' (by the plugin more-privacy-options).
	 *
	 * @param string $description The already existing description.
	 * @return string
	 */
	public function invalid_invitation_link( $description ) {
		if ( isset( $_GET['src'] ) && 'invitation' === $_GET['src'] ) {
			$text = esc_html( __( 'Sorry... The invitation link you used is not (or no longer) valid.' ) );
			return "<strong>$text</strong><br/>$description";
		}
		return $description;
	}

	/**
	 * Triggered by 'wp_loaded'.
	 * Check if the invitation_link link is present and valid.
	 *
	 * @todo the user might already be a subscriber and giving her the default_role could be a promition.
	 */
	public function maybe_add_user_and_redirect() {
		$current_url    = trim( $_SERVER['WP_HOME'] . strtok( $_SERVER['REQUEST_URI'], '?' ), '/' );
		$get_parameters = '';

		// var_dump(get_option( 'invitation_link_active' ));exit;
		if ( wp_login_url() === $current_url && get_option( 'invitation_link_active' ) ) {
			add_filter( 'privacy_description', array( $this, 'add_form' ) );
		}

		if ( get_home_url() === $current_url && 'true' === $_GET['leave_space'] ) {
			remove_user_from_blog( get_current_user_id() );
			header( 'Location: ' . get_home_url() );
			exit;
		}

		if ( $this->is_self_registration_enabled( get_current_blog_id() ) ) {
			$this->handle_self_registration( $current_url );
			return;
		} elseif ( 'success' === $_GET['invitation'] ) {
			add_filter( 'spaces_invitation_notices', $this->message( 'You successfully joined this space' ) );
			return;
		} elseif (
			! isset( $_GET['invitation_link'] ) // the cheapest way out (performancewise). the invitation_link queryvar is not set.
			|| get_home_url() !== $current_url // we are not on the home_url.
			|| ! is_user_logged_in() // the user is not logged in.
			|| is_super_admin()
		) {
			return;
		}

		if ( get_option( 'invitation_link' ) === $_GET['invitation_link'] // queryvar matches blog setting.
			&& get_option( 'invitation_link_active' )
		) {
			if ( is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) ) {
				$this->update_role_if_needed();
			} else {
				add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option( 'default_role' ) );
				$get_parameters = '?invitation=success';
			}

			header( 'Location: ' . get_home_url() . $get_parameters );
			exit;
		}

		header( 'Location: ' . get_home_url() . '/wp-login.php?action=privacy&src=invitation' );
		exit;

	}

	/**
	 * Returns the genrated invitation link.
	 * If there is no link in the database the link is generated.
	 *
	 * With this function the invitation link can be added and retrieved only when it is required and not always.
	 *
	 * @return string
	 */
	public function get_invitation_link() {
		if ( null === $this->invite_link ) {
			add_blog_option( null, 'invitation_link', sha1( uniqid() ) );
			$this->invite_link = get_option( 'invitation_link' );
		}

		return $this->invite_link;
	}

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles() {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-frontend' );
		wp_localize_script(
			$this->_token . '-frontend',
			'INVITATION_ADMIN_URL',
			array( 'url' => admin_url( 'admin-ajax.php' ) )
		);
		wp_localize_script(
			$this->_token . '-frontend',
			'INVITATION_TEXT_OPTIONS',
			array(
				'invitation'        => array(
					'true'  => 'Invitation Link enabled',
					'false' => 'Invitation Link disabled',
				),
				'self_registration' => array(
					'true'  => 'Self Registration enabled',
					'false' => 'Self Registration disabled',
				),
			)
		);
	} // End enqueue_scripts ()

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( $hook = '' ) {
		// wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		// wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( $hook = '' ) {
		// wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		// wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'spaces-invitation', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'spaces-invitation';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main Spaces_Invitation Instance
	 *
	 * Ensures only one instance of Spaces_Invitation is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object Spaces_Invitation instance
	 * @see Spaces_Invitation()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Spaces_Invitation is forbidden' ) ), esc_attr( $this->_version ) );

	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Spaces_Invitation is forbidden' ) ), esc_attr( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	} // End install ()

	/**
	 * This function is called when the ajax call for 'invitation_link' is called.
	 * The function never returns.
	 */
	public function on_ajax_call() {
		if ( $this->can_change_invitation_options() ) {
			update_blog_option( null, 'invitation_link_active', (string) ( 'true' === $_POST['activate'] ) );
			wp_die();
		}

		echo esc_html( __( 'You are not allowed to do that.' ) );
		die();
	}

	/**
	 * checks if users can become an author in the current space
	 *
	 * @return bool  return true if the current space is open and users can join on their own
	 */
	public function is_self_registration_enabled( $blog_id ) {
		return get_blog_option( $blog_id, 'self_registration', true );
	}

	/**
	 * This function is called when the ajax call for 'self_registration' is called.
	 * The function never returns.
	 */
	public function toggle_self_registration() {
		if ( $this->can_change_self_registration() ) {
			update_blog_option( null, 'self_registration', ( 'true' === $_POST['activate'] ) );
			wp_die();
		}

		echo esc_html( __( 'You are not allowed to do that.' ) );
		die();
	}

	public function add_form( $message ) {
		return $message . $this->render(
			'form',
			array(
				'home_url' => get_home_url(),
				'message'  => esc_html( __( 'Enter this space with an invitation link:' ) ),
			)
		);
	}

	/**
	 * Renders the view $template with $variables.
	 * In the view the variables can be accessed with {{ variable_name }}.
	 * The view is taken from the view/ folder and a .html sufix is appended.
	 *
	 * @param mixed $template
	 * @param mixed $variables
	 *
	 * @reutrn string
	 */
	private function render( $template, $variables ) {
		$keys = array_map(
			function( $key ) {
				return '/{{ *' . preg_quote( $key ) . ' *}}/';
			},
			array_keys( $variables )
		);

		return preg_replace(
			$keys,
			array_values( $variables ),
			file_get_contents( __DIR__ . '/views/' . $template . '.html' )
		);
	}

	/**
	 * Returns whether the user is allowed to change see, activate / deactivate the invitation link.
	 *
	 * @return bool
	 */
	private function can_change_invitation_options() {
		return /* null !== $public && ( self::PRIVATE !== $public || */ current_user_can( 'promote_users' ); /* ) */
	}

	/**
	 * Returns wether the user is allowed to change / see, activate / deactivate self registration
	 *
	 * @return bool
	 */
	private function can_change_self_registration() {
		return ! $this->blog_is_private() && ( current_user_can( 'manage_options' ) || is_super_admin() );
	}

	/**
	 * Returns true if  the current blog is either private or community.
	 * Return false for everything else.
	 *
	 * @return bool
	 */
	private function blog_is_private_or_community() {
		$public = (int) get_option( 'blog_public' );

		return $this->blog_is_private() || ( self::COMMUNITY === $public /* && ! spaces()->blogs_privacy->is_self_registration_enabled( get_current_blog_id() ) */ );
	}

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	/**
	 * Checks if $default_role > $user_role.
	 */
	private function default_role_is_greater() {
		$user              = wp_get_current_user();
		$user_role_name    = $user->roles[ array_key_first( $user->roles ) ];
		$default_role_name = get_option( 'default_role' );
		$user_role         = get_role( $user_role_name );
		$default_role      = get_role( $default_role_name );

		$user_capabilities = array_keys( array_filter( $user_role->capabilities, 'boolval' ) );
		if ( is_super_admin() ) {
			return false;
		}

		foreach ( $user_capabilities as $capability ) {
			if ( $default_role->capabilities[ $capability ] !== true ) {
				return false;
			}
		}

		return count( array_filter( $default_role->capabilities, 'boolval' ) ) > count( $user_capabilities );
	}

	/**
	 * Updates the current_user's role if $default_role > $user_role.
	 */
	private function update_role_if_needed() {
		if ( $this->default_role_is_greater() ) {
			add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option( 'default_role' ) );
		}
	}

	private function message( $message ) {
		return function() use ( $message ) {
			// return esc_html( __( $message ) );
			return $this->render( 'welcome', array( 'message' => esc_html( __( $message ) ) ) );
		};
	}

	private function self_registration_build_item() {
		$enabled = $this->is_self_registration_enabled( get_current_blog_id() );

		return array(
			'id'   => 'self-registration-item',
			'html' => $this->render(
				'self_registration',
				array(
					'text'             => esc_html( __( $enabled ? 'enabled' : 'disabled' ) ),
					'enabled'          => $enabled,
					'is_private_class' => $this->blog_is_private() ? 'private' : '',
				)
			),
		);
	}

	/**
	 * Returns if the current blog is a private blog
	 */
	private function blog_is_private() {
		return self::PRIVATE === (int) get_option( 'blog_public' );
	}

	private function handle_self_registration( $current_url ) {
		if (
			! is_user_logged_in()
			|| get_home_url() !== $current_url
			|| $this->blog_is_private()
			|| ( is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) && ! $this->default_role_is_greater() )
		) {
			return;
		}

		if ( 'true' === $_GET['join'] ) {
			add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option( 'default_role' ) );

			header( 'Location: ' . get_home_url() . '?invitation=success' );
			exit;
		}

		add_filter(
			'spaces_invitation_notices',
			function( $message ) {
				return $message . $this->render(
					'join',
					array(
						'label' => __( 'Join this space' ),
						'url'   => $current_url . '?join=true',
					)
				);
			}
		);
	}

	private function build_leave_space_items() {
		if ( ! is_user_member_of_blog( get_current_user_id() ) ) {
			return array();
		}

		return array(
			array(
				'id'   => 'leave-space-item',
				'html' => $this->render(
					'leave_space',
					array(
						'text' => __( 'Leave Space' ),
						'url'  => get_home_url() . '?leave_space=true',
					)
				),
			),
		);
	}
}
