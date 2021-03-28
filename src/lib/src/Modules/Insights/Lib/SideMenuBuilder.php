<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SideMenuBuilder {

	use ModConsumer;

	public function build() :array {
		$menu = [
			$this->overview(),
			$this->settings(),
			$this->scans(),
			$this->ips(),
			$this->audit(),
			$this->traffic(),
			$this->users(),
			$this->reports(),
			$this->integrations(),
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
				'active'    => false,
				'sub_items' => [],
				'target'    => '',
				'data'      => [],
				'badge'     => [],
			], $item );
			$item[ 'active' ] = Services::Request()->query( 'inav' ) === $item[ 'slug' ];

			if ( !empty( $item[ 'sub_items' ] ) ) {
				$item[ 'data' ][ 'toggle' ] = 'collapse';
				$item[ 'href' ] = '#collapse-'.$item[ 'slug' ];
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
				'slug'  => $slug.'-manage',
				'title' => __( 'Manage IPs', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'ips' ),
			],
			[
				'slug'  => $slug.'-blocksettings',
				'title' => __( 'Settings: IP Blocking', 'wp-simple-firewall' ),
				'href'  => $con->getModule_IPs()->getUrl_AdminPage(),
			],
			[
				'slug'  => $slug.'-antibotsettings',
				'title' => __( 'Settings: AntiBot', 'wp-simple-firewall' ),
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

		$subItems = [
			[
				'slug'  => 'audit-log',
				'title' => __( 'View Log', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'audit' ),
			],
			[
				'slug'  => 'audit-settings',
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
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$subItems = [
			[
				'slug'  => 'scans-results',
				'title' => __( 'Scan Results', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'scans' ),
			],
			[
				'slug'  => 'scans-settings',
				'title' => __( 'Scan Settings', 'wp-simple-firewall' ),
				'href'  => $con->getModule_HackGuard()->getUrl_AdminPage(),
			],
		];

		return [
			'slug'      => 'scans',
			'title'     => __( 'Scans', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/shield-shaded.svg' ),
			'sub_items' => $subItems,
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

		$subItems = [
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
		];

		return [
			'slug'      => 'integrations',
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/puzzle-fill.svg' ),
			'title'     => __( 'Integrations', 'wp-simple-firewall' ),
			'sub_items' => $subItems,
		];
	}

	private function reports() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'reports',
			'title' => __( 'Reports', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/graph-up.svg' ),
			'href'  => $mod->getUrl_SubInsightsPage( 'reports' ),
		];
	}

	private function docs() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'  => 'docs',
			'title' => __( "View Docs", 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/book-half.svg' ),
			'href'  => $mod->getUrl_SubInsightsPage( 'docs' ),
		];
	}

	private function tools() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$subItems = [
			[
				'slug'  => 'tools-importexport',
				'title' => __( 'Import / Export', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'importexport' ),
			],
			[
				'slug'  => 'tools-notes',
				'title' => __( 'Admin Notes', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'notes' ),
			],
			[
				'slug'  => 'tools-debug',
				'title' => __( "Debug Info", 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'debug' ),
			]
		];

		return [
			'slug'      => 'tools',
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->urls->forImage( 'bootstrap/tools.svg' ),
			'sub_items' => $subItems,
		];
	}

	private function traffic() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$subItems = [
			[
				'slug'  => 'traffic-log',
				'title' => __( 'View Log', 'wp-simple-firewall' ),
				'href'  => $mod->getUrl_SubInsightsPage( 'traffic' ),
			],
			[
				'slug'  => 'traffic-ratelimitsettings',
				'title' => __( 'Settings: Rate Limiting', 'wp-simple-firewall' ),
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
				'slug'  => 'users-settings',
				'title' => __( 'Settings: Sessions', 'wp-simple-firewall' ),
				'href'  => $con->getModule_UserManagement()
							   ->getUrl_DirectLinkToSection( 'section_user_session_management' ),
			],
			[
				'slug'  => 'users-passwords',
				'title' => __( 'Settings: Password Policies', 'wp-simple-firewall' ),
				'href'  => $con->getModule_UserManagement()->getUrl_DirectLinkToSection( 'section_passwords' ),
			],
			[
				'slug'  => 'users-suspend',
				'title' => __( 'Settings: User Suspension', 'wp-simple-firewall' ),
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
}