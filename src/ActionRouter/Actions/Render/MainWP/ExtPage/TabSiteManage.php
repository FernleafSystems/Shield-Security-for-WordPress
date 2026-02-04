<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayDynamic;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP\TabManageSitePage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class TabSiteManage extends BaseSubPage {

	public const SLUG = 'mainwp_page_site_manage_frame';
	public const TEMPLATE = '/integration/mainwp/pages/site.twig';
	public const TAB = 'manage_site';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'hrefs'   => [
				'page' => $con->plugin_urls->noncedPluginAction(
					FullPageDisplayDynamic::class,
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
				'page' => \base64_encode( $this->runAction() )
			],
		];
	}

	protected function runAction() :string {
		return 'nothing';
		try {
			return self::con()->action_router->action( Render::class, [
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
		return (int)$this->action_data[ 'site_id' ] ?? 0;
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