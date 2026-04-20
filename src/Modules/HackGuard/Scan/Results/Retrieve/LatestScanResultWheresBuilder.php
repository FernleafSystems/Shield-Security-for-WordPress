<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LatestScanResultWheresBuilder {

	use PluginControllerConsumer;

	/**
	 * @return list<string>
	 */
	public function forActiveProblems( string $scanSlug ) :array {
		return \array_merge( $this->buildBase( $scanSlug ), [
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`ignored_at`=0",
			"`ri`.`resolved_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	public function forContext( string $scanSlug, int $context ) :array {
		switch ( $context ) {
			case RetrieveCount::CONTEXT_NOT_YET_NOTIFIED:
				return $this->forNotYetNotified( $scanSlug );
			case RetrieveCount::CONTEXT_RESULTS_DISPLAY:
				return $this->forResultsDisplay( $scanSlug );
			case RetrieveCount::CONTEXT_ACTIVE_PROBLEMS:
			default:
				return $this->forActiveProblems( $scanSlug );
		}
	}

	/**
	 * @return list<string>
	 */
	public function forNotYetNotified( string $scanSlug ) :array {
		return \array_merge( $this->forActiveProblems( $scanSlug ), [
			"`ri`.`notified_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	public function forResultsDisplay( string $scanSlug ) :array {
		try {
			$includes = self::con()->opts->optGet( 'scan_results_table_display' );
		}
		catch ( \Throwable $e ) {
			$includes = [];
		}
		return $this->forResultsDisplayWithOptions( $scanSlug, [
			'include_ignored'  => \is_array( $includes ) && \in_array( 'include_ignored', $includes, true ),
			'include_repaired' => \is_array( $includes ) && \in_array( 'include_repaired', $includes, true ),
			'include_deleted'  => \is_array( $includes ) && \in_array( 'include_deleted', $includes, true ),
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return list<string>
	 */
	public function forResultsDisplayWithOptions( string $scanSlug, array $options = [] ) :array {
		$options = $this->normalizeResultsDisplayOptions( $options );
		$wheres = \array_merge( $this->buildBase( $scanSlug ), [
			"`ri`.`auto_filtered_at`=0",
			"`ri`.`resolution_reason`!='clean_rescan'",
			"`ri`.`resolution_reason`!='asset_replaced'",
		] );

		if ( $options[ 'ignored_only' ] ) {
			$wheres[] = "`ri`.`ignored_at`>0";
		}
		elseif ( !$options[ 'include_ignored' ] ) {
			$wheres[] = "`ri`.`ignored_at`=0";
		}
		if ( !$options[ 'include_repaired' ] ) {
			$wheres[] = "(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='repaired')";
		}
		if ( !$options[ 'include_deleted' ] ) {
			$wheres[] = "(`ri`.`resolved_at`=0 OR `ri`.`resolution_reason`!='deleted')";
		}

		return $wheres;
	}

	/**
	 * @return list<string>
	 */
	public function forLatestResults( string $scanSlug ) :array {
		return \array_merge( $this->buildBase( $scanSlug ), [
			"`ri`.`resolved_at`=0",
		] );
	}

	/**
	 * @return list<string>
	 */
	private function buildBase( string $scanSlug ) :array {
		$scanSlug = \preg_replace( '/[^a-z0-9_]/i', '', $scanSlug ) ?? '';
		return [
			\sprintf( "`ri`.`scan`='%s'", $scanSlug ),
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
