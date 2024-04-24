<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\Analysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;

class MeterAnalysis extends OffCanvasBase {

	public const SLUG = 'offcanvas_meter_analysis';

	private $meterComponents;

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( Analysis::class, [
			'meter_components' => $this->getMeterComponents()
		] );
	}

	protected function buildCanvasTitle() :string {
		return $this->getMeterComponents()[ 'title' ];
	}

	private function getMeterComponents() :array {
		return $this->meterComponents ?? $this->meterComponents = ( new Handler() )->getMeter( $this->action_data[ 'meter' ] );
	}
}