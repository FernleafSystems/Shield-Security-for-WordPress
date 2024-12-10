<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CoolDownHandler {

	use PluginControllerConsumer;

	public const CONTEXT_AUTH = 'auth';
	public const CONTEXT_COMMENTS = 'comments';
	public const CONTEXT_SPAM = 'spam';

	private array $secondsSinceLastReq = [];

	public function isCooldownTriggered( string $context ) :bool {
		return $this->serviceAvailable()
			   && $this->isCooldownContextEnabled( $context )
			   && $this->cooldownRemaining( $context ) > 0;
	}

	public function cooldownRemaining( string $context ) :int {
		if ( !isset( $this->secondsSinceLastReq[ $context ] ) ) {
			if ( $this->serviceAvailable() ) {
				$FS = Services::WpFs();
				$file = $this->getFlagFilePath( $context );
				$this->secondsSinceLastReq[ $context ] = Services::Request()->ts()
														 - ( $FS->exists( $file ) ? $FS->getModifiedTime( $file ) : 0 );
				$this->touchFlag( $context );
			}
			else {
				$this->secondsSinceLastReq[ $context ] = \PHP_INT_MAX;
			}
		}
		return (int)\max( 0, $this->intervalPerContext( $context ) - $this->secondsSinceLastReq[ $context ] );
	}

	private function intervalPerContext( string $context ) :int {
		$key = [
				   self::CONTEXT_AUTH     => 'login_limit_interval',
				   self::CONTEXT_COMMENTS => 'comments_cooldown',
			   ][ $context ] ?? null;
		return empty( $key ) ? 0 : self::con()->opts->optGet( $key );
	}

	public function isCooldownContextEnabled( string $context ) :bool {
		return $this->intervalPerContext( $context ) > 0;
	}

	private function serviceAvailable() :bool {
		return self::con()->cache_dir_handler->exists();
	}

	private function getFlagFilePath( string $context ) :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'mode.throttled_'.\sanitize_key( $context ) );
	}

	private function touchFlag( string $context ) :void {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath( $context );
		$FS->deleteFile( $file );
		$FS->touch( $file, Services::Request()->ts() );
	}
}