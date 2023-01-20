<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FileDownload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\StandardFullPageDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP\TabManageSitePage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class TabSiteManage extends BaseSubPage {

	public const SLUG = 'mainwp_page_site_manage_frame';
	public const TEMPLATE = '/integration/mainwp/pages/site.twig';
	public const TAB = 'manage_site';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$mwp = $con->mwpVO;
		$WP = Services::WpGeneral();
		$req = Services::Request();

		return [
			'hrefs'   => [
				'page' => $con->plugin_urls->noncedPluginAction(
					StandardFullPageDisplay::SLUG,
					Services::WpGeneral()->getAdminUrl(),
					[
						'render_slug' => TabManageSitePage::SLUG,
						'render_data' => [
							'site_id' => $this->getActiveSiteID()
						],
					]
				)
			],
			'content' => [
				'page' => base64_encode( $this->runAction() )
			],
		];
	}

	protected function getAjaxActionsData() :array {
		$renderManageSiteAjax = ActionData::Build( 'render_manage_site', true, [
			'site_id' => Services::Request()->query( 'site_id' )
		] );

		$data = parent::getAjaxActionsData();
		$data[ 'render_manage_site' ] = $renderManageSiteAjax;
		return $data;
	}

	protected function runAction() :string {
		return 'nothing';
		try {
			return $this->getCon()->action_router->action( Render::SLUG, [
				'render_action_slug' => TabManageSitePage::SLUG,
				'render_action_data' => [
					'site_id' => $this->getActiveSiteID(),
				],
			] )->action_response_data[ 'render_output' ];
		}
		catch ( ActionException $e ) {
			error_log( $e->getMessage() );
			die( $e->getMessage() );
		}
	}

	protected function getActiveSiteID() :int {
		return (int)Services::Request()->query( 'site_id' );
	}

	protected function getMenuTopNavItems() :array {
		$items = parent::getMenuTopNavItems();
		$site = $this->getSiteByID( $this->getActiveSiteID() );
		$items[] = [
			'title'   => $site->name,
			'tooltip' => $site->url,
			'href'    => $this->createInternalExtensionHref( [
				'tab'     => TabSiteManage::TAB,
				'site_id' => $site->id,
			] ),
			'icon'    => 'globe',
			'active'  => true
		];
		return $items;
	}
}