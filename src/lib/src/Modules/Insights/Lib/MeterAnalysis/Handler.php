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

	public function renderDashboardMeters() :string {
		$mod = $this->getMod();
		return $mod->getRenderer()
				   ->setTemplate( '/wpadmin_pages/insights/overview/progress_meter/progress_meters.twig' )
				   ->setRenderData( [
					   'ajax'    => [
						   'render_meter_analysis' => $mod->getAjaxActionData( 'render_meter_analysis', true ),
					   ],
					   'strings' => [
						   'analysis' => __( 'Analysis', 'wp-simple-firewall' ),
					   ],
					   'vars'    => [
						   'progress_meters' => $this->buildAllMeterComponents()
					   ],
				   ] )
				   ->render();
	}

	private function buildAllMeterComponents() :array {
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
	protected function getMeter( string $meter ) {
		$this->exists( $meter );
		$class = $this->enumMeters()[ $meter ];
		return ( new $class() )->setCon( $this->getCon() );
	}

	public function enumMeters() :array {
		$meters = [
			MeterIntegrity::class,
			MeterAssets::class,
			MeterIpBlocking::class,
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