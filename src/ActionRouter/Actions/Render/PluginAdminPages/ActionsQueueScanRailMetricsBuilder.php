<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueScanRailMetricsBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   tabs:array<string,array{count:int,status:string}>,
	 *   rail_accent_status:string
	 * }
	 */
	public function build() :array {
		$tabs = [];
		$statuses = [];
		$displayCounts = new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY );

		if ( $this->isWordpressTabEnabled() ) {
			$count = $displayCounts->countWPFiles();
			$tabs[ 'wordpress' ] = [
				'count'  => $count,
				'status' => $count > 0 ? 'critical' : 'good',
			];
			$statuses[] = $tabs[ 'wordpress' ][ 'status' ];
		}

		if ( $this->isPluginsTabEnabled() ) {
			$count = $this->countDistinctAffectedAssets( 'plugin' );
			$tabs[ 'plugins' ] = [
				'count'  => $count,
				'status' => $count > 0 ? 'warning' : 'good',
			];
			$statuses[] = $tabs[ 'plugins' ][ 'status' ];
		}

		if ( $this->isThemesTabEnabled() ) {
			$count = $this->countDistinctAffectedAssets( 'theme' );
			$tabs[ 'themes' ] = [
				'count'  => $count,
				'status' => $count > 0 ? 'warning' : 'good',
			];
			$statuses[] = $tabs[ 'themes' ][ 'status' ];
		}

		if ( $this->isVulnerabilitiesTabEnabled() ) {
			$vulnerableCount = $this->countDistinctResultItemIds(
				self::con()->comps->scans->WPV()->getSlug(),
				'is_vulnerable'
			);
			$abandonedCount = $this->countDistinctResultItemIds(
				self::con()->comps->scans->APC()->getSlug(),
				'is_abandoned'
			);
			$tabs[ 'vulnerabilities' ] = [
				'count'  => $vulnerableCount + $abandonedCount,
				'status' => $vulnerableCount > 0
					? 'critical'
					: ( $abandonedCount > 0 ? 'warning' : 'good' ),
			];
			$statuses[] = $tabs[ 'vulnerabilities' ][ 'status' ];
		}

		if ( $this->isMalwareTabEnabled() ) {
			$count = $displayCounts->countMalware();
			$tabs[ 'malware' ] = [
				'count'  => $count,
				'status' => $count > 0 ? 'critical' : 'good',
			];
			$statuses[] = $tabs[ 'malware' ][ 'status' ];
		}

		$fileLockerCount = \count( ( new LoadFileLocks() )->withProblems() );
		$tabs[ 'file_locker' ] = [
			'count'  => $fileLockerCount,
			'status' => $fileLockerCount > 0 ? 'warning' : 'good',
		];
		$statuses[] = $tabs[ 'file_locker' ][ 'status' ];

		return [
			'tabs'               => $tabs,
			'rail_accent_status' => StatusPriority::highest( $statuses, 'good' ),
		];
	}

	private function isWordpressTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpCore();
	}

	private function isPluginsTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledPlugins();
	}

	private function isThemesTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledThemes();
	}

	private function isVulnerabilitiesTabEnabled() :bool {
		$scansCon = self::con()->comps->scans;
		return $scansCon->WPV()->isEnabled() || $scansCon->APC()->isEnabled();
	}

	private function isMalwareTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP();
	}

	private function countDistinctAffectedAssets( string $assetType ) :int {
		$latestScanId = $this->getLatestScanId( self::con()->comps->scans->AFS()->getSlug() );
		if ( $latestScanId < 1 ) {
			return 0;
		}

		$dbCon = self::con()->db_con;
		$membershipMetaKey = $assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme';
		$query = Services::WpDb()->prepare(
			"SELECT COUNT(DISTINCT `slug_meta`.`meta_value`)
			FROM `{$dbCon->scan_results->getTable()}` AS `sr`
			INNER JOIN `{$dbCon->scan_result_items->getTable()}` AS `ri`
				ON `sr`.`resultitem_ref`=`ri`.`id`
			INNER JOIN `{$dbCon->scan_result_item_meta->getTable()}` AS `membership_meta`
				ON `membership_meta`.`ri_ref`=`ri`.`id`
				AND `membership_meta`.`meta_key`=%s
			INNER JOIN `{$dbCon->scan_result_item_meta->getTable()}` AS `slug_meta`
				ON `slug_meta`.`ri_ref`=`ri`.`id`
				AND `slug_meta`.`meta_key`='ptg_slug'
				AND `slug_meta`.`meta_value`!=''
			WHERE ".\implode( ' AND ', $this->buildDisplayWheres( $latestScanId ) ),
			$membershipMetaKey
		);

		return (int)Services::WpDb()->getVar( $query );
	}

	private function countDistinctResultItemIds( string $scanSlug, string $metaKey ) :int {
		$latestScanId = $this->getLatestScanId( $scanSlug );
		if ( $latestScanId < 1 ) {
			return 0;
		}

		$dbCon = self::con()->db_con;
		$query = Services::WpDb()->prepare(
			"SELECT COUNT(DISTINCT `ri`.`item_id`)
			FROM `{$dbCon->scan_results->getTable()}` AS `sr`
			INNER JOIN `{$dbCon->scan_result_items->getTable()}` AS `ri`
				ON `sr`.`resultitem_ref`=`ri`.`id`
			INNER JOIN `{$dbCon->scan_result_item_meta->getTable()}` AS `rim`
				ON `rim`.`ri_ref`=`ri`.`id`
				AND `rim`.`meta_key`=%s
			WHERE ".\implode( ' AND ', $this->buildDisplayWheres( $latestScanId ) ),
			$metaKey
		);

		return (int)Services::WpDb()->getVar( $query );
	}

	/**
	 * @return list<string>
	 */
	private function buildDisplayWheres( int $latestScanId ) :array {
		$wheres = [
			\sprintf( "`sr`.`scan_ref`=%d", $latestScanId ),
			"`ri`.`deleted_at`=0",
			"`ri`.`auto_filtered_at`=0",
		];

		$includes = self::con()->opts->optGet( 'scan_results_table_display' );
		$includes = \is_array( $includes ) ? $includes : [];

		if ( !\in_array( 'include_ignored', $includes, true ) ) {
			$wheres[] = "`ri`.`ignored_at`=0";
		}
		if ( !\in_array( 'include_repaired', $includes, true ) ) {
			$wheres[] = "`ri`.`item_repaired_at`=0";
		}
		if ( !\in_array( 'include_deleted', $includes, true ) ) {
			$wheres[] = "`ri`.`item_deleted_at`=0";
		}

		return $wheres;
	}

	private function getLatestScanId( string $scanSlug ) :int {
		$latest = self::con()->db_con->scans->getQuerySelector()->getLatestForScan( $scanSlug );
		return empty( $latest ) ? 0 : (int)$latest->id;
	}
}
