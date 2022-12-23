<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainwpExtensionTableSites;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainwpSiteAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class BaseSubPage extends BaseMWP {

	use Traits\SecurityAdminNotRequired;

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		return array_merge( parent::getAllRenderDataArrays(), [
			25 => $this->getCommonSubPageData()
		] );
	}

	protected function getCommonSubPageData() :array {
		return [
			'ajax' => [
				'actions' => $this->getAjaxActionsData()
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
				'title'  => __( 'Sites Dashboard', 'wp-simple-firewall' ),
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