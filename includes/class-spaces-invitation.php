<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 *
 * @todo
 * - the plugin name is not really god. this is not only about invitation, but access in general.
 * - be more specific. get_invitation_link and the option should have a different name: "access_secret"?
 * - the while invitation_link vs. access code is still confusing.
 * - there should (probably) be nothing about privacy here
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
	 * Wrapper class for the current GET variables
	 *
	 * @var Spaces_Invitation_Request
	 */
	private $req_get;

	/**
	 * Wrapper class for the current POST variables
	 *
	 * @var Spaces_Invitation_Request
	 */
	private $req_post;

	/**
	 * @var Spaces_Invitation_Comparable
	 */
	private $current_url_compare;

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->req_get  = new Spaces_Invitation_Request( $_GET );
		$this->req_post = new Spaces_Invitation_Request( $_POST );

		$this->current_url_compare = $this->get_current_url_comparable();

		$this->_version = $version;
		$this->_token   = 'Spaces_Invitation';

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

		add_action( 'wp_loaded', array( $this, 'route' ) );
		add_action( 'wp_loaded', array( $this, 'add_notifications' ) );

		add_action( 'wp_ajax_change_invitation_option', array( $this, 'ajax_change_invitation_option' ) );
		add_action( 'wp_ajax_nopriv_change_invitation_option', array( $this, 'ajax_change_invitation_option' ) );
		add_action( 'wp_ajax_invitation_update_token', array( $this, 'update_token' ) );
		add_action( 'update_option_blog_public', array( $this, 'on_update_blog_public' ), 10, 2 );
		add_action( 'wp_ajax_get_disabled_options', array( $this, 'ajax_get_disabled_options' ) );
		add_action( 'wp_ajax_nopriv_get_disabled_options', array( $this, 'ajax_get_disabled_options' ) );

		$this->load_plugin_textdomain();// Handle localisation.
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		/**
		 * You can access this property in your theme by using
		 * apply_filter( 'is_self_registration_enabled', true )
		 *
		 * Adding this filter assigns the proper default via. 'self_reg_enabled'.
		 */
		add_filter(
			'is_self_registration_enabled',
			function() {
				return $this->self_reg_enabled( false );
			}
		);
	}

	/**
	 * Use this function to load plugin options, as options have mutual dependencies.
	 *
	 * @param string $option_name
	 * @return mixed The value of the option.
	 */
	private function get_plugin_option( $option_name ) {
		$opts = array(
			'invitation_link' => $this->get_invitation_link(),
			'invitation_link_active' => filter_var( get_option( 'invitation_link_active', true ), FILTER_VALIDATE_BOOLEAN ),
			'self_registration' => $this->self_reg_enabled(),
		);

		if ( ! array_key_exists( $option_name, $opts ) ) {
			wp_die( "Spaces Invitation: The option '$option_name' was not found" );
		}

		/**
		 * The 'self_registration'-option is dominant, it changes the other options, if it's true.
		 */
		if ( $opts['self_registration'] ) {
			$opts['invitation_link'] = 'welcome'; // don't expose a previously set user password.
			$opts['invitation_link_active'] = true; // the link is active, if self_registration is enabled.
		}
		return $opts[ $option_name ];

	}

	/**
	 * Don't use this function. Use get_option('self_registration', true) instead.
	 *
	 * @param bool $filter wether the filter is_self_registration_enabled is applied.
	 * @return bool
	 */
	private function self_reg_enabled( $filter = true ) {
		$is_enabled = filter_var( get_option( 'self_registration', true ), FILTER_VALIDATE_BOOLEAN );
		return $filter ? apply_filters( 'is_self_registration_enabled', $is_enabled ) : $is_enabled;

	}

	/**
	 * Translate a WordPress default role like "author".
	 *
	 * @todo: gendering role-names?
	 */
	public function translate_role( $name ) {
		/**
		 * @var \WP_Roles $roles
		 */
		$roles = wp_roles();
		return $roles->is_role( $name ) ? translate_user_role( $roles->get_names()[ $name ] ) : $name;
	}

	/**
	 * Add an option field to the spaces "defaultspace"-theme.
	 *
	 * @param array $settings_items array of all sidebar setting li's.
	 */
	public function add_settings_item( $settings_items ) {
		if ( $this->can_change_invitation_options() ) {
			$active = $this->get_active_option();
			$title = esc_html__( 'Space User Access', 'spaces-invitation' );
			$settings_items[] = array(
				'id' => 'invitation-settings',
				'html' => '<a><i class="fa fa-link"></i><span>' . $title . '</span></a>',
				'children' => array(
					array(
						'id' => 'invitation-item',
						'html' => $this->create_settings_option(
							'invitation-status',
							esc_html__( 'Deactivated', 'spaces-invitation' ),
							$active,
							'none',
							'times'
						),
						'class' => 'success radio-accordion radio-accordion-item',
					),
					array(
						'id' => 'invitation-item2',
						'html' => $this->create_invitation_link_settings_option( $active ),
						'class' => 'success radio-accordion radio-accordion-item',
					),
					array(
						'id' => 'invitation-item3',
						'html' => $this->create_self_registration_setting_option( $active ),
						'class' => 'success radio-accordion radio-accordion-item',
					),
				),
			);
			return $settings_items;
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
		if ( $this->req_get->get( 'invitation' ) === 'failed' ) {
			return $this->get_invalid_invitation_link_message();
		}
	}

	private function get_invalid_invitation_link_message() {
		return esc_html__( 'The access code or invitation-link you used is not (or no longer) valid.' );
	}

	/**
	 * Triggered by 'wp_loaded'.
	 */
	public function add_notifications() {
		if ( $this->req_get->get( 'invitation' ) === 'success' ) {
			$this->add_callout( esc_html__( 'Welcome! You successfully joined this Space.', 'spaces-invitation' ) );
		} elseif ( $this->req_get->get( 'invitation' ) === 'failed' ) {
			$this->add_callout( $this->get_invalid_invitation_link_message(), 'alert' );
		} elseif ( $this->req_get->get( 'leave_space' ) === 'success' ) {
			$this->add_callout( esc_html__( 'You have left this Space.', 'spaces-invitation' ) );
		}
	}

	/**
	 * Triggered by 'wp_loaded'.
	 * Check if the invitation_link link is present and valid.
	 */
	public function route() {
		$this->maybe_leave_space();
		$this->handle_invitation_link( $this->current_url_compare );
	}

	/**
	 * If the users is not the last who can 'promote users' she is removed from a blog.
	 * Otherwise a warning is
	 */
	public function maybe_leave_space() {
		if ( $this->req_get->get( 'leave_space' ) !== 'true'
			|| ! $this->current_url_compare->equals( get_home_url() ) ) {
				return;
		}
		/**
		 * The capability 'promote_users' can do the following:
		 * - Enables the ‘Add Existing User’ to function for multi-site installs.
		 * - Enables the “Change role to…” dropdown in the admin user list
		 */
		$cap = 'promote_users';
		$admins = $this->get_users_by_capability( $cap );
		$is_admin = current_user_can( $cap );

		/**
		 * If there is only one admin left and the current user is an admin.
		 */
		if ( count( $admins ) < 2 && $is_admin ) {
			$msg = esc_html__(
				"You can't leave this Space because you are the last member who can manage users.
Please add somebody or delete this Space.",
				'spaces-invitation'
			);
			$this->add_callout( $msg, 'alert' );
		} else {
			remove_user_from_blog( get_current_user_id() );
			if ( wp_redirect( add_query_arg( 'leave_space', 'success', get_home_url() ) ) ) {
				exit;
			}
		}
	}

	/**
	 * Get all users with a capability in the current blog.
	 *
	 * @param string $cap_name The name of the capability like 'publish posts'.
	 * @return int[] an array of user ids. Might be empty.
	 */
	private function get_users_by_capability( $cap_name ) {
		$role__in = array();
		foreach ( wp_roles()->roles as $role_slug => $role ) {
			$role_cap = $role['capabilities'];
			if ( isset( $role_cap[ $cap_name ] ) && ! empty( $role_cap[ $cap_name ] ) ) {
				$role__in[] = $role_slug;
			}
		}
		$users = array();
		if ( $role__in ) {
			$users = get_users(
				array(
					'role__in' => $role__in,
					'fields' => 'ids',
				)
			);
		}
		return $users;
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
	 * @todo: each wp_localize_script creates a new <script> in dom. this could be a single object.
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
					'true'  => esc_html__( 'Invitation Link and Access Code enabled', 'spaces-invitation' ),
					'false' => esc_html__( 'Invitation Link and Access Code disabled', 'spaces-invitation' ),
				),
				'self_registration' => array(
					'true'  => esc_html__( 'Self Registration enabled', 'spaces-invitation' ),
					'false' => esc_html__( 'Self Registration disabled', 'spaces-invitation' ),
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
				'get_disabled_options'    => wp_create_nonce( 'get_disabled_options' ),
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
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Cloning of Spaces_Invitation is forbidden' ), esc_attr( $this->_version ) );

	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Unserializing instances of Spaces_Invitation is forbidden' ), esc_attr( $this->_version ) );
	}

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 */
	public function install() {
		$this->_log_version_number();
	}

	/**
	 * This function is called when the ajax call for 'invitation_link' is called.
	 *
	 * @todo add nonces.
	 * The function never returns.
	 */
	public function ajax_change_invitation_option() {
		check_ajax_referer( 'invitation_link' );
		if ( ! $this->can_change_invitation_options() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'spaces-invitation' ) ) );
		}
		if ( ! isset( $_POST['option'] ) ) {
			wp_send_json_error( array( 'message' => 'Specify a value for option' ) );
		}
		$new_option = $_POST['option'];

		switch ( $new_option ) {
			case 'none':
				// deactivate all.
				update_option( 'invitation_link_active', -1 );
				update_option( 'self_registration', -1 ); // WP has issues with false, especially when default is not false...

				break;
			case 'self_registration':
				if ( ! $this->can_change_self_registration() ) {
					wp_send_json_error( array( 'message' => 'You are not allowed to do that.' ) );
					return;
				}
				update_option( 'invitation_link_active', true );
				update_option( 'self_registration', true );

				break;
			case 'invitation_link':
				update_option( 'invitation_link_active', true );
				update_option( 'self_registration', -1 );

				break;
			default:
				wp_send_json_error( array( 'message' => 'Invalid option given.' ) );
				return;
		}

		wp_send_json_success(
			array(
				'message' => 'Updated options to ' . $new_option,
				'data' => array(
					'option_name' => $new_option,
					'invitation_link_active' => get_option( 'invitation_link_active', true ),
					'self_registration' => get_option( 'self_registration', true ),
				),
			) 
		);
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
			'message'  => esc_html__( 'Join this Space with an Access Code', 'spaces-invitation' ),
			'placeholder' => esc_attr__( 'Access Code', 'spaces-invitation' ),
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
		if ( $this->superadmin_notice() ) {
			return $message . $this->superadmin_notice();
		}
		$form_data = $this->get_password_form_data();
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

	public function add_callout( $message, $type = 'success' ) {
		add_filter( 'spaces_invitation_notices', $this->render_callout( $message, $type ) );
	}

	/**
	 * Creates a clousure that can be used to render a simple callout.
	 *
	 * @param string $message The message to be rendered.
	 * @param string $type The class name of the callout ('success', 'primary', 'warning', 'alert')
	 */
	private function render_callout( $message, string $type = 'success' ) {
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
	 * Returns if the current blog is a private blog.
	 *
	 * @return bool
	 */
	private function blog_is_private() {
		return self::PRIVATE === (int) get_option( 'blog_public' );
	}

	/**
	 * Superadmins don't get a "join this space" - button or a access code field.
	 */
	public function superadmin_notice() {
		if ( is_super_admin() ) {
			if ( ! is_user_member_of_blog() ) {
				$callout_message = esc_html__(
					'You are currently logged in as a super-admin. Please use a regular account to collaborate.',
					'spaces-invitation'
				);
				return $this->render(
					'callout',
					array(
						'message' => $callout_message,
						'type'    => 'warning',
					)
				);

			}
			return; // superadmin is already a member.
		}
	}

	/**
	 * Filter for spaces_invitation_notices.
	 *
	 * @param string $message The filter argument from spaces_invitation_notices.
	 */
	public function filter_join_this_space_notice( $message ) {
		if ( $this->superadmin_notice() ) {
			return $message . $this->superadmin_notice();
		}
		/**
		 * @todo: add title
		 */
		$join_button = $this->render(
			'join',
			array(
				'label' => esc_html__( 'Join this space', 'spaces-invitation' ),
				'title' => esc_html__(
					'You become an Author in this Space and can write posts. You can leave the Space again anytime you want.',
					'spaces-invitation'
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
			wp_send_json_error( array( 'message' => 'You are not allowed to do this.' ) );
			return;
		} elseif ( ! $this->req_post->has( 'token' ) ) {
			wp_send_json_error( array( 'message' => 'Token is missing' ) );
			return;
		}

		$token = esc_html( $this->req_post->get( 'token' ) );
		update_option( 'invitation_link', $token );

		wp_send_json( array( 'link' => get_home_url() . '?invitation_link=' . $token ) );
	}

	/**
	 * @todo: this is something which should ony be in the theme (a special case which has a filter in theme).
	 */
	public function on_update_blog_public( $old_value, $new_value ) {
		$self_registration_enabled = filter_var( get_option( 'self_registration', true ), FILTER_VALIDATE_BOOLEAN ); // don't use $this->get_plugin_option since the blog_public is already private
		if ( $self_registration_enabled && self::PRIVATE === $new_value ) {
			update_option( 'self_registration', -1, true );
			update_option( 'invitation_link_active', -1, true );
		}
	}

	public function ajax_get_disabled_options() {
		check_ajax_referer( 'get_disabled_options' );
		if ( ! $this->can_change_invitation_options() ) {
			wp_send_json_error( array( 'message' => 'You are not allowed to do this.' ) );
			return;
		}
		wp_send_json(
			array(
				'disabled_options' => $this->get_disabled_options(),
				'active_option' => $this->get_active_option(),
			)
		);
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
				'order' => 15,
				'data' => array(
					'text' => __( 'Leave Space', 'spaces-invitation' ),
					'confirm' => __(
						'Are you sure you want to leave this Space? You will not be able to write posts or see private posts here anymore.',
						'spaces-invitation'
					),
					'url'  => get_home_url() . '?leave_space=true',
				),
				'html' => '
					<a
						href="{{ url }}"
						title="Click here to leave this Space."
						class="alert"
						onclick="return confirm(\'{{confirm}}\')"
					>
						<i class="fas fa-leaf"></i>{{ text }}
					</a>',
			),
		);
	}
	/**
	 * Return a comparator for the current url.
	 *
	 * @return Spaces_Invitation_Comparable
	 */
	private function get_current_url_comparable() {
		$server = new Spaces_Invitation_Request( $_SERVER );
		return new Spaces_Invitation_Comparable( trim( $server->get( 'WP_HOME' ) . strtok( $server->get( 'REQUEST_URI' ), '?' ), '/' ) );
	}

	/**
	 * Checks if the current_user is trying to add himself to the current space.
	 */
	private function is_trying_to_register() {
		return $this->req_get->has( 'invitation_link' )
			&& $this->current_url_compare->equals( get_home_url() )
			&& is_user_logged_in()
			&& ! is_super_admin();
	}

	/**
	 * Adds a form to enter the invitation token for the appropriate places.
	 */
	private function add_invitation_form() {
		if ( $this->current_url_compare->equals( wp_login_url() ) ) {
			add_filter( 'more_privacy_custom_login_form', array( $this, 'add_password_form_backend' ) );
		}
		add_filter( 'spaces_invitation_notices', array( $this, 'add_password_form_frontend' ) );
	}

	/**
	 * Try to register the current user to the current space.
	 */
	private function try_to_register() {
		if ( get_option( 'invitation_link' ) !== $this->req_get->get( 'invitation_link' ) ) { // queryvar matches blog setting.
			if ( $this->req_get->get( 'src' ) === 'login' ) {
				header( 'Location: ' . get_home_url() . '/wp-login.php?action=privacy&src=invitation&invitation=failed' );
				exit;
			}
			header( 'Location: ' . get_home_url() . '?invitation=failed' );
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
	 */
	private function handle_invitation_link() {
		if ( ! $this->get_plugin_option( 'invitation_link_active' ) ) {
			return;
		}
		// var_dump($this->is_trying_to_register( $current_url_compare ), ! is_user_member_of_blog(), apply_filters( 'is_self_registration_enabled', false ));exit;
		if ( $this->is_trying_to_register() ) {
			$this->try_to_register();
		} elseif ( ! is_user_member_of_blog() ) {
			if ( $this->get_plugin_option( 'self_registration' ) ) {
				add_filter( 'spaces_invitation_notices', array( $this, 'filter_join_this_space_notice' ) );
				return;
			} else {
				$this->add_invitation_form( $this->current_url_compare );
			}
		}
	}

	private function create_settings_option( string $input_name, string $text, string $active_value, string $value, string $icon ) {
		return $this->render(
			'settings_option',
			array(
				'id'        => 'input_' . $value,
				'input_name' => $input_name,
				'text'      => $text,
				'checked'   => ( $active_value === $value ? 'checked="checked"' : '' ),
				'value'     => $value,
				'disabled'  => false !== array_search( $value, $this->get_disabled_options(), true ) ? 'disabled' : '',
				'icon'      => $icon,
			)
		);
	}

	private function get_disabled_options() {
		return $this->blog_is_private() ? array( 'self_registration' ) : array();
	}

	private function get_active_option() {
		if ( $this->get_plugin_option( 'self_registration' ) ) {
			return 'self_registration';
		} else if ( $this->get_plugin_option( 'invitation_link_active' ) ) {
			return 'invitation_link';
		}

		return 'none';
	}

	private function create_invitation_link_settings_option( string $active_value ) {
		$value = 'invitation_link';
		$default_role = $this->translate_role( get_option( 'default_role' ) );
		$description = sprintf(
			esc_html__(
				'Users who click on the Invitation Link or enter the Space via Access Code will be added with the role "%s".
Changing the Access Code will change the Inivitation Link.',
				'spaces-invitation'
			),
			$default_role
		);

		$parameters = array(
			'link' => $this->get_full_invitation_link(),
			'copy_text' => esc_html__( 'Press Ctrl+C to copy.', 'spaces-invitation' ),
			'change_password_text' => esc_html__( 'Change Access Code', 'spaces-invitation' ),
			'style' => $active_value === $value ? 'display: block;' : '',
			'description' => $description,
		);

		return $this->create_settings_option( 'invitation-status', esc_html__( 'Invitation Link', 'spaces-invitation' ), $active_value, $value, 'key' )
			 . $this->render( 'invitation_link_input', $parameters );
	}

	private function create_self_registration_setting_option( string $active_value ) {
		$value = 'self_registration';
		$description = esc_html__( 'Self-registration can not be enabled in private Spaces.', 'spaces-invitation' );

		$option = $this->create_settings_option(
			'invitation-status',
			esc_html__( 'Self Registration', 'spaces-invitation' ),
			$active_value,
			$value,
			'sign-in-alt'
		);

		return $option . $this->render(
			'detail_description',
			array(
				'style'       => $active_value === $value ? 'display: block;' : '',
				'description' => $description,
			)
		);
	}
}
