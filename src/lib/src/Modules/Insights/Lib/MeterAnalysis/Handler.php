<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Handler {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function renderAnalysis( string $meter ) :string {
		$this->exists( $meter );
		return $this->getMeter( $meter )->render();
	}

	/**
	 * @throws \Exception
	 */
	public function buildAllMeterComponents() :array {
		return array_map(
			function ( string $class ) {
				/** @var MeterBase $class */
				return $this->buildMeterComponents( $class::SLUG );
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
	protected function getMeter( string $meter ) {
		$this->exists( $meter );
		$class = $this->enumMeters()[ $meter ];
		return ( new $class() )->setCon( $this->getCon() );
	}

	public function enumMeters() :array {
		$meters = [
			MeterSiteIntegrity::class,
			MeterAssets::class,
			MeterFirewall::class,
			MeterIpBlocking::class,
			MeterLockdown::class,
			MeterScans::class,
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