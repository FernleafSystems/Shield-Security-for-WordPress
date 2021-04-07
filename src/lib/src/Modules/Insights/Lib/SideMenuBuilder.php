<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SideMenuBuilder {

	use ModConsumer;

	public function build() :array {
		$menu = [
			$this->search(),
			$this->overview(),
			$this->stats(),
			$this->settings(),
			$this->scans(),
			$this->ips(),
			$this->audit(),
			$this->traffic(),
			$this->users(),
			$this->integrations(),
			$this->gopro(),
			$this->tools(),
			$this->docs(),
		];

		foreach ( $menu as $key => $item ) {
			$item = Services::DataManipulation()->mergeArraysRecursive( [
				'slug'      => 'no-slug',
				'title'     => __( 'NO TITLE', 'wp-simple-firewall' ),
				'href'      => '#',
				'classes'   => [],
				'id'        => '',
				'active'    => $this->getInav() === $item[ 'slug' ],
				'sub_items' => [],
				'target'    => '',
				'data'      => [],
				'badge'     => [],
			], $item );

			if ( !empty( $item[ 'sub_items' ] ) ) {
				$item[ 'data' ][ 'toggle' ] = 'collapse';
				$item[ 'href' ] = '#collapse-'.$item[ 'slug' ];

				// Set parent active if any sub-items are active
				if ( !$item[ 'active' ] ) {
					$item[ 'active' ] = count( array_filter( $item[ 'sub_items' ], function ( $sub ) {
						return $sub[ 'active' ] ?? false;
					} ) );
				}
			}

			$menu[ $key ] = $item;
		}

		return $menu;
	}

	private function ips() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$slug = 'ips';

		$subItems = [
			[
				'slug'   => $slug.'-manage',
				'title'  => __( 'Manage IPs', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( 'ips' ),
				'active' => $this->getInav() === $slug,
			],
			[
				'slug'  => $slug.'-blocksettings',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'IP Blocking', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_IPs()->getUrl_AdminPage(),
			],
			[
				'slug'  => $slug.'-antibotsettings',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'AntiBot', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_IPs()->getUrl_DirectLinkToSection( 'section_antibot' ),
			],
			[
				'slug'    => 'ips-download',
				'href'    => $con->getModule_IPs()->createFileDownloadLink( 'db_ip' ),
				'classes' => [ 'shield_file_download' ],
				'title'   => sprintf( __( 'Download (as %s)', 'wp-simple-firewall' ), 'CSV' ),
			],
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'IPs and Bots', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/diagram-3.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function audit() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$slug = 'audit';
		$subItems = [
			[
				'slug'   => $slug.'-log',
				'title'  => __( 'View Log', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( $slug ),
				'active' => $this->getInav() === $slug,
			],
			[
				'slug'  => $slug.'-settings',
				'title' => __( 'Settings', 'wp-simple-firewall' ),
				'href'  => $con->getModule_AuditTrail()->getUrl_AdminPage(),
			],
			[
				'slug'    => 'audit-download',
				'title'   => sprintf( __( 'Download (as %s)', 'wp-simple-firewall' ), 'CSV' ),
				'href'    => $con->getModule_AuditTrail()->createFileDownloadLink( 'db_audit' ),
				'classes' => [ 'shield_file_download' ],
			],
			[
				'slug'   => 'audit-glossary',
				'title'  => __( 'Audit Trail Glossary', 'wp-simple-firewall' ),
				'href'   => 'https://shsec.io/audittrailglossary',
				'target' => '_blank',
			],
		];

		return [
			'slug'      => 'audit',
			'title'     => __( 'Audit Trail', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/person-lines-fill.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function scans() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$con = $this->getCon();

		$slug = 'scans';

		$subItems = [
			[
				'slug'   => $slug.'-run',
				'title'  => __( 'Run Scan', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_ScansRun(),
				'active' => $this->getInav() === 'scans_run',
			],
			[
				'slug'   => $slug.'-results',
				'title'  => __( 'Scan Results', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_ScansResults(),
				'active' => $this->getInav() === 'scans_results',
			],
			[
				'slug'  => $slug.'-settings',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'Automatic Scans', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_HackGuard()->getUrl_AdminPage(),
			],
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'Scans', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/shield-shaded.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function search() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'search',
			'title' => __( 'Search', 'wp-simple-firewall' ),
			'img'   => $this->getCon()->urls->forImage( 'bootstrap/search.svg' ),
			'id'    => 'NavMenuSearch',
			'href'  => $mod->getUrl_SubInsightsPage( 'overview' ),
			'data'  => [
				'toggle' => 'modal',
				'target' => '#SearchDialog',
			],
		];
	}

	private function stats() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return [
			'slug'      => 'reports',
			'title'     => __( 'Reports', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/graph-up.svg' ),
			'href'      => $mod->getUrl_SubInsightsPage( 'reports' ),
			'sub_items' => [
				[
					'slug'   => 'reports-stats',
					'title'  => __( 'Stats', 'wp-simple-firewall' ),
					'href'   => $mod->getUrl_SubInsightsPage( 'stats' ),
					'active' => $this->getInav() === 'stats'
				],
				[
					'slug'   => 'reports-charts',
					'title'  => __( 'Charts', 'wp-simple-firewall' ),
					'href'   => $mod->getUrl_SubInsightsPage( 'reports' ),
					'active' => $this->getInav() === 'reports'
				],
			],
		];
	}

	private function overview() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'overview',
			'title' => __( 'Overview', 'wp-simple-firewall' ),
			'img'   => $this->getCon()->urls->forImage( 'bootstrap/binoculars.svg' ),
			'href'  => $mod->getUrl_SubInsightsPage( 'overview' ),
		];
	}

	private function settings() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$slug = 'settings';

		$subItems = [];
		foreach ( $mod->getModulesSummaryData() as $modData ) {
			if ( $modData[ 'show_mod_opts' ] ) {
				$subItems[] = [
					'slug'    => $slug.'-'.$modData[ 'slug' ],
					'title'   => $modData[ 'sidebar_name' ] ?? $modData[ 'name' ],
					'href'    => $modData[ 'href' ],
					'classes' => [ 'dynamic_body_load' ],
					'data'    => [
						'load_type'    => $slug,
						'load_variant' => $modData[ 'slug' ],
					],
					'active'  => Services::Request()->query( 'subnav' ) === $modData[ 'slug' ]
				];
			}
		}

		return [
			'slug'      => $slug,
			'title'     => __( 'Settings', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/sliders.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function integrations() :array {
		$con = $this->getCon();
		return [
			'slug'      => 'integrations',
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/puzzle-fill.svg' ),
			'title'     => __( 'Integrations', 'wp-simple-firewall' ),
			'sub_items' => [
				[
					'slug'  => 'integrations-settings',
					'title' => __( 'Settings', 'wp-simple-firewall' ),
					'href'  => $con->getModule_Integrations()->getUrl_AdminPage(),
				],
				[
					'slug'  => 'integrations-spam',
					'title' => __( 'Contact Form SPAM', 'wp-simple-firewall' ),
					'href'  => $con->getModule_Integrations()->getUrl_DirectLinkToSection( 'section_spam' ),
				],
			],
		];
	}

	private function reports() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'reports',
			'title' => __( 'Reports', 'wp-simple-firewall' ),
			'img'   => $this->getCon()->urls->forImage( 'bootstrap/graph-up.svg' ),
			'href'  => $mod->getUrl_SubInsightsPage( 'reports' ),
		];
	}

	private function docs() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'docs',
			'title' => __( "View Docs", 'wp-simple-firewall' ),
			'img'   => $this->getCon()->urls->forImage( 'bootstrap/book-half.svg' ),
			'href'  => $mod->getUrl_SubInsightsPage( 'docs' ),
		];
	}

	private function gopro() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$isPro = $this->getCon()->isPremiumActive();

		if ( $isPro ) {
			$subItems = [];
		}
		else {
			$subItems = [
				[
					'slug'   => 'license-gopro',
					'title'  => __( 'Check License', 'wp-simple-firewall' ),
					'href'   => $mod->getUrl_SubInsightsPage( 'license' ),
					'active' => $this->getInav() === 'license'
				],
				[
					'slug'   => 'license-trial',
					'title'  => __( 'Free Trial', 'wp-simple-firewall' ),
					'href'   => $mod->getUrl_SubInsightsPage( 'free_trial' ),
					'active' => $this->getInav() === 'free_trial'
				],
				[
					'slug'   => 'license-features',
					'href'   => 'https://shsec.io/gp',
					'title'  => __( 'ShieldPRO Features', 'wp-simple-firewall' ),
					'target' => '_blank',
				],
			];
		}

		return [
			'slug'      => 'license',
			'title'     => $isPro ? __( 'ShieldPRO', 'wp-simple-firewall' ) : __( 'Go PRO!', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/award.svg' ),
			'href'      => $mod->getUrl_SubInsightsPage( 'license' ),
			'sub_items' => $subItems,
		];
	}

	private function tools() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$slug = 'tools';
		$subItems = [
			[
				'slug'   => $slug.'-importexport',
				'title'  => __( 'Import / Export', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( 'importexport' ),
				'active' => $this->getInav() === 'importexport'
			],
			[
				'slug'  => $slug.'-whitelabel',
				'title' => __( 'White Label', 'wp-simple-firewall' ),
				'href'  => $con->getModule_SecAdmin()->getUrl_DirectLinkToSection( 'section_whitelabel' ),
			],
			[
				'slug'   => $slug.'-notes',
				'title'  => __( 'Admin Notes', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( 'notes' ),
				'active' => $this->getInav() === 'notes'
			],
			[
				'slug'   => $slug.'-debug',
				'title'  => __( "Debug Info", 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( 'debug' ),
				'active' => $this->getInav() === 'debug'
			]
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/tools.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function traffic() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$slug = 'traffic';
		$subItems = [
			[
				'slug'   => $slug.'-log',
				'title'  => __( 'View Log', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( $slug ),
				'active' => $this->getInav() === $slug,
			],
			[
				'slug'  => $slug.'-ratelimitsettings',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'Rate Limiting', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_Traffic()->getUrl_DirectLinkToSection( 'section_traffic_limiter' ),
			],
			[
				'slug'    => 'traffic-download',
				'href'    => $con->getModule_Traffic()->createFileDownloadLink( 'db_traffic' ),
				'classes' => [ 'shield_file_download' ],
				'title'   => sprintf( __( 'Download (as %s)', 'wp-simple-firewall' ), 'CSV' ),
			],
		];

		return [
			'slug'      => 'traffic',
			'title'     => __( 'Traffic', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/stoplights.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function users() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$subItems = [
			[
				'slug'  => 'users-sessions',
				'title' => __( 'View Sessions', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'users' ),
			],
			[
				'slug'  => 'users-secadmin',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_SecAdmin()->getUrl_DirectLinkToSection( 'section_security_admin_settings' ),
			],
			[
				'slug'  => 'users-settings',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'Sessions', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_UserManagement()
							   ->getUrl_DirectLinkToSection( 'section_user_session_management' ),
			],
			[
				'slug'  => 'users-passwords',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'Password Policies', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_UserManagement()->getUrl_DirectLinkToSection( 'section_passwords' ),
			],
			[
				'slug'  => 'users-suspend',
				'title' => sprintf( '%s: %s', __( 'Settings', 'wp-simple-firewall' ), __( 'User Suspension', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_UserManagement()->getUrl_DirectLinkToSection( 'section_suspend' ),
			],
		];

		return [
			'slug'      => 'users',
			'title'     => __( 'Users', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/person-badge.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function getInav() :string {
		return (string)Services::Request()->query( 'inav' );
	}
}