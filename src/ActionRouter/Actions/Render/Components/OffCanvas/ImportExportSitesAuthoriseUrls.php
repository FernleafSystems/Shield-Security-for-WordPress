<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport\FormAuthoriseUrls;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ImportExportSitesAuthoriseUrls extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_import_export_sites_authorise_urls';

	protected function buildCanvasTitle() :string {
		return __( 'Add Authorised URLs', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( FormAuthoriseUrls::class );
	}
}
