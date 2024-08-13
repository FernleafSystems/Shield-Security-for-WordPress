<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseLookup;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginSetOpt;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\{
	SiteActionActivate,
	SiteActionDeactivate,
	SiteActionInstall,
	SiteActionSync,
	SiteActionUpdate,
	SiteCustomAction
};
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
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonSubPageData();
		return $data;
	}

	protected function getCommonSubPageData() :array {
		return [
			'vars'    => [
				'menu_topnav'  => $this->getMenuTopNavItems(),
				'site_actions' => \array_map(
					function ( $action ) {
						return wp_json_encode( \is_array( $action ) ? $action : [ 'site_action_slug' => $action ] );
					},
					$this->getSiteActions()
				),
			],
			'strings' => [
				'manage'              => __( 'Manage', 'wp-simple-firewall' ),
				'site'                => __( 'Site', 'wp-simple-firewall' ),
				'url'                 => __( 'URL', 'wp-simple-firewall' ),
				'issues'              => __( 'Issues', 'wp-simple-firewall' ),
				'status'              => __( 'Status', 'wp-simple-firewall' ),
				'last_sync'           => __( 'Last Sync', 'wp-simple-firewall' ),
				'last_scan'           => __( 'Last Scan', 'wp-simple-firewall' ),
				'version'             => __( 'Version', 'wp-simple-firewall' ),
				'connected'           => __( 'Connected', 'wp-simple-firewall' ),
				'disconnected'        => __( 'Disconnected', 'wp-simple-firewall' ),
				'with_issues'         => __( 'With Issues', 'wp-simple-firewall' ),
				'needs_update'        => __( 'Needs Update', 'wp-simple-firewall' ),
				'st_inactive'         => __( 'Shield Security plugin is installed but not activated.', 'wp-simple-firewall' ),
				'st_notinstalled'     => __( "Shield Security plugin not detected in last sync.", 'wp-simple-firewall' ),
				'st_notpro'           => __( "ShieldPRO isn't activated on this site.", 'wp-simple-firewall' ),
				'st_mwpnoton'         => __( "Shield's MainWP integration isn't enabled for this site.", 'wp-simple-firewall' ),
				'st_client_older'     => sprintf( '%s: %s',
					__( "Version Mismatch", 'wp-simple-firewall' ),
					__( "The Shield plugin version on the client site is older than this server and must be updated.", 'wp-simple-firewall' )
				),
				'st_client_newer'     => sprintf( '%s: %s',
					__( "Version Mismatch", 'wp-simple-firewall' ),
					__( "The Shield plugin version on the client site is newer than this server.", 'wp-simple-firewall' )
				),
				'st_sync_rqd'         => __( 'Shield Security plugin needs to sync.', 'wp-simple-firewall' ),
				'st_version_mismatch' => __( 'Shield Security plugin versions are out of sync.', 'wp-simple-firewall' ),
				'st_unknown'          => __( "Couldn't determine Shield plugin status.", 'wp-simple-firewall' ),
				'overall_grade'       => __( 'Grade', 'wp-simple-firewall' ),
				'actions'             => [
					'sync'       => __( 'Sync Shield', 'wp-simple-firewall' ),
					'activate'   => __( 'Activate Shield', 'wp-simple-firewall' ),
					'align'      => __( 'Align Shield', 'wp-simple-firewall' ),
					'deactivate' => __( 'Deactivate Shield', 'wp-simple-firewall' ),
					'install'    => __( 'Install Shield', 'wp-simple-firewall' ),
					'update'     => __( 'Update Shield', 'wp-simple-firewall' ),
					'uninstall'  => __( 'Uninstall Shield', 'wp-simple-firewall' ),
					'license'    => __( 'Check ShieldPRO License', 'wp-simple-firewall' ),
					'mwp_on'     => __( 'Switch-On MainWP Integration', 'wp-simple-firewall' ),
				],
			]
		];
	}

	protected function getCurrentTab() :string {
		$req = Services::Request();
		return empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );
	}

	protected function getJumpUrlFor( string $siteID, string $page ) :string {
		return URL::Build( Services::WpGeneral()->getUrl_AdminPage( 'SiteOpen' ), [
			'newWindow'  => 'yes',
			'websiteid'  => $siteID,
			'_opennonce' => wp_create_nonce( 'mainwp-admin-nonce' ),
			'location'   => \base64_encode( \str_replace( Services::WpGeneral()->getAdminUrl(), '', $page ) )
		] );
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

	protected function getSiteActions() :array {
		return [
			'sync'       => SiteActionSync::SLUG,
			'activate'   => SiteActionActivate::SLUG,
			'deactivate' => SiteActionDeactivate::SLUG,
			'install'    => SiteActionInstall::SLUG,
			'update'     => SiteActionUpdate::SLUG,
			'license'    => [
				'site_action_slug'   => SiteCustomAction::SLUG,
				'site_action_params' => [
					'sub_action_slug' => LicenseLookup::SLUG,
				],
			],
			'mwp_on'     => [
				'site_action_slug'   => SiteCustomAction::SLUG,
				'site_action_params' => [
					'sub_action_slug'   => PluginSetOpt::SLUG,
					'sub_action_params' => [
						'opt_key'   => 'enable_mainwp',
						'opt_value' => 'Y',
					],
				],
			],
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

	protected function getSites() :string {
		$mwp = self::con()->mwpVO;
		return apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
	}

	/**
	 * @throws \Exception
	 */
	protected function getSiteByID( int $id ) :MWPSiteVO {
		return MWPSiteVO::LoadByID( $id );
	}
}