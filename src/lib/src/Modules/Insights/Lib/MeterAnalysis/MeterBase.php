<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BaseTemplateRenderer;
use FernleafSystems\Wordpress\Services\Services;

abstract class MeterBase extends BaseTemplateRenderer {

	const SLUG = '';

	public function buildMeterComponents() :array {
		return $this->postProcessMeter( [
			'title'       => $this->title(),
			'subtitle'    => $this->subtitle(),
			'description' => $this->description(),
			'components'  => $this->buildComponents(),
		] );
	}

	protected function postProcessMeter( array $meter ) :array {
		$hasCritical = false;
		$protected = 0;
		$totalWeight = 0;
		foreach ( $meter[ 'components' ] as $key => $component ) {
			$totalWeight += $component[ 'weight' ];
			if ( $component[ 'protected' ] ) {
				$protected += $component[ 'weight' ];
			}

			if ( !isset( $component[ 'is_critical' ] ) ) {
				$component[ 'is_critical' ] = false;
			}
			$meter[ 'components' ][ $key ] = $component;

			$hasCritical = $hasCritical || $component[ 'is_critical' ];
		}

		foreach ( $meter[ 'components' ] as &$component ) {
			$component[ 'weight_as_percent' ] = (int)round( 100*$component[ 'weight' ]/$totalWeight );
		}

		// Put critical components to the top of the list.
		uasort( $meter[ 'components' ], function ( $a, $b ) {
			if ( $a[ 'is_critical' ] === $b[ 'is_critical' ] ) {
				return 0;
			}
			return $a[ 'is_critical' ] ? -1 : 1;
		} );

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

		$meter[ 'has_critical' ] = $hasCritical;

		return $meter;
	}

	protected function buildComponents() :array {
		return ( new Components() )
			->setCon( $this->getCon() )
			->getComponents( $this->getComponentSlugs() );
	}

	protected function getComponentSlugs() :array {
		return [];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getCon()->getModule_Plugin()->getUIHandler()->getBaseDisplayData(),
			[
				'strings' => [
					'title'            => sprintf( '%s: %s', __( 'Analysis', 'wp-simple-firewall' ), $this->title() ),
					'scores_footnote1' => __( 'Scores are an approximate weighting for each component.', 'wp-simple-firewall' ),
					'scores_footnote2' => __( 'As each issue is resolved the overall score will improve, up to 100%.', 'wp-simple-firewall' ),
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

	protected function subtitle() :string {
		return 'no subtitle';
	}

	protected function description() :array {
		return [ 'no description' ];
	}

	protected function getTemplateBaseDir() :string {
		return '/wpadmin_pages/insights/overview/progress_meter/analysis';
	}

	protected function getTemplateStub() :string {
		return 'standard';
	}
}