<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueScheduler {

	use PluginControllerConsumer;

	public const HOOK = 'importexport_sites_queue';
	public const INTERVAL = 300;

	private \Closure $canRun;
	private \Closure $runWorker;

	public function __construct( ?callable $canRun = null, ?callable $runWorker = null ) {
		$this->canRun = \Closure::fromCallable( $canRun ?? static fn() :bool => false );
		$this->runWorker = \Closure::fromCallable( $runWorker ?? static function () :void {} );
	}

	public function setup() :void {
		$hook = $this->hook();
		add_action( $hook, function () {
			if ( !$this->canRun() ) {
				$this->clear();
				return;
			}

			$this->scheduleNext();
			( $this->runWorker )();
		}, 10, 0 );

		$this->scheduleNext();
	}

	public function scheduleSoon( int $delay = 30 ) :void {
		if ( !$this->canRun() ) {
			$this->clear();
			return;
		}

		$timestamp = Services::Request()->ts() + \max( 1, $delay );
		$next = wp_next_scheduled( $this->hook() );
		if ( !empty( $next ) && $next <= $timestamp ) {
			return;
		}

		$this->clear();
		wp_schedule_single_event( $timestamp, $this->hook() );
	}

	public function clear() :void {
		wp_clear_scheduled_hook( $this->hook() );
	}

	private function scheduleNext( ?int $timestamp = null ) :void {
		$hook = $this->hook();
		if ( !$this->canRun() ) {
			$this->clear();
			return;
		}

		$timestamp = $timestamp ?? Services::Request()->ts() + self::INTERVAL;
		if ( empty( wp_next_scheduled( $hook ) ) ) {
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
