<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AttentionSummary from BuildAttentionItems
 * @phpstan-import-type ScanRuntime from BuildScanRuntime
 * @phpstan-type OverviewSite array{
 *   url:string,
 *   name:string,
 *   shield_version:string,
 *   is_premium:bool
 * }
 * @phpstan-type OverviewPosture array{
 *   status:string,
 *   severity:string,
 *   percentage:int,
 *   totals:array{score:int,max_weight:int,percentage:int,letter_score:string}
 * }
 * @phpstan-type OverviewScans array{
 *   is_running:bool,
 *   enqueued_count:int,
 *   latest_completed_at:array{
 *     malware:int,
 *     vulnerabilities:int,
 *     abandoned:int,
 *     core_files:int,
 *     plugin_files:int,
 *     theme_files:int
 *   }
 * }
 * @phpstan-type OverviewQuery array{
 *   generated_at:int,
 *   site:OverviewSite,
 *   attention_summary:AttentionSummary,
 *   posture:OverviewPosture,
 *   scans:OverviewScans
 * }
 */
class BuildOverview {

	use PluginControllerConsumer;

	/**
	 * @return OverviewQuery
	 */
	public function build() :array {
		$attention = $this->buildAttentionQuery();
		$posture = $this->buildPosture();
		$runtime = $this->buildScanRuntime();

		return [
			'generated_at'      => Services::Request()->ts(),
			'site'              => $this->buildSite(),
			'attention_summary' => $attention[ 'summary' ],
			'posture'           => [
				'status'     => $posture[ 'status' ],
				'severity'   => $posture[ 'severity' ],
				'percentage' => $posture[ 'percentage' ],
				'totals'     => $posture[ 'totals' ],
			],
			'scans'             => [
				'is_running'         => $runtime[ 'is_running' ],
				'enqueued_count'     => $runtime[ 'enqueued_count' ],
				'latest_completed_at' => [
					'malware'         => $this->getLatestCompletedScanTimestamp( 'afs' ),
					'vulnerabilities' => $this->getLatestCompletedScanTimestamp( 'wpv' ),
					'abandoned'       => $this->getLatestCompletedScanTimestamp( 'apc' ),
					'core_files'      => $this->getLatestCompletedScanTimestamp( 'afs' ),
					'plugin_files'    => $this->getLatestCompletedScanTimestamp( 'afs' ),
					'theme_files'     => $this->getLatestCompletedScanTimestamp( 'afs' ),
				],
			],
		];
	}

	protected function getLatestCompletedScanTimestamp( string $scanSlug ) :int {
		try {
			$record = self::con()
				->db_con
				->scans
				->getQuerySelector()
				->filterByScan( $scanSlug )
				->filterByFinished()
				->setOrderBy( 'id', 'DESC', true )
				->first();
			return $record instanceof ScanRecord ? (int)$record->finished_at : 0;
		}
		catch ( \Exception $e ) {
			return 0;
		}
	}

	protected function buildAttentionQuery() :array {
		return ( new BuildAttentionItems() )->build();
	}

	protected function buildPosture() :array {
		return ( new BuildZonePosture() )->build();
	}

	protected function buildScanRuntime() :array {
		return ( new BuildScanRuntime() )->build();
	}

	protected function buildSite() :array {
		return [
			'url'            => Services::WpGeneral()->getHomeUrl(),
			'name'           => Services::WpGeneral()->getSiteName(),
			'shield_version' => self::con()->cfg->version(),
			'is_premium'     => self::con()->isPremiumActive(),
		];
	}
}
