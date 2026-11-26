<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

/**
 * Captures what PHP's error_log() writes during a test, by pointing the error_log setting at a temporary file.
 * It reads what was really written, so no function is stubbed. PHP's own error reporting is kept out of the file.
 */
final class ErrorLogCapture {

	use TempDirLifecycleTrait;

	/**
	 * The prefix PHP writes before each entry in an error log file, such as "[26-Nov-2026 08:44:28 UTC] ".
	 */
	private const ENTRY_PREFIX = '#^\[\d{2}-[A-Z][a-z]{2}-\d{4} \d{2}:\d{2}:\d{2} [^\]\r\n]+\] #m';

	private string $file;

	private string $previousDestination;

	private string $previousLogErrors;

	private function __construct() {
	}

	public static function start() :self {
		$capture = new self();
		$capture->file = $capture->createTrackedTempFile( 'shield-error-log-' );
		$capture->previousDestination = (string)\ini_get( 'error_log' );
		$capture->previousLogErrors = (string)\ini_get( 'log_errors' );
		\ini_set( 'error_log', $capture->file );
		\ini_set( 'log_errors', '0' );
		return $capture;
	}

	/**
	 * @return list<string> each message written, in order, without the timestamp and line ending PHP adds
	 */
	public function messages() :array {
		$entries = \preg_split( self::ENTRY_PREFIX, (string)\file_get_contents( $this->file ), -1, \PREG_SPLIT_NO_EMPTY );
		return \array_values( \array_map(
			static fn( string $entry ) :string => (string)\preg_replace( '#\r?\n\z#', '', $entry ),
			\is_array( $entries ) ? $entries : []
		) );
	}

	/**
	 * Forgets what's been captured so far, for a test that checks only what a later step writes.
	 */
	public function clear() :void {
		\file_put_contents( $this->file, '' );
	}

	public function contains( string $needle ) :bool {
		foreach ( $this->messages() as $message ) {
			if ( \strpos( $message, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public function stop() :void {
		\ini_set( 'error_log', $this->previousDestination );
		\ini_set( 'log_errors', $this->previousLogErrors );
		$this->cleanupTrackedTempDirs();
	}
}
