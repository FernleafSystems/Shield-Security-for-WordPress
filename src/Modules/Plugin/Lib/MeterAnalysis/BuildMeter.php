<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BuildMeter {

	use PluginControllerConsumer;

	public const STATUS_XHIGH = 'x';
	public const STATUS_HIGH = 'h';
	public const STATUS_MEDIUM = 'm';
	public const STATUS_LOW = 'l';
	public const STATUS_RGBs = [
		self::STATUS_XHIGH  => [ 47, 192, 10 ],
		self::STATUS_HIGH   => [ 16, 128, 0 ],
		self::STATUS_MEDIUM => [ 200, 150, 10 ],
		self::STATUS_LOW    => [ 200, 50, 10 ],
	];

	public function build( string $meterClass ) :array {
		/** @var Meter\MeterBase $meter */
		$meter = new $meterClass();
		return $this->postProcessMeter( [
			'title'       => $meter->title(),
			'subtitle'    => $meter->subtitle(),
			'warning'     => $meter->warning(),
			'description' => $meter->description(),
			'components'  => $meter->buildComponents(),
		] );
	}

	protected function postProcessMeter( array $meter ) :array {
		$hasCritical = false;
		$totalScore = 0;
		$totalWeight = 0;

		foreach ( $meter[ 'components' ] as $key => $component ) {
			if ( empty( $component[ 'is_applicable' ] ) ) {
				unset( $meter[ 'components' ][ $key ] );
			}
			else {
				$totalScore += $component[ 'score' ];
				$totalWeight += $component[ 'weight' ];
				$hasCritical = $hasCritical || $component[ 'is_critical' ];
			}
		}

		foreach ( $meter[ 'components' ] as &$comp ) {
			$comp[ 'score_as_percent' ] = (int)\round( 100*$comp[ 'score' ]/$totalWeight );
			$comp[ 'weight_as_percent' ] = (int)\round( 100*$comp[ 'weight' ]/$totalWeight );
		}

		// Put critical components to the top of the list.
		\uasort( $meter[ 'components' ], function ( $a, $b ) {
			if ( $a[ 'is_critical' ] === $b[ 'is_critical' ] ) {
				return 0;
			}
			return $a[ 'is_critical' ] ? -1 : 1;
		} );

		$percentage = (int)\round( 100*$totalScore/$totalWeight );
		$meter[ 'totals' ] = [
			'score'        => $totalScore,
			'max_weight'   => $totalWeight,
			'percentage'   => $percentage,
			'letter_score' => $this->letterScoreFromPercentage( $percentage ),
		];
		$meter[ 'status' ] = $this->getStatus( $percentage );
		$meter[ 'rgbs' ] = self::STATUS_RGBs[ $this->getStatus( $percentage ) ];
		$meter[ 'has_critical' ] = $hasCritical || !empty( $meter[ 'warning' ] );

		return $meter;
	}

	protected function letterScoreFromPercentage( int $score ) :string {
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

	protected function getStatus( int $score ) :string {
		return ( $score > 85 ? self::STATUS_XHIGH :
			( $score > 70 ? self::STATUS_HIGH :
				( $score > 40 ? self::STATUS_MEDIUM : self::STATUS_LOW )
			)
		);
	}
}