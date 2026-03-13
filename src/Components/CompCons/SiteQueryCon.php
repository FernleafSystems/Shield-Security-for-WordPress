<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\{
	BuildAttentionItems,
	BuildOverview,
	BuildRecentActivity,
	BuildScanRuntime
};

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type OverviewQuery from BuildOverview
 * @phpstan-import-type RecentActivityQuery from BuildRecentActivity
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
	 * @return ScanRuntime
	 */
	public function scanRuntime() :array {
		return ( new BuildScanRuntime() )->build();
	}
}
