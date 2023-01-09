<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Handler {

	use PluginControllerConsumer;

	public const METERS = [
		Meter\MeterOverallConfig::SLUG => Meter\MeterOverallConfig::class,
		Meter\MeterSummary::SLUG       => Meter\MeterSummary::class,
		Meter\MeterIpBlocking::SLUG    => Meter\MeterIpBlocking::class,
		Meter\MeterAssets::SLUG        => Meter\MeterAssets::class,
		Meter\MeterScans::SLUG         => Meter\MeterScans::class,
		Meter\MeterFirewall::SLUG      => Meter\MeterFirewall::class,
		Meter\MeterLockdown::SLUG      => Meter\MeterLockdown::class,
		Meter\MeterLogin::SLUG         => Meter\MeterLogin::class,
		Meter\MeterUsers::SLUG         => Meter\MeterUsers::class,
		Meter\MeterSpam::SLUG          => Meter\MeterSpam::class,
	];

	private static $BuiltMeters;

	public function __construct() {
		if ( !is_array( self::$BuiltMeters ) ) {
			self::$BuiltMeters = [];
		}
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

	public function getMeter( string $meterClassOrSlug ) :array {

		if ( isset( self::METERS[ $meterClassOrSlug ] ) ) {
			$theSlug = $meterClassOrSlug;
		}
		elseif ( in_array( $meterClassOrSlug, self::METERS ) ) {
			/** @var Meter\MeterBase $meterClassOrSlug */
			$theSlug = $meterClassOrSlug::SLUG;
		}
		else {
			throw new \Exception( 'Invalid Meter Class or Slug: '.$meterClassOrSlug );
		}

		if ( empty( self::$BuiltMeters[ $theSlug ] ) ) {
			self::$BuiltMeters[ $theSlug ] = ( new BuildMeter() )
				->setCon( $this->getCon() )
				->build( self::METERS[ $theSlug ] );
		}

		return self::$BuiltMeters[ $theSlug ];
	}
}