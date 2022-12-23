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

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		return array_merge( parent::getAllRenderDataArrays(), [
			25 => $this->getCommonMwpData()
		] );
	}

	protected function getCommonMwpData() :array {
		ob_start();
		do_action( 'mainwp_pageheader_extensions', $this->getCon()->getRootFile() );
		$mainwpHeader = ob_get_clean();
		ob_start();
		do_action( 'mainwp_pagefooter_extensions', $this->getCon()->getRootFile() );
		$mainwpFooter = ob_get_clean();
		return [
			'content' => [
				'mainwp_header' => $mainwpHeader,
				'mainwp_footer' => $mainwpFooter,
			],
		];
	}

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
			$bodyToRender = $this->action_data[ 'current_tab' ];
			if ( !in_array( $bodyToRender, $this->enumPages() ) ) {
				throw new ActionException( 'Not a supported tab: '.sanitize_key( $bodyToRender ) );
			}
		}

		return [
			'content' => [
				'page_body' => $con->action_router->render( $bodyToRender ),
			],
		];
	}

	/**
	 * @return BaseMWP[]
	 */
	protected function enumPages() :array {
		return [
			SitesListing::SLUG,
			SiteManageFrame::SLUG,
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'current_tab',
		];
	}
}