<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainwpExtensionTableSites;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainwpSiteAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class BaseMWP extends BaseRender {

	use Traits\SecurityAdminNotRequired;

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
		$mainwpHeader = ob_get_contents();
		ob_clean();
		do_action( 'mainwp_pagefooter_extensions', $this->getCon()->getRootFile() );
		$mainwpFooter = ob_get_clean();
		return [
			'content' => [
				'mainwp_header' => $mainwpHeader,
				'mainwp_footer' => $mainwpFooter,
			],
		];
	}

	protected function getCurrentTab() :string {
		$req = Services::Request();
		return empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );
	}

	protected function getMenuTopNavItems() :array {
		return [
			[
				'title'  => 'Sites Dashboard',
				'href'   => $this->createInternalExtensionHref( [ 'tab' => SitesListing::SLUG ] ),
				'icon'   => 'globe',
				'active' => $this->getCurrentTab() === 'sites',
			]
		];
	}

	protected function getAjaxActionsData() :array {
		return [
			'site_action' => ActionData::Build( MainwpSiteAction::SLUG ),
			'ext_table'   => ActionData::Build( MainwpExtensionTableSites::SLUG ),
		];
	}

	protected function getRootUri() :string {
		$req = Services::Request();
		return URL::Build( $req->getPath(), [
			'page' => $req->query( 'page' )
		] );
	}

	protected function createInternalExtensionHref( array $params ) :string {
		return URL::Build( $this->getRootUri(), $params );
	}

	protected function getSites() :string {
		$mwp = $this->getCon()->mwpVO;
		return apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
	}

	protected function getSiteByID( int $id ) :MWPSiteVO {
		return MWPSiteVO::LoadByID( $id );
	}
}