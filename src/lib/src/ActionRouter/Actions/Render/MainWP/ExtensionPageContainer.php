<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\BaseMWP;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\MwpOutOfDate;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\NotShieldPro;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\ShieldOutOfDate;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\TabSiteManage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\TabSitesListing;
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
		return \array_merge( parent::getAllRenderDataArrays(), [
			25 => $this->getCommonMwpData()
		] );
	}

	protected function getCommonMwpData() :array {
		\ob_start();
		do_action( 'mainwp_pageheader_extensions', self::con()->getRootFile() );
		$mainwpHeader = \ob_get_clean();

		\ob_start();
		do_action( 'mainwp_pagefooter_extensions', self::con()->getRootFile() );
		$mainwpFooter = \ob_get_clean();

		return [
			'content' => [
				'mainwp_header' => $mainwpHeader,
				'mainwp_footer' => $mainwpFooter,
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();

		if ( !$con->isPremiumActive() ) {
			$bodyToRender = NotShieldPro::class;
		}
		elseif ( Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ) {
			$bodyToRender = ShieldOutOfDate::class;
		}
		elseif ( !Controller::isMainWPServerVersionSupported() ) {
			$bodyToRender = MwpOutOfDate::class;
		}
		else {
			$bodyToRender = $this->enumPages()[ $this->action_data[ 'current_tab' ] ] ?? null;
			if ( empty( $bodyToRender ) ) {
				throw new ActionException( 'Not a supported tab: '.sanitize_key( $this->action_data[ 'current_tab' ] ) );
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
		$enum = [];
		foreach ( $this->getPageRenderers() as $pageRenderer ) {
			$enum[ $pageRenderer::TAB ] = $pageRenderer::SLUG;
		}
		return $enum;
	}

	/**
	 * @return BaseMWP[]
	 */
	protected function getPageRenderers() :array {
		return [
			TabSitesListing::class,
			TabSiteManage::class,
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'current_tab',
		];
	}
}