<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\TabRender;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MainwpExtensionTableSites;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MainwpSiteAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseTab extends BaseRender {

	const TAB_SLUG = '';

	protected function getBaseData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getBaseData(),
			[
				'ajax' => [
					'actions' => json_encode( (object)$this->getAjaxActionsData() )
				],
				'vars' => [
					'menu_topnav' => $this->getMenuTopNavItems(),
				]
			]
		);
	}

	protected function getCurrentTab() :string {
		$req = Services::Request();
		return empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );
	}

	protected function getMenuTopNavItems() :array {
		return [
			[
				'title'  => 'Sites Dashboard',
				'href'   => $this->createInternalExtensionHref( [ 'tab' => SitesList::TAB_SLUG ] ),
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
		return add_query_arg( [
			'page' => $req->query( 'page' )
		], $req->getPath() );
	}

	protected function createInternalExtensionHref( array $params ) :string {
		return add_query_arg( $params, $this->getRootUri() );
	}

	protected function getSites() :string {
		$mwp = $this->getCon()->mwpVO;
		return apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
	}

	protected function getSiteByID( int $id ) :MWPSiteVO {
		return MWPSiteVO::LoadByID( $id );
	}
}