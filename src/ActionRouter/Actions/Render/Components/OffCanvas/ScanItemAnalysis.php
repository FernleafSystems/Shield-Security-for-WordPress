<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container;

class ScanItemAnalysis extends OffCanvasBase {

	public const SLUG = 'offcanvas_scanitemanalysis';

	protected function buildCanvasTitle() :string {
		return sprintf( '%s: %s', __( 'IP Analysis', 'wp-simple-firewall' ), $this->action_data[ 'ip' ] );
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( Container::class, [
			'ip' => $this->action_data[ 'ip' ]
		] );
	}
}