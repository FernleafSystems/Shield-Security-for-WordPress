<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LatestScanResultWheresBuilder {

	use PluginControllerConsumer;

	/**
	 * @return list<string>
	 */
	public function forActiveProblems( int $latestScanId ) :array {
		return \array_merge( $this->buildBase( $latestScanId ), [
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	public function forContext( int $latestScanId, int $context ) :array {
		switch ( $context ) {
			case RetrieveCount::CONTEXT_NOT_YET_NOTIFIED:
				return $this->forNotYetNotified( $latestScanId );
			case RetrieveCount::CONTEXT_RESULTS_DISPLAY:
				return $this->forResultsDisplay( $latestScanId );
			case RetrieveCount::CONTEXT_ACTIVE_PROBLEMS:
			default:
				return $this->forActiveProblems( $latestScanId );
		}
	}

	/**
	 * @return list<string>
	 */
	public function forNotYetNotified( int $latestScanId ) :array {
		return \array_merge( $this->buildBase( $latestScanId ), [
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
			"`ri`.`notified_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	public function forResultsDisplay( int $latestScanId ) :array {
		$wheres = \array_merge( $this->buildBase( $latestScanId ), [
			"`ri`.`auto_filtered_at`=0",
		] );

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

	/**
	 * @return list<string>
	 */
	public function forLatestResults( int $latestScanId ) :array {
		return \array_merge( $this->buildBase( $latestScanId ), [
			"`ri`.`item_repaired_at`=0",
			"`ri`.`item_deleted_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	private function buildBase( int $latestScanId ) :array {
		return [
			\sprintf( "`sr`.`scan_ref`=%d", $latestScanId ),
			"`ri`.`deleted_at`=0",
		];
	}
}
