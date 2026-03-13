<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type ScanRuntime array{
 *   is_running:bool,
 *   enqueued_count:int,
 *   running_states:array<string,bool>,
 *   current_slug:string,
 *   current_name:string,
 *   progress:float
 * }
 */
class BuildScanRuntime {

	use PluginControllerConsumer;

	/**
	 * @return ScanRuntime
	 */
	public function build() :array {
		$currentSlug = ( new ScansStatus() )->current();
		$queueCon = self::con()->comps->scans_queue;

		return [
			'is_running'     => $currentSlug !== '',
			'enqueued_count' => \count( ( new ScansStatus() )->enqueued() ),
			'running_states' => $queueCon->getScansRunningStates(),
			'current_slug'   => $currentSlug,
			'current_name'   => $currentSlug === ''
				? ''
				: self::con()->comps->scans->getScanCon( $currentSlug )->getScanName(),
			'progress'       => (float)$queueCon->getScanJobProgress(),
		];
	}
}
