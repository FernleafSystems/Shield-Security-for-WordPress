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
			$this->configuration(),
			$this->scans(),
			$this->ips(),
			$this->audit(),
			$this->traffic(),
			$this->users(),
			$this->integrations(),
			$this->stats(),
			$this->gopro(),
			$this->tools(),
			$this->docs(),
		];

		foreach ( $menu as $key => $item ) {
			$item = Services::DataManipulation()->mergeArraysRecursive( [
				'slug'      => 'no-slug',
				'title'     => __( 'NO TITLE', 'wp-simple-firewall' ),
				'href'      => 'javascript:{}',
				'classes'   => [],
				'id'        => '',
				'active'    => $this->getInav() === $item[ 'slug' ],
				'sub_items' => [],
				'target'    => '',
				'data'      => [],
				'badge'     => [],
				'introjs'   => [],
			], $item );

			if ( !empty( $item[ 'introjs' ] ) ) {
				$item[ 'classes' ][] = 'tour-'.$this->getIntroJsTourID();
				if ( empty( $item[ 'introjs' ][ 'title' ] ) ) {
					$item[ 'introjs' ][ 'title' ] = $item[ 'title' ];
				}
			}

			if ( !empty( $item[ 'sub_items' ] ) ) {
				$item[ 'sub_items' ] = array_map( function ( $sub ) {
					if ( empty( $sub[ 'classes' ] ) ) {
						$sub[ 'classes' ] = [];
					}
					if ( $sub[ 'active' ] ?? false ) {
						$sub[ 'classes' ][] = 'active';
					}
					return $sub;
				}, $item[ 'sub_items' ] );

				// Set parent active if any sub-items are active
				if ( !$item[ 'active' ] ) {
					$item[ 'active' ] = count( array_filter( $item[ 'sub_items' ], function ( $sub ) {
						return $sub[ 'active' ] ?? false;
					} ) );
				}
			}

			if ( $item[ 'active' ] ) {
				$item[ 'classes' ][] = 'active';
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
				'title' => __( 'Blocking Rules', 'wp-simple-firewall' ),
				'href'  => $con->getModule_IPs()->getUrl_AdminPage(),
			],
			[
				'slug'  => $slug.'-antibotsettings',
				'title' => __( 'AntiBot Rules', 'wp-simple-firewall' ),
				'href'  => $con->getModule_IPs()->getUrl_DirectLinkToSection( 'section_antibot' ),
			],
			[
				'slug'    => 'ips-download',
				'href'    => $con->getModule_IPs()->createFileDownloadLink( 'db_ip' ),
				'classes' => [ 'shield_file_download' ],
				'title'   => sprintf( __( 'Download (%s)', 'wp-simple-firewall' ), 'CSV' ),
			],
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'IPs & Bots', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/diagram-3.svg' ),
			'img_hover' => $this->getCon()->svgs->raw( 'bootstrap/diagram-3-fill.svg' ),
			'introjs'   => [
				'body' => __( "Protection begins by detecting bad bots - Review and Analyse all visitor IPs that have an impact on your site.", 'wp-simple-firewall' ),
			],
			'sub_items' => $subItems,
		];
	}

	private function audit() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$slug = 'audit_trail';
		$subItems = [
			[
				'slug'   => $slug.'-log',
				'title'  => __( 'View Log', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( $slug ),
				'active' => $this->getInav() === $slug,
			],
			[
				'slug'  => $slug.'-settings',
				'title' => __( 'Configure', 'wp-simple-firewall' ),
				'href'  => $con->getModule_AuditTrail()->getUrl_AdminPage(),
			],
			[
				'slug'    => 'audit-download',
				'title'   => sprintf( __( 'Download (%s)', 'wp-simple-firewall' ), 'JSON' ),
				'href'    => $con->getModule_AuditTrail()->createFileDownloadLink( 'db_log' ),
				'classes' => [ 'shield_file_download' ],
			],
			[
				'slug'   => 'audit-glossary',
				'title'  => __( 'Glossary', 'wp-simple-firewall' ),
				'href'   => 'https://shsec.io/audittrailglossary',
				'target' => '_blank',
			],
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'Audit Trail', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/person-lines-fill.svg' ),
			'introjs'   => [
				'body' => __( "Track and review all important actions taken on your site - see the Who, What and When.", 'wp-simple-firewall' ),
			],
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
				'title' => __( 'Configure', 'wp-simple-firewall' ),
				'href'  => $con->getModule_HackGuard()->getUrl_AdminPage(),
			],
			[
				'slug'   => $slug.'-guide',
				'title'  => __( 'Guide', 'wp-simple-firewall' ),
				'href'   => 'https://shsec.io/shieldscansguide',
				'target' => '_blank',
			],
		];

		return [
			'slug'      => $slug,
			'title'     => __( 'Scans', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/shield-shaded.svg' ),
			'img_hover' => $this->getCon()->svgs->raw( 'bootstrap/shield-fill.svg' ),
			'introjs'   => [
				'body' => sprintf( __( "Run a %s scan at any time, or view the results from the latest scan.", 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
			],
			'sub_items' => $subItems,
		];
	}

	private function search() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'    => 'search',
			'title'   => __( 'Search', 'wp-simple-firewall' ),
			'img'     => $this->getCon()->svgs->raw( 'bootstrap/search.svg' ),
			'id'      => 'NavMenuSearch',
			'href'    => $mod->getUrl_SubInsightsPage( 'overview' ),
			'introjs' => [
				'body' => __( 'Use Search to find any option within the entire plugin' ),
			],
			'data'    => [
				'toggle' => 'modal',
				'target' => '#SearchDialog',
			],
		];
	}

	private function stats() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return [
			'slug'      => 'reports',
			'title'     => __( 'Reports', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/bar-chart-line.svg' ),
			'img_hover' => $this->getCon()->svgs->raw( 'bootstrap/bar-chart-line-fill.svg' ),
			'href'      => $mod->getUrl_SubInsightsPage( 'reports' ),
			'introjs'   => [
				'body' => __( 'Reports use the built-in stats to show you how Shield is working to secure your site.' ),
			],
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
			'slug'    => 'overview',
			'title'   => __( 'Overview', 'wp-simple-firewall' ),
			'img'     => $this->getCon()->svgs->raw( 'bootstrap/binoculars.svg' ),
			'href'    => $mod->getUrl_SubInsightsPage( 'overview' ),
			'introjs' => [
				'body' => sprintf( __( "Review your entire %s configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
			],
		];
	}

	private function configuration() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$slug = 'configuration';

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
			'title'     => __( 'Config', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/sliders.svg' ),
			'introjs'   => [
				'body' => sprintf( __( "%s is a big plugin split into modules, and each with their own options - use these jump-off points to find the specific option you need.", 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
			],
			'sub_items' => $subItems,
		];
	}

	private function integrations() :array {
		$con = $this->getCon();
		return [
			'slug'      => 'integrations',
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/puzzle.svg' ),
			'img_hover' => $this->getCon()->svgs->raw( 'bootstrap/puzzle-fill.svg' ),
			'title'     => __( 'Integrations', 'wp-simple-firewall' ),
			'introjs'   => [
				'body' => __( "Integrate with your favourite plugins to block SPAM and manage Shield better.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'  => 'integrations-contact',
					'title' => __( 'Contact Form SPAM', 'wp-simple-firewall' ),
					'href'  => $con->getModule_Integrations()->getUrl_DirectLinkToSection( 'section_spam' ),
				],
				[
					'slug'  => 'integrations-login',
					'title' => __( 'Custom Login Forms', 'wp-simple-firewall' ),
					'href'  => $con->getModule_Integrations()->getUrl_DirectLinkToSection( 'section_user_forms' ),
				],
			],
		];
	}

	private function docs() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'docs',
			'title' => __( "View Docs", 'wp-simple-firewall' ),
			'img'   => $this->getCon()->svgs->raw( 'bootstrap/book-half.svg' ),
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
					'href'   => 'https://shsec.io/shieldfreetrialinplugin',
					'target' => '_blank',
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
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/award.svg' ),
			'img_hover' => $this->getCon()->svgs->raw( 'bootstrap/award-fill.svg' ),
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
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/tools.svg' ),
			'introjs'   => [
				'body' => __( "Important security tools, such a import/export, whitelabel and admin notes.", 'wp-simple-firewall' ),
			],
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
				'title'  => __( 'View Traffic', 'wp-simple-firewall' ),
				'href'   => $mod->getUrl_SubInsightsPage( $slug ),
				'active' => $this->getInav() === $slug,
			],
			[
				'slug'  => $slug.'-settings',
				'title' => __( 'Configure', 'wp-simple-firewall' ),
				'href'  => $con->getModule_Traffic()->getUrl_DirectLinkToSection( 'section_traffic_options' ),
			],
		];

		return [
			'slug'      => 'traffic',
			'title'     => __( 'Traffic', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/stoplights.svg' ),
			'introjs'   => [
				'body' => __( "Monitor and watch traffic as it hits your site.", 'wp-simple-firewall' ),
			],
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
				'title' => sprintf( '%s: %s', __( 'Configure', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_SecAdmin()->getUrl_DirectLinkToSection( 'section_security_admin_settings' ),
			],
			[
				'slug'  => 'users-settings',
				'title' => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), __( 'Sessions', 'wp-simple-firewall' ) ),
				'href'  => $con->getModule_UserManagement()
							   ->getUrl_DirectLinkToSection( 'section_user_session_management' ),
			],
			[
				'slug'  => 'users-passwords',
				'title' => __( 'Password Policies', 'wp-simple-firewall' ),
				'href'  => $con->getModule_UserManagement()->getUrl_DirectLinkToSection( 'section_passwords' ),
			],
			[
				'slug'  => 'users-suspend',
				'title' => __( 'User Suspension', 'wp-simple-firewall' ),
				'href'  => $con->getModule_UserManagement()->getUrl_DirectLinkToSection( 'section_suspend' ),
			],
		];

		return [
			'slug'      => 'users',
			'title'     => __( 'Users', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/person-badge.svg' ),
			'introjs'   => [
				'body' => __( 'View sessions, and configure session timeouts and passwords requirements.', 'wp-simple-firewall' ),
			],
			'sub_items' => $subItems,
		];
	}

	private function getIntroJsTourID() :string {
		return 'navigation_v1';
	}

	private function getInav() :string {
		return (string)Services::Request()->query( 'inav' );
	}
}