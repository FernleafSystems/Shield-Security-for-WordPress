<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\IpAnalyse\Container;

class ScanItemAnalysis extends OffCanvasBase {

	const SLUG = 'offcanvas_scanitemanalysis';

	protected function buildCanvasTitle() :string {
		return sprintf( '%s: %s', __( 'IP Analysis', 'wp-simple-firewall' ), $this->action_data[ 'ip' ] );
	}

	protected function buildCanvasBody() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render(
						Container::SLUG,
						[
							'ip' => $this->action_data[ 'ip' ]
						]
					);
	}
}