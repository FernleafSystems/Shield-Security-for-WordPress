<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

class BuildZonePosture {

	/**
	 * @return array{
	 *   components:list<array<string,mixed>>,
	 *   signals:list<array<string,mixed>>,
	 *   totals:array{score:int,max_weight:int,percentage:int,letter_score:string},
	 *   percentage:int,
	 *   severity:string,
	 *   status:string
	 * }
	 */
	public function build() :array {
		$signals = [];
		$zonesCon = new SecurityZonesCon();
		foreach ( $zonesCon->getZones() as $zone ) {
			foreach ( $zonesCon->getComponentsForZone( $zone ) as $component ) {
				foreach ( $component->postureSignals() as $signal ) {
					$signal[ 'zone' ] = $zone::Slug();
					$signals[] = $signal;
				}
			}
		}

		$totalScore = (int)\array_sum( \array_column( $signals, 'score' ) );
		$totalWeight = (int)\array_sum( \array_column( $signals, 'weight' ) );
		$percentage = $totalWeight > 0 ? (int)\round( 100*$totalScore/$totalWeight ) : 0;
		$percentage = max( 0, min( 100, $percentage ) );

		return [
			'components' => $signals,
			'signals'    => $signals,
			'totals'     => [
				'score'        => $totalScore,
				'max_weight'   => $totalWeight,
				'percentage'   => $percentage,
				'letter_score' => $this->letterScoreFromPercentage( $percentage ),
			],
			'percentage' => $percentage,
			'severity'   => self::trafficFromPercentage( $percentage ),
			'status'     => $this->statusFromPercentage( $percentage ),
		];
	}

	public static function trafficFromPercentage( int $percentage ) :string {
		if ( $percentage > 80 ) {
			return 'good';
		}
		return $percentage > 40 ? 'warning' : 'critical';
	}

	private function letterScoreFromPercentage( int $score ) :string {
		return ( $score > 95 ? 'A+' :
			( $score > 85 ? 'A' :
				( $score > 70 ? 'B' :
					( $score > 55 ? 'C' :
						( $score > 40 ? 'D' :
							( $score > 20 ? 'E' : 'F' )
						)
					)
				)
			)
		);
	}

	private function statusFromPercentage( int $score ) :string {
		return ( $score > 85 ? 'x' :
			( $score > 80 ? 'h' :
				( $score > 40 ? 'm' : 'l' )
			)
		);
	}
}
