<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\MainwpServerClientActionHandler as H;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\{
	MWPSiteVO,
	SiteOpenUrlBuilder
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class BaseSubPage extends BaseMWP {

	use Traits\SecurityAdminNotRequired;

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonSubPageData();
		return $data;
	}

	protected function getCommonSubPageData() :array {
		$name = self::con()->labels->Name;
		return [
			'vars'    => [
				'menu_topnav' => $this->getMenuTopNavItems(),
			],
			'strings' => [
				'site'            => __( 'Site', 'wp-simple-firewall' ),
				'url'             => CommonDisplayStrings::get( 'url_label' ),
				'issues'          => __( 'Issues', 'wp-simple-firewall' ),
				'status'          => __( 'Status', 'wp-simple-firewall' ),
				'last_sync'       => __( 'Last Sync', 'wp-simple-firewall' ),
				'version'         => CommonDisplayStrings::get( 'version_label' ),
				'connected'       => __( 'Connected', 'wp-simple-firewall' ),
				'st_inactive'     => sprintf( __( '%s plugin is installed but not activated.', 'wp-simple-firewall' ), $name ),
				'st_notinstalled' => sprintf( __( "%s plugin not detected in last sync.", 'wp-simple-firewall' ), $name ),
				'st_notpro'       => sprintf( __( "%s isn't activated on this site.", 'wp-simple-firewall' ), $name ),
				'st_mwpnoton'     => sprintf( __( "%s's MainWP integration isn't enabled for this site.", 'wp-simple-firewall' ), $name ),
				'st_client_older' => sprintf( '%s: %s',
					__( "Version Mismatch", 'wp-simple-firewall' ),
					sprintf( __( "The %s plugin version on the client site is older than this server and must be updated.", 'wp-simple-firewall' ), $name )
				),
				'st_client_newer'     => sprintf( '%s: %s',
					__( "Version Mismatch", 'wp-simple-firewall' ),
					sprintf( __( "The %s plugin version on the client site is newer than this server.", 'wp-simple-firewall' ), $name )
				),
				'st_sync_rqd'         => sprintf( __( '%s plugin needs to sync.', 'wp-simple-firewall' ), $name ),
				'st_version_mismatch' => sprintf( __( '%s plugin versions are out of sync.', 'wp-simple-firewall' ), $name ),
				'st_unknown'          => sprintf( __( "Couldn't determine %s plugin status.", 'wp-simple-firewall' ), $name ),
				'actions'             => [
					H::OP_SYNC       => sprintf( __( 'Sync %s', 'wp-simple-firewall' ), $name ),
					H::OP_ACTIVATE   => sprintf( __( 'Activate %s', 'wp-simple-firewall' ), $name ),
					H::OP_DEACTIVATE => sprintf( __( 'Deactivate %s', 'wp-simple-firewall' ), $name ),
					H::OP_INSTALL    => sprintf( __( 'Install %s', 'wp-simple-firewall' ), $name ),
					H::OP_UPDATE     => sprintf( __( 'Update %s', 'wp-simple-firewall' ), $name ),
					H::OP_LICENSE    => sprintf( __( 'Check %s License', 'wp-simple-firewall' ), $name ),
					H::OP_MWP_ON     => __( 'Switch-On MainWP Integration', 'wp-simple-firewall' ),
				],
			]
		];
	}

	protected function getCurrentTab() :string {
		$req = Services::Request();
		return empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );
	}

	protected function getJumpUrlFor( string $siteID, string $page ) :string {
		return ( new SiteOpenUrlBuilder() )->build( $siteID, $page );
	}

	protected function getMenuTopNavItems() :array {
		return [
			[
				'title'   => __( 'Sites', 'wp-simple-firewall' ),
				'href'    => $this->createInternalExtensionHref( [ 'tab' => TabSitesListing::TAB ] ),
				'tooltip' => __( 'Sites Listing', 'wp-simple-firewall' ),
				'icon'    => 'list alternate outline',
				'active'  => $this->getCurrentTab() === TabSitesListing::TAB,
			]
		];
	}

	protected function getExtensionRootUri() :string {
		$mwp = self::con()->mwpVO->official_extension_data;
		return URL::Build( Services::Request()->getPath(), [
			'page' => $mwp[ 'page' ] ?? 'Extensions-Wp-Simple-Firewall',
		] );
	}

	protected function createInternalExtensionHref( array $params ) :string {
		return URL::Build( $this->getExtensionRootUri(), $params );
	}

	/**
	 * @throws \Exception
	 */
	protected function getSiteByID( int $id ) :MWPSiteVO {
		return MWPSiteVO::LoadByID( $id );
	}
}
