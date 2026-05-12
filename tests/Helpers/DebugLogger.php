<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Symfony\Component\Filesystem\Path;

class DebugLogger {

	private bool $enabled;
	private string $logFile;

	public function __construct( ?bool $enabled = null ) {
		$this->enabled = $enabled ?? TestEnv::isVerbose();
		$this->logFile = Path::join( __DIR__, '../../debug.log' );
	}

	public function log( string $message, string $level = 'INFO' ) :void {
		if ( !$this->enabled ) {
			return;
		}

		$timestamp = date( 'Y-m-d H:i:s' );
		$formattedMessage = sprintf( "[%s] [%s] %s\n", $timestamp, $level, $message );

		file_put_contents( $this->logFile, $formattedMessage, FILE_APPEND | LOCK_EX );
	}

	public function debug( string $message ) :void {
		$this->log( $message, 'DEBUG' );
	}

	public function info( string $message ) :void {
		$this->log( $message, 'INFO' );
	}

	public function warning( string $message ) :void {
		$this->log( $message, 'WARNING' );
	}

	public function error( string $message ) :void {
		$this->log( $message, 'ERROR' );
	}

	public function clear() :void {
		if ( file_exists( $this->logFile ) ) {
			unlink( $this->logFile );
		}
	}
}
