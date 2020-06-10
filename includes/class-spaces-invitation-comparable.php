<?php
/**
 * Comparable class file.
 *
 * @package WordPress Plugin Template/Includes
 */

/**
 * Comparable class file.
 */
class Spaces_Invitation_Comparable {
	/**
	 * Value can be anything.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param mixed $value Inital value. This value cannot be accesed fronm within this class, it can only be compared by other values.
	 */
	public function __construct( $value ) {
		$this->value = $value;
	}


	/**
	 * Checks if the given value equals the privided inital value.
	 *
	 * @param mixed $value value to compare with the given inital value.
	 */
	public function equals( $value ) {
		return $value === $this->value;
	}
}
