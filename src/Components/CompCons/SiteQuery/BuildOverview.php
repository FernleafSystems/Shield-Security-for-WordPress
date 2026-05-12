<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\BuildConfigurationCoverage;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQueryCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
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
 *   severity:'good'|'warning'|'critical',
 *   percentage:int,
 *   controls:array{total:int,good:int,warning:int,critical:int},
 *   zones:array{total:int,good:int,warning:int,critical:int}
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
		$runtime = $this->buildScanRuntime();
		return [
			'generated_at'      => Services::Request()->ts(),
			'site'              => $this->buildSite(),
			'attention_summary' => $this->buildAttentionQuery()[ 'summary' ],
			'posture'           => $this->buildPosture(),
			'scans'             => [
				'is_running'         => $runtime[ 'is_running' ],
				'enqueued_count'     => $runtime[ 'enqueued_count' ],
				'latest_completed_at' => $this->buildLatestCompletedScanTimestamps(),
			],
		];
	}

	protected function buildLatestCompletedScanTimestamps() :array {
		return $this->siteQueryCon()->latestCompletedScanTimestamps();
	}

	protected function buildAttentionQuery() :array {
		return $this->siteQueryCon()->attention();
	}

	protected function buildPosture() :array {
		return ( new BuildConfigurationCoverage() )->build();
	}

	protected function buildScanRuntime() :array {
		return $this->siteQueryCon()->scanRuntime();
	}

	protected function buildSite() :array {
		return [
			'url'            => Services::WpGeneral()->getHomeUrl(),
			'name'           => Services::WpGeneral()->getSiteName(),
			'shield_version' => self::con()->cfg->version(),
			'is_premium'     => self::con()->isPremiumActive(),
		];
	}

	protected function siteQueryCon() :SiteQueryCon {
		return self::con()->comps->site_query;
	}
}
