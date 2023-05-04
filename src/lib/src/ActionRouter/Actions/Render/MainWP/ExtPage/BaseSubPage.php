<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseLookup;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpExtensionTableSites;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\{
	MainwpServerClientActionHandler,
	SiteActionActivate,
	SiteActionDeactivate,
	SiteActionInstall,
	SiteActionSync,
	SiteActionUpdate,
	SiteCustomAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\LoadShieldSyncData;
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
			'ajax'    => [
				'actions' => $this->getAjaxActionsData(),
			],
			'vars'    => [
				'menu_topnav'  => $this->getMenuTopNavItems(),
				'site_actions' => array_map(
					function ( $action ) {
						return wp_json_encode( is_array( $action ) ? $action : [ 'site_action_slug' => $action ] );
					},
					$this->getSiteActions()
				),
			],
			'strings' => [
				'manage'              => __( 'Manage', 'wp-simple-firewall' ),
				'actions'             => __( 'Actions', 'wp-simple-firewall' ),
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
				'act_sync'            => __( 'Sync Shield', 'wp-simple-firewall' ),
				'act_activate'        => __( 'Activate Shield', 'wp-simple-firewall' ),
				'act_align'           => __( 'Align Shield', 'wp-simple-firewall' ),
				'act_deactivate'      => __( 'Deactivate Shield', 'wp-simple-firewall' ),
				'act_install'         => __( 'Install Shield', 'wp-simple-firewall' ),
				'act_update'          => __( 'Update Shield', 'wp-simple-firewall' ),
				'act_uninstall'       => __( 'Uninstall Shield', 'wp-simple-firewall' ),
				'act_license'         => __( 'Check ShieldPRO License', 'wp-simple-firewall' ),
				'act_mwp'             => __( 'Switch-On MainWP Integration', 'wp-simple-firewall' ),
				'overall_grade'       => __( 'Grade', 'wp-simple-firewall' ),
			]
		];
	}

	/**
	 * @throws \Exception
	 */
	protected function buildEntireSiteData( array $site ) :array {
		$WP = Services::WpGeneral();
		$req = Services::Request();
		$con = $this->con();
		$mwpSite = $this->getSiteByID( (int)$site[ 'id' ] );
		$sync = LoadShieldSyncData::Load( $mwpSite );
		$meta = $sync->meta;

		$shd = $sync->getRawData();
		$status = ( new ClientPluginStatus() )
			->setMwpSite( $mwpSite )
			->detect();
		$shd[ 'status_key' ] = key( $status );
		$shd[ 'status' ] = current( $status );

		$shd[ 'is_active' ] = $shd[ 'status_key' ] === ClientPluginStatus::ACTIVE;
		$shd[ 'is_inactive' ] = $shd[ 'status_key' ] === ClientPluginStatus::INACTIVE;
		$shd[ 'is_notinstalled' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_INSTALLED;
		$shd[ 'is_notpro' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_PRO;
		$shd[ 'is_mwpnoton' ] = $shd[ 'status_key' ] === ClientPluginStatus::MWP_NOT_ON;
		$shd[ 'is_sync_rqd' ] = $shd[ 'status_key' ] === ClientPluginStatus::NEED_SYNC;
		$shd[ 'is_client_older' ] = $shd[ 'status_key' ] === ClientPluginStatus::VERSION_OLDER_THAN_SERVER;
		$shd[ 'is_client_newer' ] = $shd[ 'status_key' ] === ClientPluginStatus::VERSION_NEWER_THAN_SERVER;
		$shd[ 'is_version_mismatch' ] = in_array( $shd[ 'status_key' ], [
			ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
			ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
		] );
		$shd[ 'can_sync' ] = in_array( $shd[ 'status_key' ], [
			ClientPluginStatus::ACTIVE,
			ClientPluginStatus::NEED_SYNC,
			ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
			ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
		] );
		$shd[ 'has_update' ] = (bool)$meta->has_update;
		$shd[ 'has_issues' ] = false;

		if ( $shd[ 'is_active' ] ) {

			$shd[ 'sync_at_text' ] = $WP->getTimeStringForDisplay( $meta->sync_at );
			$shd[ 'sync_at_diff' ] = $req->carbon()->setTimestamp( $meta->sync_at )->diffForHumans();

			if ( empty( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] ) ) {
				$shd[ 'issues' ] = __( 'No Issues', 'wp-simple-firewall' );
				$shd[ 'has_issues' ] = false;
			}
			else {
				$shd[ 'has_issues' ] = true;
				$shd[ 'issues' ] = array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] );
			}

			$shd[ 'href_issues' ] = $this->getJumpUrlFor( (string)$site[ 'id' ], $con->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ) );
			$gradeLetter = $sync->modules[ 'plugin' ][ 'grades' ][ 'integrity' ][ 'totals' ][ 'letter_score' ] ?? '-';
			$shd[ 'grades' ] = [
				'href'      => $this->getJumpUrlFor( (string)$site[ 'id' ], $con->plugin_urls->adminTopNav( PluginURLs::NAV_OVERVIEW ) ),
				'integrity' => $gradeLetter,
				'good'      => in_array( $gradeLetter, [ 'A', 'B' ] ),
			];

			$shd[ 'href_manage' ] = $this->createInternalExtensionHref( [
				'tab'     => TabSiteManage::TAB,
				'site_id' => $site[ 'id' ],
			] );
		}

		$site[ 'shield' ] = $shd;
		$site[ 'hrefs' ] = [
			'manage_site' => $this->createInternalExtensionHref( [
				'tab'     => 'manage_site',
				'site_id' => $site[ 'id' ],
			] )
		];

		return $site;
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
			'location'   => base64_encode( str_replace( Services::WpGeneral()->getAdminUrl(), '', $page ) )
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

	protected function getAjaxActionsData() :array {
		return [
			'site_action' => ActionData::Build( MainwpServerClientActionHandler::class ),
			'ext_table'   => ActionData::Build( MainwpExtensionTableSites::class ),
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
					'sub_action_slug' => LicenseLookup::SLUG
				],
			]
		];
	}

	protected function getExtensionRootUri() :string {
		$req = Services::Request();
		$mwp = $this->con()->mwpVO->official_extension_data;
		return URL::Build( $req->getPath(), [
			'page' => $mwp[ 'page' ] ?? 'Extensions-Wp-Simple-Firewall',
		] );
	}

	protected function createInternalExtensionHref( array $params ) :string {
		return URL::Build( $this->getExtensionRootUri(), $params );
	}

	protected function getSites() :string {
		$mwp = $this->con()->mwpVO;
		return apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
	}

	/**
	 * @throws \Exception
	 */
	protected function getSiteByID( int $id ) :MWPSiteVO {
		return MWPSiteVO::LoadByID( $id );
	}
}