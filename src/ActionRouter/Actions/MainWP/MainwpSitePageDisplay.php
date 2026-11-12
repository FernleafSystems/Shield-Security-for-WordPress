<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayDynamic;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP\TabManageSitePage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

/**
 * The MainWP dashboard's manage-site iframe. The page it shows is fixed; only the site comes from the request.
 */
class MainwpSitePageDisplay extends MainwpBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'mwp_site_page_display';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$siteID = $this->fields()->positiveInt( 'site_id' );

		$this->setResponse( self::con()->action_router->action( FullPageDisplayDynamic::class, [
			'render_slug' => TabManageSitePage::SLUG,
			'render_data' => [
				'site_id' => $siteID,
			],
		] ) );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site_id',
		];
	}
}
