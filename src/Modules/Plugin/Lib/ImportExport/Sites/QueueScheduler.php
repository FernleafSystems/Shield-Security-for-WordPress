<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueScheduler {

	use PluginControllerConsumer;

	public const HOOK = 'importexport_sites_queue';
	public const INTERVAL = 300;

	private \Closure $canRun;

	public function __construct( ?callable $canRun = null ) {
		$this->canRun = \Closure::fromCallable( $canRun ?? static fn() :bool => false );
	}

	public function setup() :void {
		$hook = $this->hook();
		add_action( $hook, function () {
			if ( !$this->canRun() ) {
				$this->clear();
				return;
			}

			( new QueueRunner() )->run();
			$this->scheduleNext();
		}, 10, 0 );

		$this->scheduleNext();
	}

	public function scheduleSoon( int $delay = 30 ) :void {
		$this->scheduleNext( Services::Request()->ts() + \max( 1, $delay ), true );
	}

	public function clear() :void {
		wp_clear_scheduled_hook( $this->hook() );
	}

	private function scheduleNext( ?int $timestamp = null, bool $preferEarlier = false ) :void {
		$hook = $this->hook();
		if ( !$this->canRun() ) {
			$this->clear();
			return;
		}

		$timestamp = $timestamp ?? Services::Request()->ts() + self::INTERVAL;
		$next = wp_next_scheduled( $hook );
		if ( $preferEarlier && !empty( $next ) && $next > $timestamp ) {
			$this->clear();
			$next = false;
		}
		if ( empty( $next ) ) {
			wp_schedule_single_event( $timestamp, $hook );
		}
	}

	private function canRun() :bool {
		return ( $this->canRun )();
	}

	public function hook() :string {
		return self::con()->prefix( self::HOOK );
	}

	public function hasScheduledEvent() :bool {
		return !empty( wp_next_scheduled( $this->hook() ) );
	}
}
