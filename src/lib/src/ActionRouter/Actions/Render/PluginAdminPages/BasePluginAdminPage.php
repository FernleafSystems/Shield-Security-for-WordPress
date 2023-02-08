<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class BasePluginAdminPage extends BaseRender {

	protected function getPageContextualHrefs() :array {
		return [];
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
		$con = $this->getCon();
		$urls = $con->plugin_urls;

		$hrefs = $this->getPageContextualHrefs();
		if ( $con->isPluginAdmin() && $con->getModule_SecAdmin()->getSecurityAdminController()->isEnabledSecAdmin() ) {
			$hrefs[] = [
				'text' => __( 'End Security Admin Session', 'wp-simple-firewall' ),
				'href' => $urls->noncedPluginAction( SecurityAdminAuthClear::SLUG, $urls->adminHome() ),
			];
		}

		return [
			'hrefs' => [
				'inner_page_contextual_hrefs' => $hrefs,
			],
		];
	}
}