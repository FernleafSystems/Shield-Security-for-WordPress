<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\{
	BuildAttentionItems,
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
	 * @return AttentionQuery
	 */
	public function attention() :array {
		return ( new BuildAttentionItems() )->build();
	}

	/**
	 * @return OverviewQuery
	 */
	public function overview() :array {
		return ( new BuildOverview() )->build();
	}

	/**
	 * @return RecentActivityQuery
	 */
	public function recentActivity() :array {
		return ( new BuildRecentActivity() )->build();
	}

	/**
	 * @param string[] $scanSlugs
	 * @param string[] $statesToInclude
	 * @return ScanFindingsQuery
	 */
	public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) :array {
		return ( new BuildScanFindings() )->build( $scanSlugs, $statesToInclude );
	}

	/**
	 * @return ScanRuntime
	 */
	public function scanRuntime() :array {
		return ( new BuildScanRuntime() )->build();
	}
}
