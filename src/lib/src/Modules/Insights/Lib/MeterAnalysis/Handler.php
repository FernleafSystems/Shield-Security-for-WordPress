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
		$meters = $this->renderMeters();
		$primary = $meters[ MeterIntegrity::SLUG ];
		unset( $meters[ MeterIntegrity::SLUG ] );
		return $this->getMod()
					->getRenderer()
					->setTemplate( '/wpadmin_pages/insights/overview/progress_meter/progress_meters.twig' )
					->setRenderData( [
						'content' => [
							'primary_meter' => $primary,
							'meters'        => $meters
						],
					] )
					->render();
	}

	private function renderMeters() :array {
		$con = $this->getCon();
		$renderer = $this->getMod()
						 ->getRenderer()
						 ->setTemplate( '/wpadmin_pages/insights/overview/progress_meter/meter_card.twig' );
		$renderData = [
			'strings' => [
				'analysis' => __( 'Analysis', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'analysis' => $con->svgs->raw( 'bootstrap/clipboard2-data-fill.svg' ),
				],
			],
		];

		$meters = [];
		foreach ( $this->buildAllMeterComponents() as $meterSlug => $meter ) {
			$renderData[ 'vars' ] = [
				'meter_slug' => $meterSlug,
				'meter'      => $meter,
			];
			$meters[ $meterSlug ] = $renderer
				->setRenderData( $renderData )
				->render();
		}
		return $meters;
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