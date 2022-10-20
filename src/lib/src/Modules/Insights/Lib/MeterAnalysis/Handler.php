<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Handler {

	use ModConsumer;

	public function buildAllMeterComponents() :array {
		return array_map(
			function ( string $class ) {
				/** @var MeterBase $class */
				try {
					return $this->buildMeterComponents( $class::SLUG );
				}
				catch ( \Exception $e ) {
					return 'meter component error:'.$e->getMessage();
				}
			},
			$this->enumMeters()
		);
	}

	/**
	 * @throws \Exception
	 */
	public function buildMeterComponents( string $meter ) :array {
		return $this->getMeter( $meter )->buildMeterComponents();
	}

	/**
	 * @return MeterBase|mixed
	 * @throws \Exception
	 */
	public function getMeter( string $meter ) {
		$this->exists( $meter );
		$class = $this->enumMeters()[ $meter ];
		return ( new $class() )->setCon( $this->getCon() );
	}

	public function enumMeters() :array {
		$meters = [
			MeterIntegrity::class,
			MeterIpBlocking::class,
			MeterAssets::class,
			MeterScans::class,
			MeterFirewall::class,
			MeterLockdown::class,
			MeterLoginProtection::class,
			MeterUsers::class,
			MeterSpam::class,
		];
		$enum = [];
		foreach ( $meters as $meter ) {
			/** @var MeterBase $meter */
			$enum[ $meter::SLUG ] = $meter;
		}
		return $enum;
	}

	/**
	 * @throws \Exception
	 */
	protected function exists( string $meter ) :bool {
		if ( empty( $this->enumMeters()[ $meter ] ) ) {
			throw new \Exception( 'No such meter exists: '.sanitize_key( $meter ) );
		}
		return true;
	}
}