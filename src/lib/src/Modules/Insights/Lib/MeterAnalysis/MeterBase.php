<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BaseTemplateRenderer;
use FernleafSystems\Wordpress\Services\Services;

abstract class MeterBase extends BaseTemplateRenderer {

	const SLUG = '';

	public function buildMeterComponents() :array {
		return $this->postProcessMeter( [
			'title'      => $this->title(),
			'components' => $this->buildComponents()
		] );
	}

	protected function postProcessMeter( array $meter ) :array {
		$protected = 0;
		$totalWeight = 0;
		foreach ( $meter[ 'components' ] as $component ) {
			$totalWeight += $component[ 'weight' ];
			if ( $component[ 'protected' ] ) {
				$protected += $component[ 'weight' ];
			}
		}

		foreach ( $meter[ 'components' ] as &$component ) {
			$component[ 'weight_as_percent' ] = (int)round( 100*$component[ 'weight' ]/$totalWeight );
		}

		$percentage = (int)round( 100*$protected/$totalWeight );
		$meter[ 'totals' ] = [
			'protected'  => $protected,
			'max_weight' => $totalWeight,
			'percentage' => $percentage,
		];
		$meter[ 'rgbs' ] = [
			( 100 - $percentage )*128/100,
			( $percentage )*128/100,
			0
		];

		return $meter;
	}

	protected function buildComponents() :array {
		return [];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getCon()->getModule_Plugin()->getUIHandler()->getBaseDisplayData(),
			[
				'strings' => [
					'title' => sprintf( '%s: %s', __( 'Analysis', 'wp-simple-firewall' ), $this->title() ),
				],
				'vars'    => [
					'components' => $this->buildMeterComponents()[ 'components' ]
				]
			],
			$this->getMeterRenderData()
		);
	}

	protected function getMeterRenderData() :array {
		return [];
	}

	protected function title() :string {
		return 'no title';
	}

	protected function getTemplateBaseDir() :string {
		return '/wpadmin_pages/insights/overview/progress_meter/analysis';
	}

	protected function getTemplateStub() :string {
		return static::SLUG;
	}
}