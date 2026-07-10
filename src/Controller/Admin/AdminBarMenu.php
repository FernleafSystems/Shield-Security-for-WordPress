<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AdminBarExactScanCounts from Counts
 * @phpstan-import-type AdminBarScanSummaryShape from Counts
 * @phpstan-type AdminBarItem array{
 *   id:string,
 *   title:string,
 *   href?:string,
 *   parent?:string
 * }
 * @phpstan-type AdminBarGroup array{
 *   title:string,
 *   href:string,
 *   items:list<AdminBarItem>,
 *   id?:string,
 *   parent?:string
 * }
 * @phpstan-type AdminBarScanStatus array{
 *   summary:AdminBarScanSummaryShape,
 *   is_exact:bool
 * }
 */
class AdminBarMenu {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = self::con();
		return !$con->this_req->is_force_off
			   && !$con->this_req->wp_is_ajax
			   && $con->isValidAdminArea()
			   && Services::WpUsers()->isUserAdmin()
			   && (bool)apply_filters( 'shield/show_admin_bar_menu', true );
	}

	protected function run() {
		// @phpstan-ignore return.void
		add_action( 'admin_bar_menu', fn( $adminBar ) => $adminBar instanceof \WP_Admin_Bar ? $this->createAdminBarMenu( $adminBar ) : null, 100 );
	}

	private function createAdminBarMenu( \WP_Admin_Bar $adminBar ) :void {

		$con = self::con();
		$canSeeDetails = $con->isPluginAdmin();
		$isPluginAdminPageRequest = $con->isPluginAdminPageRequest();
		$scanStatus = $this->scanStatus( $canSeeDetails && $isPluginAdminPageRequest );
		$scanSummary = $scanStatus[ 'summary' ];
		$groups = $canSeeDetails ? $this->buildDetailGroups( $scanStatus, $isPluginAdminPageRequest ) : [];

		$subNodeGroupsToAdd = [];
		$topNodeID = $con->prefix( 'adminbarmenu' );

		foreach ( $groups as $key => $group ) {

			$group[ 'id' ] = $con->prefix( 'adminbarmenu-sub'.$key );
			foreach ( $group[ 'items' ] as $item ) {
				$item[ 'parent' ] = $group[ 'id' ];
				$this->addAdminBarNode( $adminBar, $item );
			}

			unset( $group[ 'items' ] );
			$group[ 'parent' ] = $topNodeID;
			$subNodeGroupsToAdd[] = $group;
		}

		$adminBar->add_node( [
			'id'    => $topNodeID,
			'title' => sprintf(
				'%s %s',
				$con->labels->Name,
				$this->counterMarkup(
					$this->formatCounterLabel( $scanSummary[ 'total' ], $scanSummary[ 'is_capped' ] ),
					$scanSummary[ 'total' ] === 0
				)
			),
			'href'  => $con->plugin_urls->actionsQueueScans()
		] );

		foreach ( $subNodeGroupsToAdd as $nodeGroup ) {
			$this->addAdminBarNode( $adminBar, $nodeGroup );
		}
	}

	/**
	 * @param AdminBarScanStatus $scanStatus
	 * @return list<AdminBarGroup>
	 */
	private function buildDetailGroups( array $scanStatus, bool $isPluginAdminPageRequest ) :array {
		return \array_values( \array_filter( [
			$this->hackGuard( $scanStatus ),
			$isPluginAdminPageRequest ? $this->users() : null,
		] ) );
	}

	/**
	 * @return AdminBarScanStatus
	 */
	private function scanStatus( bool $canRefreshExact ) :array {
		$con = self::con();
		$cache = $con->comps->scans->getAdminBarScanSummaryCache();
		$summary = $cache->read();
		$hasExactSummary = $summary !== null;

		if ( !$hasExactSummary ) {
			$counts = $con->comps->scans->getScanResultsCount();

			if ( $canRefreshExact ) {
				$summary = $cache->refresh( $counts );
				$hasExactSummary = $summary !== null;
			}

			if ( !$hasExactSummary ) {
				$summary = $counts->adminBarScanSummary( false );
			}
		}

		return [
			'summary'  => $summary,
			'is_exact' => $hasExactSummary,
		];
	}

	/**
	 * @param AdminBarScanStatus $scanStatus
	 * @return AdminBarGroup|null
	 */
	private function hackGuard( array $scanStatus ) :?array {
		$summary = $scanStatus[ 'summary' ];
		if ( !$scanStatus[ 'is_exact' ] ) {
			return null;
		}

		if ( $summary[ 'total' ] < 1 ) {
			return null;
		}

		$counterLabel = $this->formatCounterLabel( $summary[ 'total' ], $summary[ 'is_capped' ] );
		/** @var AdminBarExactScanCounts $counts */
		$counts = $summary[ 'counts' ];

		return [
			'title' => sprintf(
				'%s %s', __( 'Scan Results', 'wp-simple-firewall' ),
				$this->counterMarkup( $counterLabel )
			),
			'href'  => self::con()->plugin_urls->actionsQueueScans(),
			'items' => $this->buildHackGuardItems( $counts ),
		];
	}

	/**
	 * @param AdminBarExactScanCounts $counts
	 * @return list<AdminBarItem>
	 */
	private function buildHackGuardItems( array $counts ) :array {
		$items = [];
		$con = self::con();

		foreach ( $this->hackGuardItemDefinitions() as $key => $definition ) {
			$count = $counts[ $key ];
			if ( $count < 1 ) {
				continue;
			}

			$items[] = [
				'id'    => $con->prefix( 'problems-scan-'.$definition[ 'suffix' ] ),
				'title' => $definition[ 'label' ].$this->counterMarkup( (string)$count ),
			];
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

	private function counterMarkup( string $countLabel, bool $isOk = false ) :string {
		return sprintf(
			'<div class="wp-core-ui wp-ui-notification shield-counter %s"><span aria-hidden="true">%s</span></div>',
			$isOk ? 'shield-counter--ok' : 'shield-counter--issue',
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
		$adminBar->add_node( $node );
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
				];
			}

			$thisGroup = [
				'title' => __( 'Recent Users', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->investigateUserSessions(),
				'items' => $items,
			];
		}

		return $thisGroup;
	}
}
