<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type RecentActivityItem array{
 *   key:string,
 *   label:string,
 *   latest_at:int,
 *   has_record:bool
 * }
 * @phpstan-type RecentActivityQuery array{
 *   generated_at:int,
 *   items:list<RecentActivityItem>
 * }
 */
class BuildRecentActivity {

	use PluginControllerConsumer;

	/**
	 * @return RecentActivityQuery
	 */
	public function build() :array {
		$eventsService = $this->eventsService();
		$recentEvents = \array_filter(
			$eventsService->getEvents(),
			static fn( array $event ) :bool => !empty( $event[ 'recent' ] )
		);
		$latestTimestamps = $this->latestTimestamps( \array_keys( $recentEvents ) );

		$items = [];
		foreach ( \array_keys( $recentEvents ) as $eventKey ) {
			$latestAt = $latestTimestamps[ $eventKey ] ?? 0;
			$items[] = [
				'key'        => $eventKey,
				'label'      => $eventsService->getEventName( $eventKey ),
				'latest_at'  => $latestAt,
				'has_record' => $latestAt > 0,
			];
		}

		return [
			'generated_at' => Services::Request()->ts(),
			'items'        => $items,
		];
	}

	protected function eventsService() {
		return self::con()->comps->events;
	}

	/**
	 * @param string[] $eventKeys
	 * @return array<string,int>
	 */
	protected function latestTimestamps( array $eventKeys ) :array {
		return self::con()->db_con->events->getQuerySelector()->getLatestTimestampsForEvents( $eventKeys );
	}
}
