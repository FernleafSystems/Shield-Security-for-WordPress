<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AdminBarExactScanCounts from Counts
 * @phpstan-type AdminBarItem array{
 *   id:string,
 *   title:string,
 *   href?:string,
 *   warnings:int,
 *   warnings_capped:bool,
 *   parent?:string
 * }
 * @phpstan-type AdminBarGroup array{
 *   title:string,
 *   href:string,
 *   items:list<AdminBarItem>,
 *   warnings:int,
 *   warnings_capped:bool,
 *   id?:string,
 *   parent?:string
 * }
 */
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
		// @phpstan-ignore return.void
		add_action( 'admin_bar_menu', fn( $adminBar ) => $adminBar instanceof \WP_Admin_Bar ? $this->createAdminBarMenu( $adminBar ) : null, 100 );
	}

	private function createAdminBarMenu( \WP_Admin_Bar $adminBar ) :void {

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
			$hasCappedWarnings = false;
			$topNodeID = $con->prefix( 'adminbarmenu' );
			foreach ( $groups as $key => $group ) {

				$group[ 'id' ] = $con->prefix( 'adminbarmenu-sub'.$key );
				if ( empty( $group[ 'items' ] ) ) {
					$totalWarnings += $group[ 'warnings' ];
					$hasCappedWarnings = $hasCappedWarnings || $group[ 'warnings_capped' ];
				}

				foreach ( $group[ 'items' ] as $item ) {
					$totalWarnings += $item[ 'warnings' ];
					$hasCappedWarnings = $hasCappedWarnings || $item[ 'warnings_capped' ];
					$item[ 'parent' ] = $group[ 'id' ];
					$this->addAdminBarNode( $adminBar, $item );
				}

				unset( $group[ 'items' ] );
				$group[ 'parent' ] = $topNodeID;
				$subNodeGroupsToAdd[] = $group;
			}

			// The top menu item.
			$adminBar->add_node( [
				'id'    => $topNodeID,
				'title' => sprintf( '%s %s', $con->labels->Name,
					empty( $totalWarnings ) ? '' : $this->counterMarkup( $this->formatCounterLabel( $totalWarnings, $hasCappedWarnings ) )
				),
				'href'  => $con->plugin_urls->adminHome()
			] );

			if ( $con->isPluginAdmin() ) {
				foreach ( $subNodeGroupsToAdd as $nodeGroup ) {
					$this->addAdminBarNode( $adminBar, $nodeGroup );
				}
			}
		}
	}

	/**
	 * @return AdminBarGroup|null
	 */
	private function hackGuard() :?array {
		$con = self::con();
		$counts = $con->comps->scans->getScanResultsCount();
		$showScanItems = $con->isPluginAdmin();
		$summary = $counts->adminBarScanSummary( $showScanItems );

		$thisGroup = null;
		if ( $summary[ 'total' ] > 0 ) {
			$items = $showScanItems
				? $this->buildHackGuardItems( $summary[ 'counts' ] )
				: [];

			$counterLabel = $this->formatCounterLabel( $summary[ 'total' ], $summary[ 'is_capped' ] );

			$thisGroup = [
				'title' => sprintf(
					'%s %s', __( 'Scan Results', 'wp-simple-firewall' ),
					$this->counterMarkup( $counterLabel )
				),
				'href'  => self::con()->plugin_urls->actionsQueueScans(),
				'items' => $items,
				'warnings'        => $summary[ 'total' ],
				'warnings_capped' => $summary[ 'is_capped' ],
			];
		}

		return $thisGroup;
	}

	/**
	 * @param AdminBarExactScanCounts $counts
	 * @return list<AdminBarItem>
	 */
	private function buildHackGuardItems( array $counts ) :array {
		$items = [];
		$template = [
			'id'    => self::con()->prefix( 'problems-scan' ),
			'title' => '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
		];

		foreach ( $this->hackGuardItemDefinitions() as $key => $definition ) {
			$count = $counts[ $key ];
			if ( $count < 1 ) {
				continue;
			}

			$item = $template;
			$item[ 'id' ] .= '-'.$definition[ 'suffix' ];
			$item[ 'title' ] = $definition[ 'label' ].sprintf( $item[ 'title' ], $count );
			$item[ 'warnings' ] = $count;
			$item[ 'warnings_capped' ] = false;
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * @return array<string,array{suffix:string,label:string}>
	 */
	private function hackGuardItemDefinitions() :array {
		return [
			'malware'           => [
				'suffix' => 'malware',
				'label'  => __( 'Potential Malware', 'wp-simple-firewall' ),
			],
			'wp_files'          => [
				'suffix' => 'wp',
				'label'  => __( 'WordPress Core Files', 'wp-simple-firewall' ),
			],
			'plugin_files'      => [
				'suffix' => 'plugin',
				'label'  => __( 'Plugin Files', 'wp-simple-firewall' ),
			],
			'theme_files'       => [
				'suffix' => 'theme',
				'label'  => __( 'Theme Files', 'wp-simple-firewall' ),
			],
			'abandoned'         => [
				'suffix' => 'apc',
				'label'  => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
			],
			'vulnerable_assets' => [
				'suffix' => 'wpv',
				'label'  => __( 'Vulnerable Plugins', 'wp-simple-firewall' ),
			],
		];
	}

	private function counterMarkup( string $countLabel ) :string {
		return sprintf(
			'<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
			$countLabel
		);
	}

	private function formatCounterLabel( int $count, bool $isCapped = false ) :string {
		return $isCapped ? '99+' : (string)$count;
	}

	/**
	 * @param AdminBarItem|AdminBarGroup $node
	 */
	private function addAdminBarNode( \WP_Admin_Bar $adminBar, array $node ) :void {
		unset( $node[ 'warnings' ], $node[ 'warnings_capped' ] );
		$adminBar->add_node( $node );
	}

	/**
	 * @return AdminBarGroup|null
	 */
	private function ipsOffended() :?array {
		$con = self::con();
		$thisGroup = null;

		$IPs = ( new RecentStats() )->getRecentlyOffendedIPs();
		if ( !empty( $IPs ) ) {
			$thisGroup = [
				'title' => __( 'Recent Offenses', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminIpRules(),
				'items' => \array_map( fn( $ip ) => [
					'id'    => $con->prefix( 'ip-'.$ip->id ),
					'title' => $ip->ip,
					'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
					'warnings'        => 0,
					'warnings_capped' => false,
				], $IPs ),
				'warnings'        => 0,
				'warnings_capped' => false,
			];
		}

		return $thisGroup;
	}

	/**
	 * @return AdminBarGroup|null
	 */
	private function ipsBlocked() :?array {
		$con = self::con();
		$thisGroup = null;

		$IPs = ( new RecentStats() )->getRecentlyBlockedIPs();
		if ( !empty( $IPs ) ) {
			$thisGroup = [
				'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminIpRules(),
				'items' => \array_map( fn( $ip ) => [
					'id'    => $con->prefix( 'ip-'.$ip->id ),
					'title' => $ip->ip,
					'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
					'warnings'        => 0,
					'warnings_capped' => false,
				], $IPs ),
				'warnings'        => 0,
				'warnings_capped' => false,
			];
		}

		return $thisGroup;
	}

	/**
	 * @return AdminBarGroup|null
	 */
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
					'warnings'        => 0,
					'warnings_capped' => false,
				];
			}

			$thisGroup = [
				'title' => __( 'Recent Users', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->investigateUserSessions(),
				'items' => $items,
				'warnings'        => 0,
				'warnings_capped' => false,
			];
		}

		return $thisGroup;
	}
}
