<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type LatestCompletedScanTimestamps array{
 *   malware:int,
 *   vulnerabilities:int,
 *   abandoned:int,
 *   core_files:int,
 *   plugin_files:int,
 *   theme_files:int
 * }
 */
class BuildLatestCompletedScanTimestamps {

	use PluginControllerConsumer;

	/**
	 * @return LatestCompletedScanTimestamps
	 */
	public function build() :array {
		$afsTimestamp = $this->getLatestCompletedScanTimestamp( 'afs' );

		return [
			'malware'         => $afsTimestamp,
			'vulnerabilities' => $this->getLatestCompletedScanTimestamp( 'wpv' ),
			'abandoned'       => $this->getLatestCompletedScanTimestamp( 'apc' ),
			'core_files'      => $afsTimestamp,
			'plugin_files'    => $afsTimestamp,
			'theme_files'     => $afsTimestamp,
		];
	}

	protected function getLatestCompletedScanTimestamp( string $scanSlug ) :int {
		try {
			$record = self::con()
				->db_con
				->scans
				->getQuerySelector()
				->filterByScan( $scanSlug )
				->filterByStatus( 'completed' )
				->addWhereEquals( 'scope_type', 'full' )
				->filterByFinished()
				->setOrderBy( 'id', 'DESC', true )
				->first();
			return $record instanceof ScanRecord ? (int)$record->finished_at : 0;
		}
		catch ( \Exception $e ) {
			return 0;
		}
	}
}
