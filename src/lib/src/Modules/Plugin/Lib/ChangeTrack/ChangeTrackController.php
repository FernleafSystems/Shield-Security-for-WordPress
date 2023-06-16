<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ChangeTrackController {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	/**
	 * @var Zone\BaseZone[]
	 */
	private $zones;

	protected function run() {
		if ( Services::WpGeneral()->isCron() ) {
			$this->setupCronHooks();
		}
	}

	public function getDbH_Changes() :ChangesDB\Handler {
		return $this->mod()->getDbHandler()->loadDbH( 'changes' );
	}

	/**
	 * @return Zone\BaseZone|mixed|null
	 */
	public function getZone( string $slug ) {
		return $this->getZones()[ $slug ] ?? null;
	}

	public function getZones() :array {
		if ( !isset( $this->zones ) ) {
			$this->zones = [];
			foreach ( Constants::ZONES as $zone ) {
				$this->zones[ $zone::SLUG ] = new $zone();
			}
		}
		return $this->zones;
	}

	public function runHourlyCron() {
		$this->storeNewSnapshot();
	}

	public function runDailyCron() {
		$this->purgeOldDiffs();
	}

	/**
	 * Deletes all diffs older than 4 whole weeks back.
	 */
	private function purgeOldDiffs() {
		/** @var ChangesDB\Delete $deleter */
		$deleter = $this->getDbH_Changes()->getQueryDeleter();
		$deleter->filterIsDiff()
				->addWhereOlderThan(
					Services::Request()
							->carbon( true )
							->endOfWeek()
							->subWeeks( 5 )->timestamp
				)
				->query();

		// 2. Delete any stranded Diffs
		/** @var ChangesDB\Select $selector */
		$selector = $this->getDbH_Changes()->getQuerySelector();
		/** @var ?ChangesDB\Record $record */
		$record = $selector->filterIsFull()
						   ->setOrderBy( 'created_at', 'ASC', true )
						   ->first();
		if ( !empty( $record ) ) {
			$deleter->filterIsDiff()
					->addWhereOlderThan( $record->created_at )
					->query();
		}
	}

	private function storeNewSnapshot() {
		try {
			$snapshot = ( new Ops\BuildNew() )->scheduled();
			// If it's an empty diff, no need to store.
			if ( !empty( \array_filter( $snapshot->data ) ) ) {
				( new Ops\Store() )->store( $snapshot );
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}
}