<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type ConfigureZoneTileContract from ConfigureZoneTilesBuilder
 * @phpstan-import-type ConfigureRowContract from ConfigureZoneTilesBuilder
 * @phpstan-type CoverageCounts array{
 *   total:int,
 *   good:int,
 *   warning:int,
 *   critical:int
 * }
 * @phpstan-type ConfigurationCoverage array{
 *   severity:'good'|'warning'|'critical',
 *   percentage:int,
 *   controls:CoverageCounts,
 *   zones:CoverageCounts
 * }
 */
class BuildConfigurationCoverage {

	private ConfigureZoneTilesBuilder $tilesBuilder;

	public function __construct( ?ConfigureZoneTilesBuilder $tilesBuilder = null ) {
		$this->tilesBuilder = $tilesBuilder ?? new ConfigureZoneTilesBuilder();
	}

	/**
	 * @return ConfigurationCoverage
	 */
	public function build() :array {
		$zoneCounts = $this->emptyCounts();
		$controlCounts = $this->emptyCounts();
		$earnedPoints = 0.0;
		$zoneSeverities = [];

		foreach ( $this->tilesBuilder->build() as $tile ) {
			if ( empty( $tile[ 'include_in_posture' ] ) ) {
				continue;
			}

			$zoneStatus = $this->normalizeCoverageStatus( (string)( $tile[ 'status' ] ?? '' ) );
			$zoneCounts[ 'total' ]++;
			$zoneCounts[ $zoneStatus ]++;
			$zoneSeverities[] = $zoneStatus;

			foreach ( $tile[ 'panel' ][ 'rows' ] ?? [] as $row ) {
				$rowStatus = $this->normalizeCoverageStatus( (string)( $row[ 'status' ] ?? '' ) );
				if ( $rowStatus === 'neutral' ) {
					continue;
				}

				$controlCounts[ 'total' ]++;
				$controlCounts[ $rowStatus ]++;
				$earnedPoints += $this->pointsForStatus( $rowStatus );
			}
		}

		$percentage = $controlCounts[ 'total' ] > 0
			? (int)\round( 100*$earnedPoints/$controlCounts[ 'total' ] )
			: 100;

		return [
			'severity'   => $this->determineCoverageSeverity( $zoneSeverities ),
			'percentage' => \max( 0, \min( 100, $percentage ) ),
			'controls'   => $controlCounts,
			'zones'      => $zoneCounts,
		];
	}

	/**
	 * @return CoverageCounts
	 */
	private function emptyCounts() :array {
		return [
			'total'    => 0,
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];
	}

	/**
	 * @param list<string> $zoneSeverities
	 * @return 'good'|'warning'|'critical'
	 */
	private function determineCoverageSeverity( array $zoneSeverities ) :string {
		$severity = StatusPriority::highest( $zoneSeverities, 'good' );
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true )
			? $severity
			: 'good';
	}

	/**
	 * @return 'critical'|'warning'|'good'|'neutral'
	 */
	private function normalizeCoverageStatus( string $status ) :string {
		$status = \strtolower( \trim( $status ) );
		return \in_array( $status, [ 'critical', 'warning', 'good', 'neutral' ], true )
			? $status
			: 'good';
	}

	private function pointsForStatus( string $status ) :float {
		switch ( $status ) {
			case 'warning':
				return 0.5;
			case 'critical':
				return 0.0;
			case 'good':
			default:
				return 1.0;
		}
	}
}
