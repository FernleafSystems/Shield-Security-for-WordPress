<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\LoadShieldSyncData;
use FernleafSystems\Wordpress\Services\Services;

class TabSitesListing extends BaseSubPage {

	public const SLUG = 'mainwp_page_sites_listing';
	public const TEMPLATE = '/integration/mainwp/pages/sites.twig';
	public const TAB = 'sites';

	protected function getRenderData() :array {
		$mwp = self::con()->mwpVO;

		$statsHead = [
			'connected'    => 0,
			'disconnected' => 0,
			'with_issues'  => 0,
			'needs_update' => 0,
		];

		$sites = \array_filter( \array_map(
			function ( $site ) use ( $statsHead ) {
				try {
					return $this->buildEntireSiteData( $site );
				}
				catch ( \Exception $e ) {
					return null;
				}
			},
			apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key )
		) );

		foreach ( $sites as $site ) {
			$shieldData = $site[ 'shield' ];
			if ( $shieldData[ 'is_active' ] ) {
				$statsHead[ 'connected' ]++;
			}
			else {
				$statsHead[ 'disconnected' ]++;
			}
			if ( $shieldData[ 'has_update' ] ) {
				$statsHead[ 'needs_update' ]++;
			}
			if ( $shieldData[ 'has_issues' ] ) {
				$statsHead[ 'with_issues' ]++;
			}
		}

		return [
			'vars' => [
				'sites'      => $sites,
				'stats_head' => $statsHead,
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	protected function buildEntireSiteData( array $site ) :array {
		$mwpSite = $this->getSiteByID( (int)$site[ 'id' ] );
		$sync = LoadShieldSyncData::Load( $mwpSite );

		$status = ( new ClientPluginStatus() )
			->setMwpSite( $mwpSite )
			->detect();

		$shd = $sync->getRawData();

		$shd[ 'status_key' ] = \key( $status );
		$shd[ 'status' ] = \current( $status );

		$shd[ 'is_active' ] = $shd[ 'status_key' ] === ClientPluginStatus::ACTIVE;
		$shd[ 'is_inactive' ] = $shd[ 'status_key' ] === ClientPluginStatus::INACTIVE;
		$shd[ 'is_notinstalled' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_INSTALLED;
		$shd[ 'is_notpro' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_PRO;
		$shd[ 'is_mwpnoton' ] = $shd[ 'status_key' ] === ClientPluginStatus::MWP_NOT_ON;
		$shd[ 'is_sync_rqd' ] = $shd[ 'status_key' ] === ClientPluginStatus::NEED_SYNC;
		$shd[ 'is_client_older' ] = $shd[ 'status_key' ] === ClientPluginStatus::VERSION_OLDER_THAN_SERVER;
		$shd[ 'is_client_newer' ] = $shd[ 'status_key' ] === ClientPluginStatus::VERSION_NEWER_THAN_SERVER;
		$shd[ 'is_version_mismatch' ] = \in_array( $shd[ 'status_key' ], [
			ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
			ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
		] );
		$shd[ 'can_sync' ] = \in_array( $shd[ 'status_key' ], [
			ClientPluginStatus::ACTIVE,
			ClientPluginStatus::NEED_SYNC,
			ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
			ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
		] );
		$shd[ 'has_update' ] = (bool)$sync->meta->has_update;
		$shd[ 'has_issues' ] = false;

		if ( $shd[ 'is_active' ] ) {

			$shd[ 'sync_at_text' ] = Services::WpGeneral()->getTimeStringForDisplay( $sync->meta->sync_at );
			$shd[ 'sync_at_diff' ] = Services::Request()
											 ->carbon()
											 ->setTimestamp( $sync->meta->sync_at )
											 ->diffForHumans();

			$totalIssues = \array_sum( $sync->scan_issues ?? [] );
			if ( $totalIssues === 0 ) {
				$shd[ 'issues' ] = __( 'No Issues', 'wp-simple-firewall' );
				$shd[ 'has_issues' ] = false;
			}
			else {
				$shd[ 'has_issues' ] = true;
				$shd[ 'issues' ] = $totalIssues;
			}

			$shd[ 'href_issues' ] = $this->getJumpUrlFor(
				(string)$site[ 'id' ],
				self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS )
			);
			$gradeLetter = $sync->integrity[ 'totals' ][ 'letter_score' ] ?? '-';
			$shd[ 'grades' ] = [
				'href'      => $this->getJumpUrlFor( (string)$site[ 'id' ], self::con()->plugin_urls->adminHome() ),
				'integrity' => $gradeLetter,
				'good'      => \in_array( $gradeLetter, [ 'A', 'B' ] ),
			];

			$shd[ 'href_manage' ] = $this->createInternalExtensionHref( [
				'tab'     => TabSiteManage::TAB,
				'site_id' => $site[ 'id' ],
			] );
		}

		$shd[ 'site_actions' ] = $this->determineSiteActions( $shd );

		$site[ 'shield' ] = $shd;
		$site[ 'hrefs' ] = [
			'manage_site' => $this->createInternalExtensionHref( [
				'tab'     => 'manage_site',
				'site_id' => $site[ 'id' ],
			] )
		];

		return $site;
	}

	private function determineSiteActions( array $flags ) :array {
		$actions = [];
		if ( $flags[ 'can_sync' ] ) {
			$actions[] = 'sync';
		}

		if ( $flags[ 'has_update' ] ) {
			$actions[] = 'update';
		}

		if ( $flags[ 'is_notinstalled' ] ) {
			$actions[] = 'install';
		}
		elseif ( $flags[ 'is_inactive' ] ) {
			$actions[] = 'activate';
		}
		else {
			$actions[] = 'deactivate';
		}

		$actions[] = 'license';

		if ( $flags[ 'is_mwpnoton' ] ) {
			$actions[] = 'mwp_on';
		}

		return $actions;
	}
}