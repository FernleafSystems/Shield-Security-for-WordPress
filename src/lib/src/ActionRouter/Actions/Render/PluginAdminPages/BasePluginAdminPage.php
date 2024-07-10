<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;

abstract class BasePluginAdminPage extends BaseRender {

	public const TEMPLATE = '/wpadmin/plugin_pages/base_inner_page.twig';

	protected function getPageContextualHrefs() :array {
		return [];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [];
	}

	protected function getInnerPageTitle() :string {
		return '';
	}

	protected function getInnerPageSubTitle() :string {
		return '';
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonAdminPageRenderData();
		return $data;
	}

	protected function getCommonAdminPageRenderData() :array {
		$urls = self::con()->plugin_urls;

		$hrefs = $this->getPageContextualHrefs();
		if ( self::con()->comps->sec_admin->hasActiveSession() ) {
			$hrefs[] = [
				'title' => __( 'End Security Admin Session', 'wp-simple-firewall' ),
				'href'  => $urls->noncedPluginAction( SecurityAdminAuthClear::class, $urls->adminHome() ),
			];
		}
		$hrefs[] = $this->getPageContextualHrefs_Help();

		return [
			'hrefs' => [
				'breadcrumbs'                 => $this->getBreadCrumbs(),
				'inner_page_contextual_hrefs' => \array_filter( $hrefs ),
			],
		];
	}

	protected function getBreadCrumbs() :array {
		return ( new BuildBreadCrumbs() )->current();
	}
}