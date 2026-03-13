<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Record as EventRecord;
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
		/** @var array<string,EventRecord> $latestRecords */
		$latestRecords = $this->latestRecords();

		$items = [];
		foreach ( \array_keys( $recentEvents ) as $eventKey ) {
			$record = $latestRecords[ $eventKey ] ?? null;
			$items[] = [
				'key'       => $eventKey,
				'label'     => $eventsService->getEventName( $eventKey ),
				'latest_at' => $record instanceof EventRecord ? (int)$record->created_at : 0,
				'has_record' => $record instanceof EventRecord,
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
	 * @return array<string,EventRecord>
	 */
	protected function latestRecords() :array {
		return self::con()->db_con->events->getQuerySelector()->getLatestForAllEvents();
	}
}
