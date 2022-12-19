<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Services\Services;

class ExtensionPageContainer extends BaseMWP {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mainwp_page_extension';
	public const TEMPLATE = '/integration/mainwp/page_extension.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();

		if ( !$con->isPremiumActive() ) {
			$bodyToRender = NotShieldPro::SLUG;
		}
		elseif ( Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ) {
			$bodyToRender = ShieldOutOfDate::SLUG;
		}
		elseif ( !Controller::isMainWPServerVersionSupported() ) {
			$bodyToRender = MwpOutOfDate::SLUG;
		}
		else {
			$currentTab = $this->action_data[ 'current_tab' ];

			/** @var BaseMWP[] $pages */
			$pages = [
				SitesListing::SLUG,
				SiteManageFrame::SLUG,
			];

			foreach ( $pages as $page ) {
				if ( $currentTab == $page::SLUG ) {
					$bodyToRender = new $page();
					break;
				}
			}

			if ( empty( $bodyToRender ) ) {
				throw new ActionException( 'Not a supported tab: '.$currentTab );
			}
		}

		return [
			'content' => [
				'page_body' => $con->action_router->render( $bodyToRender ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'current_tab',
		];
	}
}