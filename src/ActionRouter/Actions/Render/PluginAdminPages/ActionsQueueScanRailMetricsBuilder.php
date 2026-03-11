<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueScanRailMetricsBuilder {

	use PluginControllerConsumer;

	private ?ScansResultsRailTabAvailability $tabAvailability = null;

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

		$wordpressState = $this->getTabAvailability( 'wordpress' );
		if ( !empty( $wordpressState[ 'show_in_actions_queue' ] ) && !empty( $wordpressState[ 'is_available' ] ) ) {
			$count = $displayCounts->countWPFiles();
			$tabs[ 'wordpress' ] = [
				'count'  => $count,
				'status' => $count > 0 ? 'critical' : 'good',
			];
			$statuses[] = $tabs[ 'wordpress' ][ 'status' ];
		}

		$this->appendAssetTabMetrics( $tabs, $statuses, 'plugins', 'plugin' );
		$this->appendAssetTabMetrics( $tabs, $statuses, 'themes', 'theme' );
		$this->appendVulnerabilitiesMetrics( $tabs, $statuses );
		$this->appendMalwareMetrics( $tabs, $statuses, $displayCounts );

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

	/**
	 * @param array<string,array{count:int,status:string}> $tabs
	 * @param list<string> $statuses
	 */
	private function appendAssetTabMetrics( array &$tabs, array &$statuses, string $tabKey, string $assetType ) :void {
		$availability = $this->getTabAvailability( $tabKey );
		if ( empty( $availability[ 'show_in_actions_queue' ] ) ) {
			return;
		}

		if ( empty( $availability[ 'is_available' ] ) ) {
			$tabs[ $tabKey ] = $this->buildDisabledTabMetrics();
			return;
		}

		$count = $this->countDistinctAffectedAssets( $assetType );
		$tabs[ $tabKey ] = [
			'count'  => $count,
			'status' => $count > 0 ? 'warning' : 'good',
		];
		$statuses[] = $tabs[ $tabKey ][ 'status' ];
	}

	/**
	 * @param array<string,array{count:int,status:string}> $tabs
	 * @param list<string> $statuses
	 */
	private function appendVulnerabilitiesMetrics( array &$tabs, array &$statuses ) :void {
		$availability = $this->getTabAvailability( 'vulnerabilities' );
		if ( empty( $availability[ 'show_in_actions_queue' ] ) ) {
			return;
		}

		if ( empty( $availability[ 'is_available' ] ) ) {
			$tabs[ 'vulnerabilities' ] = $this->buildDisabledTabMetrics();
			return;
		}

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

	/**
	 * @param array<string,array{count:int,status:string}> $tabs
	 * @param list<string> $statuses
	 */
	private function appendMalwareMetrics( array &$tabs, array &$statuses, Counts $displayCounts ) :void {
		$availability = $this->getTabAvailability( 'malware' );
		if ( empty( $availability[ 'show_in_actions_queue' ] ) ) {
			return;
		}

		if ( empty( $availability[ 'is_available' ] ) ) {
			$tabs[ 'malware' ] = $this->buildDisabledTabMetrics();
			return;
		}

		$count = $displayCounts->countMalware();
		$tabs[ 'malware' ] = [
			'count'  => $count,
			'status' => $count > 0 ? 'critical' : 'good',
		];
		$statuses[] = $tabs[ 'malware' ][ 'status' ];
	}

	/**
	 * @return array{count:int,status:string}
	 */
	private function buildDisabledTabMetrics() :array {
		return [
			'count'  => 0,
			'status' => 'neutral',
		];
	}

	/**
	 * @return array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   disabled_message:string,
	 *   disabled_status:string
	 * }
	 */
	private function getTabAvailability( string $tabKey ) :array {
		return $this->getTabAvailabilityBuilder()->build( $tabKey );
	}

	private function getTabAvailabilityBuilder() :ScansResultsRailTabAvailability {
		if ( $this->tabAvailability === null ) {
			$this->tabAvailability = new ScansResultsRailTabAvailability();
		}

		return $this->tabAvailability;
	}

	private function countDistinctAffectedAssets( string $assetType ) :int {
		$latestScanId = $this->getLatestScanId( self::con()->comps->scans->AFS()->getSlug() );
		if ( $latestScanId < 1 ) {
			return 0;
		}

		$dbCon = self::con()->db_con;
		$wpdb = Services::WpDb()->loadWpdb();
		$membershipMetaKey = $assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme';
		$query = $wpdb->prepare(
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
		$wpdb = Services::WpDb()->loadWpdb();
		$query = $wpdb->prepare(
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
