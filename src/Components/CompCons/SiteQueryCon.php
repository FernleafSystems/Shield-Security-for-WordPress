<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\{
	BuildAttentionItems,
	BuildLatestCompletedScanTimestamps,
	BuildOverview,
	BuildRecentActivity,
	BuildScanFindings,
	BuildScanRuntime
};

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type OverviewQuery from BuildOverview
 * @phpstan-import-type RecentActivityQuery from BuildRecentActivity
 * @phpstan-import-type ScanFindingsQuery from BuildScanFindings
 * @phpstan-import-type ScanRuntime from BuildScanRuntime
 */
class SiteQueryCon {

	/**
	 * @var array<string,mixed>
	 */
	private array $memoized = [];

	/**
	 * @return AttentionQuery
	 */
	public function attention() :array {
		return $this->memoized[ 'attention' ] ??= $this->buildAttentionItems()->build();
	}

	/**
	 * @return OverviewQuery
	 */
	public function overview() :array {
		return $this->memoized[ 'overview' ] ??= $this->buildOverview()->build();
	}

	/**
	 * @return array{
	 *   malware:int,
	 *   vulnerabilities:int,
	 *   abandoned:int,
	 *   core_files:int,
	 *   plugin_files:int,
	 *   theme_files:int
	 * }
	 */
	public function latestCompletedScanTimestamps() :array {
		return $this->memoized[ 'latestCompletedScanTimestamps' ] ??= $this->buildLatestCompletedScanTimestamps()->build();
	}

	/**
	 * @return RecentActivityQuery
	 */
	public function recentActivity() :array {
		return $this->memoized[ 'recentActivity' ] ??= $this->buildRecentActivity()->build();
	}

	/**
	 * @param string[] $scanSlugs
	 * @param string[] $statesToInclude
	 * @return ScanFindingsQuery
	 */
	public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) :array {
		$cacheKey = 'scanFindings:'.\md5( \serialize( [ $scanSlugs, $statesToInclude ] ) );
		return $this->memoized[ $cacheKey ] ??= $this->buildScanFindings()->build( $scanSlugs, $statesToInclude );
	}

	/**
	 * @return ScanRuntime
	 */
	public function scanRuntime() :array {
		return $this->memoized[ 'scanRuntime' ] ??= $this->buildScanRuntime()->build();
	}

	public function clearMemoized() :void {
		$this->memoized = [];
	}

	protected function buildAttentionItems() :BuildAttentionItems {
		return new BuildAttentionItems();
	}

	protected function buildOverview() :BuildOverview {
		return new BuildOverview();
	}

	protected function buildLatestCompletedScanTimestamps() :BuildLatestCompletedScanTimestamps {
		return new BuildLatestCompletedScanTimestamps();
	}

	protected function buildRecentActivity() :BuildRecentActivity {
		return new BuildRecentActivity();
	}

	protected function buildScanFindings() :BuildScanFindings {
		return new BuildScanFindings();
	}

	protected function buildScanRuntime() :BuildScanRuntime {
		return new BuildScanRuntime();
	}
}
