<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class BasePluginAdminPage extends BaseRender {

	protected function getPageContextualHrefs() :array {
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
		if ( self::con()->getModule_SecAdmin()->getSecurityAdminController()->hasActiveSession() ) {
			$hrefs[] = [
				'text' => __( 'End Security Admin Session', 'wp-simple-firewall' ),
				'href' => $urls->noncedPluginAction( SecurityAdminAuthClear::class, $urls->adminHome() ),
			];
		}

		return [
			'hrefs' => [
				'inner_page_contextual_hrefs' => $hrefs,
			],
		];
	}
}