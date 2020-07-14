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
	 * Wrapper class for the current GET variables
	 *
	 * @var Spaces_Invitation_Request
	 */
	private $get;

	/**
	 * Wrapper class for the current POST variables
	 *
	 * @var Spaces_Invitation_Request
	 */
	private $post;

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		global $wpdb;
		$this->get  = new Spaces_Invitation_Request( $_GET );
		$this->post = new Spaces_Invitation_Request( $_POST );

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

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'wp_loaded', array( $this, 'maybe_add_user_and_redirect' ) );

		add_action( 'wp_ajax_invitation_link', array( $this, 'ajax_toggle_invitation_link' ) );
		add_action( 'wp_ajax_nopriv_invitation_link', array( $this, 'ajax_toggle_invitation_link' ) );
		add_action( 'wp_ajax_self_registration', array( $this, 'ajax_toggle_self_registration' ) );
		add_action( 'wp_ajax_nopriv_self_registration', array( $this, 'ajax_toggle_self_registration' ) );
		add_action( 'wp_ajax_invitation_update_token', array( $this, 'update_token' ) );

		$this->load_plugin_textdomain();// Handle localisation.
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
		add_filter(
			'is_self_registration_enabled',
			function() {
				return $this->is_self_registration_enabled();
			}
		);
	}

	/**
	 * Add an option field to the spaces "defaultspace"-theme.
	 *
	 * @param array $settings_items array of all sidebar setting li's.
	 */
	public function add_settings_item( $settings_items ) {
		if ( $this->can_change_invitation_options() ) {
			$is_self_registration_enabled = apply_filters( 'is_self_registration_enabled', false );
			$is_private_or_community      = $this->blog_is_private_or_community();
			$link_enabled                 = $this->is_invitation_link_enabled();
			$link                         = $this->get_full_invitation_link();
			$toggle_button_class          = $is_self_registration_enabled ? 'no-toggle' : '';
			$link_enabled_class           = $link_enabled ? '' : 'link-disabled';
			$default_role                 = get_option( 'default_role' );

			$item = array(
				'id'   => 'invitation-item',
				'html' => $this->render(
					'settings',
					array(
						'link'                => $link,
						'link_enabled'        => $link_enabled,
						'toggle_button_class' => $toggle_button_class,
						'default_role'        => $default_role,
						'link_enabled_class'  => $link_enabled_class,
					)
				),
			);
			array_splice( $settings_items, count( $settings_items ) - 1, 0, array( $item ) );

			$self_registration_item = $this->self_registration_build_item();
			array_splice( $settings_items, count( $settings_items ) - 1, 0, array( $self_registration_item ) );
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
	public function invalid_invitation_link() {
		if ( $this->get->get( 'src' ) === 'invitation' ) {
			return esc_html__( 'The password or invitation-link you used is not (or no longer) valid.' );
		}
	}

	/**
	 * Triggered by 'wp_loaded'.
	 * Check if the invitation_link link is present and valid.
	 */
	public function maybe_add_user_and_redirect() {
		$current_url = $this->get_current_url();

		if ( $this->get->get( 'invitation' ) === 'success' ) {
			add_filter( 'spaces_invitation_notices', $this->callout( 'You successfully joined this space' ) );
			return;
		}

		if ( $this->get->get( 'leave_space' ) === 'true' && $current_url->equals( get_home_url() ) ) {
			remove_user_from_blog( get_current_user_id() );
			header( 'Location: ' . get_home_url() );
			exit;
		}

		if ( $this->is_invitation_link_enabled() ) {
			$this->handle_invitation_link( $current_url );
		}
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
			$this->invite_link = get_option( 'invitation_link' );
			if ( ! $this->invite_link ) {
				update_option( 'invitation_link', sha1( uniqid() ) );
				$this->invite_link = get_option( 'invitation_link' );
			}
		}

		return $this->invite_link;
	}

	/**
	 * Returns the full link for the invitation link.
	 */
	public function get_full_invitation_link() {
		return get_home_url() . '?invitation_link=' . $this->get_invitation_link();
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
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend.js', array( 'jquery' ), $this->_version, true );
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
					'true'  => 'Invitation Link and Password enabled',
					'false' => 'Invitation Link and Password disabled',
				),
				'self_registration' => array(
					'true'  => 'Self Registration enabled',
					'false' => 'Self Registration disabled',
				),
			)
		);
		wp_localize_script(
			$this->_token . '-frontend',
			'INVITATION_NONCES',
			array(
				'invitation_link'         => wp_create_nonce( 'invitation_link' ),
				'self_registration'       => wp_create_nonce( 'self_registration' ),
				'invitation_update_token' => wp_create_nonce( 'invitation_update_token' ),
			)
		);
		wp_localize_script(
			$this->_token . '-frontend',
			'INVITATION_TOKEN',
			array(
				'token' => $this->get_invitation_link(),
			)
		);
	} // End enqueue_scripts ()

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
	 *
	 * @todo add nonces.
	 * The function never returns.
	 */
	public function ajax_toggle_invitation_link() {
		check_ajax_referer( 'invitation_link' );
		if ( ! $this->can_change_invitation_options() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'spaces-invitation' ) ) );
		}
		if ( ! isset( $_POST['activate'] ) ) {
			wp_send_json_error( array( 'message' => 'Specify a value for activate' ) );
		}
		$this->update_boolean_option_respond_json( 'invitation_link_active', ( 'true' === $_POST['activate'] ) );
	}

	/**
	 * Checks if users can become an author in the current space.
	 * Don't call this function directly but with the filter: apply_filters( 'is_self_registration_enabled', false ).
	 *
	 * @return bool return true if the current space is open and users can join on their own
	 */
	public function is_self_registration_enabled() {
		// return false;
		return get_option( 'self_registration', false );
	}

	/**
	 * This function is called when the ajax call for 'self_registration' is called.
	 * The function never returns.
	 */
	public function ajax_toggle_self_registration() {
		check_ajax_referer( 'self_registration' );
		if ( $this->can_change_self_registration() ) {
			$this->update_boolean_option_respond_json( 'self_registration', ( 'true' === $this->post->get( 'activate' ) ) );
		} else {
			wp_send_json_error( array( 'message' => 'You are not allowed to do that.' ) );
		}
	}

	 /**
	  * Return fields for templating password form fields (frontend & backend)
	  *
	  * @return array[]
	  */
	private function get_password_form_data() {
		return array(
			'class' => '',
			'error' => $this->invalid_invitation_link(),
			'home_url' => get_home_url(),
			'message'  => esc_html__( 'Join this space with a password', 'spaces-invitation' ),
			'placeholder' => esc_attr__( 'Password', 'spaces-invitation' ),
			'button_text' => esc_html__( 'Join', 'spaces-invitation' ),
		);
	}

	/**
	 * Adds a password-form to the frontend-view.
	 *
	 * @param string $message
	 * @return void
	 */
	public function add_password_form_frontend( string $message ) {
		$form_data = $this->get_password_form_data();
		error_log( 'froootnet!' );
		return $message . $this->render( 'password_form_frontend', $form_data );
	}

	/**
	 * Add a form to the backend-view
	 *
	 * @param array $message The filter parameter of more_privacy_custom_login_form.
	 *
	 * @return array
	 */
	public function add_password_form_backend( array $message ) {
		$form_data = $this->get_password_form_data();
		if ( ! empty( $form_data['error'] ) ) {
			$form_data['error'] = "<div class='message error' id='login_error'>{$form_data['error']}</div>";
			$form_data['class'] = 'shake';
		}
		$form = $this->render( 'password_form_backend', $form_data );
		array_splice( $message, 1, 0, $form );
		return $message;
	}

	/**
	 * Renders the view $template with $variables.
	 * In the view the variables can be accessed with {{ variable_name }}.
	 * The view is taken from the view/ folder and a .html sufix is appended.
	 *
	 * @param string $template The template name, .html suffix is added and the file is searching the folder views/.
	 * @param array  $variables Variables with key values are given to the template.
	 *
	 * @reutrn string
	 */
	private function render( $template, array $variables ) {
		$keys = array_map(
			function( $key ) {
				return '/{{ *' . preg_quote( $key, '/' ) . ' *}}/';
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
		return current_user_can( 'promote_users' );
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

		return $this->blog_is_private() || ( self::COMMUNITY === $public );
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
	 *
	 *  Check if the user already has all capabilities of the default_role (specified in wp_options)
	 *
	 * The current user role might have more capabilities than the default role.
	 * So it doesn't make sense to add the default role.
	 * (WP supports having multiple roles only from a code-perspekive, but doesn't have interfaces).
	 *
	 * @return bool return true if the user already has all the capabilities of the default role. false for superadmin.
	 */
	private function user_has_all_caps_of_default_role() {
		if ( ! is_user_member_of_blog() ) {
			return false;
		}
		$role = get_role( get_option( 'default_role' ) );
		if ( is_super_admin() ) {
			return false;
		}
		$role_caps = array_keys( array_filter( $role->capabilities, 'boolval' ) );
		foreach ( $role_caps as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				error_log( "does not have cap $cap" );
				return false;
			}
		}
		return true;
	}

	/**
	 * Updates the current_user's role if the $default_role is a upgrade (compared to the current role [if she has one]).
	 */
	private function update_role_if_needed() {
		$default_role_name = get_option( 'default_role' );
		if ( ! $this->user_has_all_caps_of_default_role() ) {
			add_user_to_blog( get_current_blog_id(), get_current_user_id(), $default_role_name );
		}
	}


	/**
	 * Creates a clousure that can be used to render a simple callout.
	 *
	 * @param string $message The message to be rendered.
	 * @param string $type The class name of the callout.
	 */
	private function callout( $message, string $type = 'success' ) {
		return function() use ( $message, $type ) {
			return $this->render(
				'callout',
				array(
					'message' => esc_html( $message ),
					'type'    => $type,
				)
			);
		};
	}

	/**
	 * Creates an settings_item for add_settings_item.
	 *
	 * @return array
	 */
	private function self_registration_build_item() {
		$enabled        = apply_filters( 'is_self_registration_enabled', false );
		$enabled_stirng = esc_html( __( $enabled ? 'enabled' : 'disabled' ) );

		return array(
			'id'   => 'self-registration-item',
			'html' => $this->render(
				'self_registration',
				array(
					'text'                   => $enabled_stirng,
					'enabled'                => $enabled,
					'is_private_class'       => $this->blog_is_private() ? 'private' : '',
					'self_registration_text' => esc_html( __( 'Self-registration can not be enabled in private spaces.' ) ),
				)
			),
		);
	}

	/**
	 * Returns if the current blog is a private blog.
	 *
	 * @return bool
	 */
	private function blog_is_private() {
		return self::PRIVATE === (int) get_option( 'blog_public' );
	}

	/**
	 *
	 * Checks for self_registration and adds the user to the blog or adds a button to join / leave the space resprectively.
	 */
	private function handle_self_registration() {
		if ( ! is_user_logged_in()
			|| $this->blog_is_private() // there is no self-registration in private blogs.
			|| $this->user_has_all_caps_of_default_role() // the user already has all the capabilies of role that would be added.
		) {
			return;
		}

		if ( $this->get->get( 'join' ) ) {
			add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option( 'default_role' ) );
			header( 'Location: ' . get_home_url() . '?invitation=success' );
			exit;
		}

		add_filter( 'spaces_invitation_notices', array( $this, 'filter_join_this_space_notice' ) );
	}

	/**
	 * Filter for spaces_invitation_notices.
	 *
	 * @param string $message The filter argument from spaces_invitation_notices.
	 */
	public function filter_join_this_space_notice( $message ) {
		$change_url = add_query_arg( 'join', 'true', ds_get_current_url() );

		/**
		 * Superadmins don't get a "join this space" - button.
		 */
		if ( is_super_admin() ) {
			if ( ! is_user_member_of_blog() ) {
				$callout_message = esc_html__( 'You are currently logged in as a super-admin. Please use a regular account to collaborate.', 'defaultspace' );
				$callout         = $this->render(
					'callout',
					array(
						'message' => $callout_message,
						'type'    => 'warning',
					)
				);
				return $message . $callout;
			}
			return; // superadmin is already a member.
		}

		/**
		 * @todo: add title
		 */
		$join_button = $this->render(
			'join',
			array(
				'label' => esc_html__( 'Join this space' ),
				'title' => esc_html__(
					'You become an author in this space and can write posts. You can leave the space again anytime you want.',
					'defaultspace'
				),
				'url'   => $this->get_full_invitation_link(),
			)
		);
		return $message . $join_button;
	}

	/**
	 * Called by the wp_ajax_invitation_update_token action.
	 */
	public function update_token() {
		check_ajax_referer( 'invitation_update_token' );
		if ( ! $this->can_change_invitation_options() ) {
			wp_send_json_error( array( 'message' => 'You are not allowed to do this' ) );
			return;
		} elseif ( ! $this->post->has( 'token' ) ) {
			wp_send_json_error( array( 'message' => 'Token is missing' ) );
			return;
		}

		$token = esc_html( $this->post->get( 'token' ) );
		update_option( 'invitation_link', $token );

		wp_send_json( array( 'link' => get_home_url() . '?invitation_link=' . $token ) );
	}

	/**
	 * Called by add_settings_item to add the leave space button if neccessary.
	 */
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

	/**
	 * Return a comparator for the current url.
	 */
	private function get_current_url() {
		$server = new Spaces_Invitation_Request( $_SERVER );

		return new Spaces_Invitation_Comparable( trim( $server->get( 'WP_HOME' ) . strtok( $server->get( 'REQUEST_URI' ), '?' ), '/' ) );
	}

	/**
	 * Returns if the invitation link is active.
	 */
	private function is_invitation_link_enabled() {
		return get_option( 'invitation_link_active' );
	}

	/**
	 * Checks if the current_user is trying to add himself to the current space.
	 *
	 * @param Spaces_Invitation_Comparable $current_url Comparator for the current url.
	 */
	private function is_trying_to_register( Spaces_Invitation_Comparable $current_url ) {
		return $this->get->has( 'invitation_link' )
			&& $current_url->equals( get_home_url() )
			&& is_user_logged_in()
			&& ! is_super_admin();
	}

	/**
	 * Adds a form to enter the invitation token for the appropriate places.
	 *
	 * @param Spaces_Invitation_Comparable $current_url The current url of the request.
	 */
	private function add_invitation_form( Spaces_Invitation_Comparable $current_url ) {
		if ( $current_url->equals( wp_login_url() ) ) {
			add_filter( 'more_privacy_custom_login_form', array( $this, 'add_password_form_backend' ) );
		}
		add_filter( 'spaces_invitation_notices', array( $this, 'add_password_form_frontend' ) );
	}

	/**
	 * Try to register the current user to the current space.
	 */
	private function try_to_register() {
		if ( get_option( 'invitation_link' ) !== $this->get->get( 'invitation_link' ) ) { // queryvar matches blog setting.
			header( 'Location: ' . get_home_url() . '/wp-login.php?action=privacy&src=invitation' );
			exit;
		}

		$get_parameters = '';

		if ( is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) ) {
			$this->update_role_if_needed();
		} else {
			add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option( 'default_role' ) );
			$get_parameters = '?invitation=success';
		}

		header( 'Location: ' . get_home_url() . $get_parameters );
		exit;
	}

	/**
	 * Checks if the user is trying to register and adds a form if not.
	 *
	 * @param Spaces_Invitation_Comparable $current_url The current url of the request.
	 */
	private function handle_invitation_link( Spaces_Invitation_Comparable $current_url ) {
		// var_dump($this->is_trying_to_register( $current_url ), ! is_user_member_of_blog(), apply_filters( 'is_self_registration_enabled', false ));exit;
		if ( $this->is_trying_to_register( $current_url ) ) {
			$this->try_to_register();
		} elseif ( ! is_user_member_of_blog() ) {
			if ( apply_filters( 'is_self_registration_enabled', false ) ) {
				add_filter( 'spaces_invitation_notices', array( $this, 'filter_join_this_space_notice' ) );
				return;
			} else {
				$this->add_invitation_form( $current_url );
			}
		}
	}

	/**
	 * Updates the given option name with the given bool value and sends a json responds with either a success or failure.
	 *
	 * @param string $option_name The option to be updated.
	 * @param bool   $bool_value The value the option should become.
	 */
	private function update_boolean_option_respond_json( string $option_name, bool $bool_value ) {
		$updated = update_option( $option_name, (string) $bool_value );
		if ( $updated ) {
			$boolstring = $bool_value ? 'true' : 'false';
			wp_send_json( array( 'message' => 'The option "' . $option_name . '" is now ' . $boolstring ) );
		} else {
			wp_send_json_error( array( 'message' => 'The option "' . $option_name . '" was not updated.' ) );
		}
	}
}
