<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class FormScanResultsDisplayOptions extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_form_scan_results_display_options';

	protected function buildCanvasTitle() :string {
		return __( 'Adjust Scan Results Display', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( Components\Scans\FormScanResultsDisplayOptions::class );
	}
}