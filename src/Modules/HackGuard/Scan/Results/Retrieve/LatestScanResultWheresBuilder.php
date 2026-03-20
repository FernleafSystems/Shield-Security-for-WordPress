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
		$includes = self::con()->opts->optGet( 'scan_results_table_display' );
		return $this->forResultsDisplayWithOptions( $latestScanId, [
			'include_ignored'  => \is_array( $includes ) && \in_array( 'include_ignored', $includes, true ),
			'include_repaired' => \is_array( $includes ) && \in_array( 'include_repaired', $includes, true ),
			'include_deleted'  => \is_array( $includes ) && \in_array( 'include_deleted', $includes, true ),
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return list<string>
	 */
	public function forResultsDisplayWithOptions( int $latestScanId, array $options = [] ) :array {
		$options = $this->normalizeResultsDisplayOptions( $options );
		$wheres = \array_merge( $this->buildBase( $latestScanId ), [
			"`ri`.`auto_filtered_at`=0",
		] );

		if ( $options[ 'ignored_only' ] ) {
			$wheres[] = "`ri`.`ignored_at`>0";
		}
		elseif ( !$options[ 'include_ignored' ] ) {
			$wheres[] = "`ri`.`ignored_at`=0";
		}
		if ( !$options[ 'include_repaired' ] ) {
			$wheres[] = "`ri`.`item_repaired_at`=0";
		}
		if ( !$options[ 'include_deleted' ] ) {
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

	/**
	 * @param array<string,mixed> $options
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	private function normalizeResultsDisplayOptions( array $options ) :array {
		return [
			'include_ignored'  => !empty( $options[ 'include_ignored' ] ),
			'include_repaired' => !empty( $options[ 'include_repaired' ] ),
			'include_deleted'  => !empty( $options[ 'include_deleted' ] ),
			'ignored_only'     => !empty( $options[ 'ignored_only' ] ),
		];
	}
}
