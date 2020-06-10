<?php
/**
 * Request class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requst class class.
 */
class Spaces_Invitation_Request {

	/**
	 * Array to be used aas data source.
	 *
	 * @var array
	 */
	private $array;

	/**
	 * Constructor.
	 *
	 * @param array $array Array that should be used as source.
	 */
	public function __construct( array $array ) {
		$this->array = $array;
	}

	/**
	 * Checks if the given key exists.
	 *
	 * @param string $key Key for the array.
	 *
	 * @return bool
	 */
	public function has( string $key ) {
		return isset( $this->array[ $key ] );
	}

	/**
	 * Returns the corresponding value of the given key or the default value.
	 *
	 * @param string $key Key for the array.
	 * @param mixed  $default_value Value to be used if the key does not exist.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default_value = false ) {
		return $this->has( $key ) ? $this->array[ $key ] : $default_value;
	}
}
