<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Meters\Analysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Handler;

class MeterAnalysis extends OffCanvasBase {

	public const SLUG = 'offcanvas_meter_analysis';

	private $meterComponents;

	protected function buildCanvasBody() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( Analysis::SLUG, [
						'meter_components' => $this->getMeterComponents()
					] );
	}

	protected function buildCanvasTitle() :string {
		return $this->getMeterComponents()[ 'title' ];
	}

	private function getMeterComponents() :array {
		if ( !isset( $this->meterComponents ) ) {
			$this->meterComponents = ( new Handler() )
				->setMod( $this->getMod() )
				->getMeter( $this->action_data[ 'meter' ] )
				->buildMeterComponents();
		}
		return $this->meterComponents;
	}
}