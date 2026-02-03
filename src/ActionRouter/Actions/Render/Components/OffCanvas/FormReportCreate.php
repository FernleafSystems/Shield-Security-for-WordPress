<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\FormCreateReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class FormReportCreate extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_form_report_create';

	protected function buildCanvasTitle() :string {
		return __( 'Create New Report', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( FormCreateReport::class );
	}
}