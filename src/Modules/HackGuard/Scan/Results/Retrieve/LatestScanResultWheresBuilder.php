<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions;

class LatestScanResultWheresBuilder {

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
		return $this->forResultsDisplayWithOptions( $scanSlug, ( new ScanResultsDisplayOptions() )->activeOnly() );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return list<string>
	 */
	public function forResultsDisplayWithOptions( string $scanSlug, array $options = [] ) :array {
		$options = ( new ScanResultsDisplayOptions() )->normalize( $options );
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

}
