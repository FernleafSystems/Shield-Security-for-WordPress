<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;
use FernleafSystems\Wordpress\Services\Services;

class AdminBarMenu {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = self::con();
		return !$con->this_req->is_force_off
			   && !$con->this_req->wp_is_ajax
			   && apply_filters( 'shield/show_admin_bar_menu', $con->cfg->properties[ 'show_admin_bar_menu' ] )
			   && self::con()->opts->optIs( 'enable_upgrade_admin_notice', 'Y' )
			   && $con->isValidAdminArea()
			   && Services::WpUsers()->isUserAdmin();
	}

	protected function run() {
		add_action( 'admin_bar_menu', function ( $adminBar ) {
			if ( $adminBar instanceof \WP_Admin_Bar ) {
				$this->createAdminBarMenu( $adminBar );
			}
		}, 100 );
	}

	private function createAdminBarMenu( \WP_Admin_Bar $adminBar ) {

		$groups = \array_filter( [
			$this->ipsBlocked(),
			$this->ipsOffended(),
			$this->hackGuard(),
			$this->users(),
		] );

		$subNodeGroupsToAdd = [];

		if ( !empty( $groups ) ) {
			$con = self::con();
			$totalWarnings = 0;
			$topNodeID = $con->prefix( 'adminbarmenu' );
			foreach ( $groups as $key => $group ) {

				$group[ 'id' ] = $con->prefix( 'adminbarmenu-sub'.$key );

				foreach ( $group[ 'items' ] as $item ) {
					$totalWarnings += $item[ 'warnings' ] ?? 0;
					$item[ 'parent' ] = $group[ 'id' ];
					$adminBar->add_node( $item );
				}

				unset( $group[ 'items' ] );
				$group[ 'parent' ] = $topNodeID;
				$subNodeGroupsToAdd[] = $group;
			}

			// The top menu item.
			$adminBar->add_node( [
				'id'    => $topNodeID,
				'title' => sprintf( '%s %s', $con->labels->Name,
					empty( $totalWarnings ) ? '' : sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $totalWarnings )
				),
				'href'  => $con->plugin_urls->adminHome()
			] );

			if ( $con->isPluginAdmin() ) {
				foreach ( $subNodeGroupsToAdd as $nodeGroup ) {
					$adminBar->add_node( $nodeGroup );
				}
			}
		}
	}

	private function hackGuard() :?array {
		$items = [];
		foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
			if ( $scanCon->isEnabled() ) {
				$items = \array_merge( $items, $scanCon->getAdminMenuItems() );
			}
		}

		$thisGroup = null;
		if ( !empty( $items ) ) {

			$totalWarnings = 0;
			foreach ( $items as $item ) {
				$totalWarnings += $item[ 'warnings' ];
			}

			$thisGroup = [
				'title' => sprintf(
					'%s %s', __( 'Scan Results', 'wp-simple-firewall' ),
					sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $totalWarnings )
				),
				'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				'items' => $items,
			];
		}

		return $thisGroup;
	}

	private function ipsOffended() :?array {
		$con = self::con();
		$thisGroup = null;

		$IPs = ( new RecentStats() )->getRecentlyOffendedIPs();
		if ( !empty( $IPs ) ) {
			$thisGroup = [
				'title' => __( 'Recent Offenses', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminIpRules(),
				'items' => \array_map( function ( $ip ) use ( $con ) {
					return [
						'id'    => $con->prefix( 'ip-'.$ip->id ),
						'title' => $ip->ip,
						'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
					];
				}, $IPs ),
			];
		}

		return $thisGroup;
	}

	private function ipsBlocked() :?array {
		$con = self::con();
		$thisGroup = null;

		$IPs = ( new RecentStats() )->getRecentlyBlockedIPs();
		if ( !empty( $IPs ) ) {
			$thisGroup = [
				'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminIpRules(),
				'items' => \array_map( function ( $ip ) use ( $con ) {
					return [
						'id'    => $con->prefix( 'ip-'.$ip->id ),
						'title' => $ip->ip,
						'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
					];
				}, $IPs ),
			];
		}

		return $thisGroup;
	}

	private function users() :?array {
		$con = self::con();

		$thisGroup = null;

		$recent = ( new FindSessions() )->mostRecent();
		if ( !empty( $recent ) ) {
			$items = [];
			foreach ( $recent as $userID => $user ) {
				$items[] = [
					'id'    => $con->prefix( 'meta-'.$userID ),
					'title' => sprintf( '<a href="%s">%s (%s)</a>',
						Services::WpUsers()->getAdminUrl_ProfileEdit( $userID ),
						$user[ 'user_login' ],
						$user[ 'ip' ]
					),
				];
			}

			$thisGroup = [
				'title' => __( 'Recent Users', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_SESSIONS ),
				'items' => $items,
			];
		}

		return $thisGroup;
	}
}