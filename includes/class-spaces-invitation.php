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
	 * Undocumented variable
	 *
	 * @var Space_Invite_Link
	 */
	public $invite_link;

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

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions.
		// if ( is_admin() ) {
		// 	$this->admin = new Spaces_Invitation_Admin_API();
		// }

        add_action( 'init', array( $this, 'init' ) );

        add_action( 'wp_loaded', function() {
            $current_url = trim( $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?') < 0 ? strlen($_SERVER['REQUEST_URI']) : strpos($_SERVER['REQUEST_URI'], '?')), '/' );
            if( is_user_logged_in() && get_home_url() === $current_url && get_blog_option( null, 'invitation_link' ) === $_GET['invitation_link'] && !!get_blog_option( null, 'invitation_link_active' ) )
            {
                if( !is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) )
                {
                    add_user_to_blog( get_current_blog_id(), get_current_user_id(), get_option('default_role') );
                }
                header( 'Location: ' . get_home_url() );
                exit;
            }

        } );
        add_action( 'wp_ajax_invitation_link', array( $this, 'on_ajax_call' ) );
        add_action( 'wp_ajax_nopriv_invitation_link', array( $this, 'on_ajax_call' ) );

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	} // End __construct ()

	/**
	 * Register post type function.
	 *
	 * @param string $post_type Post Type.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param string $description Description.
	 * @param array  $options Options array.
	 *
	 * @return bool|string|Spaces_Invitation_Post_Type
	 */
	public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) {
			return false;
		}

		$post_type = new Spaces_Invitation_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param array  $post_types Post types to register this taxonomy for.
	 * @param array  $taxonomy_args Taxonomy arguments.
	 *
	 * @return bool|string|Spaces_Invitation_Taxonomy
	 */
	public function register_taxonomy( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) {
			return false;
		}

		$taxonomy = new Spaces_Invitation_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
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
	} // End enqueue_scripts ()

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
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
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );
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
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

    public function init() {
        if( $this->is_allowed() )
        {
            add_filter( 'invitation_link_setting', function() {
                $this->generate_link_when_needed();
                $is_private_or_community = $this->blog_is_private_or_community();
                add_blog_option( null, 'invitation_link_active', (string)!$is_private_or_community );
                $link = get_home_url() . '?invitation_link=' . get_blog_option( null, 'invitation_link' );
                $link_enabled = get_blog_option( null, 'invitation_link_active' );
                $toggle_button_class = $is_private_or_community ? '' : 'disabled';

                return array(
                    'id' => 'invitation-item',
                    'html' => $this->render( 'settings', array(
                        'link' => $link,
                        'link_enabled' => $link_enabled,
                        'toggle_button_class' => $toggle_button_class
                    ) )
                );
            });
        }
    }

    private function render( $template, $variables ) {
        $keys = array_map(function( $key ) {
            return '/{{ *' . preg_quote( $key ) . ' *}}/';
        }, array_keys( $variables ) );
        return preg_replace(
            $keys,
            array_values( $variables ),
            file_get_contents( __DIR__ . '/views/' . $template . '.html' )
        );
    }

    private function generate_link_when_needed() {
        add_blog_option( null, 'invitation_link', sha1( uniqid() ) );
    }

    public function check_ajax_call() {
        if(wp_doing_ajax()) {
            $actions = array(
                'invitation'
            );

            if( 'invitation_link' === $_POST['action'] ) {
                $this->on_ajax_call();
            }
        }
    }

    public function on_ajax_call()
    {
        if( $this->is_allowed() )
        {
            update_blog_option(null, 'invitation_link_active', (string)($_POST['activate'] === 'true'));
            wp_die();
        }

        echo 'you are not allowed to do that'.

        wp_die();
    }

    private function get_current_blogs_public_value()
    {
        $result = $this->db->get_row( $this->db->prepare( 'select public from wp_blogs where blog_id = %d', (int)get_current_blog_id() ) , ARRAY_A )['public'];

        return $result !== null ? (int)$result : null;
    }

    private function is_allowed()
    {
        $public = $this->get_current_blogs_public_value();

        return null !== $public && (-2 !== $public || current_user_can( 'promote_users' )); // -2 === private space
    }

    private function blog_is_private_or_community()
    {
        $public = $this->get_current_blogs_public_value();

        return in_array($public, [-2, -1]);
    }
}
