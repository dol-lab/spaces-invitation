<?php
/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Includes
 *
 * @see https://github.com/hlashbrooke/WordPress-Plugin-Template/blob/c9dc251ee84583d904d84b5179243e3e7c5fa22c/wordpress-plugin-template.php
 */

/**
 * Comparable class file.
 */
class Spaces_Invitation_Settings {
	/**
	 * The single instance of WordPress_Plugin_Template_Settings.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 's_invitation_';

		add_filter( 'wpmu_blogs_columns', array( $this, 'my_custom_blog_columns' ) );
		add_action( 'manage_sites_custom_column', array( $this, 'manage_sites_custom_column' ), 10, 3 );



	}

	public function my_custom_blog_columns( $sites_columns ) {
		// Modify $site_columns here....
		$sites_columns['self_registration'] = esc_html__( 'Self Registration', 'spaces-invitation' );
		return $sites_columns;
	}

	/**
	 * Triggered by the action "manage_sites_custom_column".
	 *
	 * @param string $column_name -.
	 * @param int    $blog_id -.
	 * @return void
	 */
	public function manage_sites_custom_column( $column_name, $blog_id ) {
		if ( 'self_registration' !== $column_name ) {
			return;
		}
		switch_to_blog( $blog_id );
		$enabled = get_option( 'self_registration' );
		$desc = $enabled ? array(
			'icon' => '✅',
			'title' => 'enabled',
		) : array(
			'icon' => '❌',
			'title' => 'disabled',
		);
		restore_current_blog();
		echo "<span title='{$desc['title']}'>{$desc['icon']}</span>";

	}

	/**
	 * Main WordPress_Plugin_Template_Settings Instance
	 *
	 * Ensures only one instance of WordPress_Plugin_Template_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WordPress_Plugin_Template()
	 * @param object $parent Object instance.
	 * @return object WordPress_Plugin_Template_Settings instance
	 */
	public static function instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of WordPress_Plugin_Template_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of WordPress_Plugin_Template_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
