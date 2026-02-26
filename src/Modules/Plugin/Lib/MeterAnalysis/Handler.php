<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Handler {

	use PluginControllerConsumer;

	public const METERS = [
		Meter\MeterOverallConfig::SLUG => Meter\MeterOverallConfig::class,
		Meter\MeterSummary::SLUG       => Meter\MeterSummary::class,
		Meter\MeterIpBlocking::SLUG    => Meter\MeterIpBlocking::class,
		Meter\MeterAssets::SLUG        => Meter\MeterAssets::class,
		Meter\MeterScans::SLUG         => Meter\MeterScans::class,
		Meter\MeterLockdown::SLUG      => Meter\MeterLockdown::class,
		Meter\MeterLogin::SLUG         => Meter\MeterLogin::class,
		Meter\MeterUsers::SLUG         => Meter\MeterUsers::class,
		Meter\MeterSpam::SLUG          => Meter\MeterSpam::class,
	];

	private static array $BuiltMeters = [];
	private static array $BuiltMetersByChannel = [];

	public function __construct() {
	}

	/**
	 * array keys are the Meter::SLUG
	 */
	public function getAllMeters() :array {
		foreach ( self::METERS as $meterClass ) {
			try {
				$this->getMeter( $meterClass );
			}
			catch ( \Exception $e ) {
			}
		}
		return self::$BuiltMeters;
	}

	/**
	 * @param string|class-string<Meter\MeterBase> $meterClassOrSlug
	 * @throws \Exception
	 */
	public function getMeter(
		string $meterClassOrSlug,
		bool $orderComponentsByWeight = true,
		?string $channel = null
	) :array {

		if ( isset( self::METERS[ $meterClassOrSlug ] ) ) {
			$theSlug = $meterClassOrSlug;
		}
		elseif ( \in_array( $meterClassOrSlug, self::METERS ) ) {
			$theSlug = $meterClassOrSlug::SLUG;
		}
		else {
			throw new \Exception( 'Invalid Meter Class or Slug: '.$meterClassOrSlug );
		}

		$normalizedChannel = $this->normalizeChannel( $channel );

		if ( $normalizedChannel === null ) {
			if ( empty( self::$BuiltMeters[ $theSlug ] ) ) {
				self::$BuiltMeters[ $theSlug ] = ( new BuildMeter() )->build( self::METERS[ $theSlug ] );
			}
			$meter = self::$BuiltMeters[ $theSlug ];
		}
		else {
			if ( empty( self::$BuiltMetersByChannel[ $theSlug ][ $normalizedChannel ] ) ) {
				self::$BuiltMetersByChannel[ $theSlug ][ $normalizedChannel ] = ( new BuildMeter() )
					->build( self::METERS[ $theSlug ], $normalizedChannel );
			}
			$meter = self::$BuiltMetersByChannel[ $theSlug ][ $normalizedChannel ];
		}
		if ( $orderComponentsByWeight ) {
			\usort( $meter[ 'components' ], function ( $a, $b ) {
				$wA = $a[ 'weight' ];
				$wB = $b[ 'weight' ];
				return ( $wA === $wB ) ? 0 : ( $wA > $wB ? -1 : 1 );
			} );
		}

		return $meter;
	}

	private function normalizeChannel( ?string $channel ) :?string {
		return Component\Base::assertValidChannel(
			$channel,
			'Invalid channel requested for meter retrieval: %s'
		);
	}
}
