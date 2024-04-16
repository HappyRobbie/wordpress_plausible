<?php
/**
 * Plausible Analytics | Admin Actions.
 *
 * @since      2.0.6
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP\Admin;

/**
 * This class provides an alternative to the JS/Client approach to display error/notice messages in the Admin interface.
 */
class Messages {
	const ERROR_TRANSIENT      = 'plausible_analytics_error';

	const NOTICE_TRANSIENT     = 'plausible_analytics_notice';

	const SUCCESS_TRANSIENT    = 'plausible_analytics_success';

	const ADDITIONAL_TRANSIENT = 'plausible_analytics_additional';

	/**
	 * Sets a success message.
	 *
	 * @param $message
	 * @param $expiration
	 *
	 * @return void
	 */
	public static function set_success( $message, $expiration = 5 ) {
		set_transient( self::SUCCESS_TRANSIENT, $message, $expiration );
	}

	/**
	 * Sets an error.
	 *
	 * @param $message
	 * @param $expiration
	 *
	 * @return void
	 */
	public static function set_error( $message, $expiration = 5 ) {
		set_transient( self::ERROR_TRANSIENT, $message, $expiration );
	}

	/**
	 * Sets a notice.
	 *
	 * @param $message
	 * @param $expiration
	 *
	 * @return void
	 */
	public static function set_notice( $message, $expiration = 5 ) {
		set_transient( self::NOTICE_TRANSIENT, $message, $expiration );
	}

	/**
	 * Sets an additional message.
	 *
	 * @param string $message    The message to be displayed.
	 * @param string $id         ID of the option where this additional message should be displayed.
	 * @param int    $expiration Expiration in seconds.
	 *
	 * @return void
	 */
	public static function set_additional( $message, $id, $expiration = 5 ) {
		set_transient( self::ADDITIONAL_TRANSIENT, [ $id => $message ], $expiration );
	}
}
