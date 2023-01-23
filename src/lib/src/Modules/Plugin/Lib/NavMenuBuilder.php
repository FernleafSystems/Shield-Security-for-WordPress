<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Config;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NavMenuBuilder {

	use ModConsumer;

	public function build() :array {
		$menu = [
			$this->overview(),
			$this->configuration(),
			$this->ips(),
			$this->scans(),
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

			if ( empty( $item[ 'sub_items' ] ) ) {
				$item[ 'classes' ][] = 'body_content_link';
			}
			else {
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
		$slug = PluginURLs::NAV_IP_RULES;
		return [
			'slug'      => $slug,
			'title'     => __( 'IPs & Bots', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'bootstrap/diagram-3.svg' ),
			'img_hover' => $con->svgs->raw( 'bootstrap/diagram-3-fill.svg' ),
			'introjs'   => [
				'body' => __( "Protection begins by detecting bad bots - Review and Analyse all visitor IPs that have an impact on your site.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-manage',
					'title'  => __( 'IP Rules', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_IP_RULES ),
					'active' => $this->getInav() === PluginURLs::NAV_IP_RULES,
				],
				[
					'slug'  => $slug.'-blocksettings',
					'title' => __( 'IP Block Config', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( $con->getModule_IPs()->getSlug() ),
				],
				[
					'slug'  => $slug.'-antibotsettings',
					'title' => __( 'AntiBot Config', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_antibot' ),
				],
			],
		];
	}

	private function audit() :array {
		$con = $this->getCon();
		$slug = PluginURLs::NAV_ACTIVITY_LOG;
		return [
			'slug'      => $slug,
			'title'     => __( 'Activity Log', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/person-lines-fill.svg' ),
			'introjs'   => [
				'body' => __( "Track and review all important actions taken on your site - see the Who, What and When.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-log',
					'title'  => __( 'View Log', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_ACTIVITY_LOG ),
					'active' => $this->getInav() === PluginURLs::NAV_ACTIVITY_LOG,
				],
				[
					'slug'  => $slug.'-settings',
					'title' => __( 'Configure', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( $con->getModule_AuditTrail()->getSlug() ),
				],
				[
					'slug'   => 'audit-glossary',
					'title'  => __( 'Glossary', 'wp-simple-firewall' ),
					'href'   => 'https://shsec.io/audittrailglossary',
					'target' => '_blank',
				],
			],
		];
	}

	private function scans() :array {
		$con = $this->getCon();
		$slug = 'scans';
		$subItems = [
			[
				'slug'   => $slug.'-run',
				'title'  => __( 'Run Scan', 'wp-simple-firewall' ),
				'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RUN ),
				'active' => $this->getInav() === PluginURLs::NAV_SCANS_RUN,
			],
			[
				'slug'   => $slug.'-results',
				'title'  => __( 'Scan Results', 'wp-simple-firewall' ),
				'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RESULTS ),
				'active' => $this->getInav() === PluginURLs::NAV_SCANS_RESULTS,
			],
			[
				'slug'  => $slug.'-settings',
				'title' => __( 'Configure', 'wp-simple-firewall' ),
				'href'  => $this->getOffCanvasJavascriptLinkFor( $con->getModule_HackGuard()->getSlug() ),
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

	private function stats() :array {
		$con = $this->getCon();
		return [
			'slug'      => 'reports',
			'title'     => __( 'Reports', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'bootstrap/bar-chart-line.svg' ),
			'img_hover' => $con->svgs->raw( 'bootstrap/bar-chart-line-fill.svg' ),
			'href'      => $con->plugin_urls->adminTop( PluginURLs::NAV_REPORTS ),
			'introjs'   => [
				'body' => __( 'Reports use the built-in stats to show you how Shield is working to secure your site.' ),
			],
			'sub_items' => [
				[
					'slug'   => 'reports-stats',
					'title'  => __( 'Stats', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_STATS ),
					'active' => $this->getInav() === PluginURLs::NAV_STATS
				],
				[
					'slug'   => 'reports-charts',
					'title'  => __( 'Charts', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_REPORTS ),
					'active' => $this->getInav() === PluginURLs::NAV_REPORTS
				],
			],
		];
	}

	private function overview() :array {
		$con = $this->getCon();
		return [
			'slug'    => 'overview',
			'title'   => __( 'Overview', 'wp-simple-firewall' ),
			'img'     => $con->svgs->raw( 'bootstrap/speedometer.svg' ),
			'href'    => $con->plugin_urls->adminTop( PluginURLs::NAV_OVERVIEW ),
			'introjs' => [
				'body' => sprintf( __( "Review your entire %s configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ),
					$con->getHumanName() ),
			],
		];
	}

	private function configuration() :array {
		$con = $this->getCon();

		$slug = 'configuration';

		$subItems = [];
		foreach ( $con->modules as $module ) {
			$cfg = $module->cfg;
			if ( $cfg->properties[ 'show_module_options' ] ) {
				$subItems[ $cfg->slug ] = [
					'mod_slug'      => $cfg->slug,
					'slug'          => $slug.'-'.$cfg->slug,
					'title'         => __( $cfg->properties[ 'sidebar_name' ], 'wp-simple-firewall' ),
					'href'          => $con->plugin_urls->modCfg( $module ),
					// 'href'          => $this->getOffCanvasJavascriptLinkForModule( $module ),
					'classes'       => [ 'dynamic_body_load', 'body_content_link' ],
					'data'          => [
						'dynamic_page_load' => json_encode( [
							'dynamic_load_slug' => Config::SLUG,
							'dynamic_load_data' => [
								'primary_mod_slug' => $cfg->slug,
							],
						] ),
					],
					'active'        => Services::Request()->query( Constants::NAV_SUB_ID ) === $cfg->slug,
					'menu_priority' => $cfg->menus[ 'config_menu_priority' ],
				];
			}
		}

		uasort( $subItems, function ( $a, $b ) {
			if ( $a[ 'menu_priority' ] == $b[ 'menu_priority' ] ) {
				return 0;
			}
			return ( $a[ 'menu_priority' ] < $b[ 'menu_priority' ] ) ? -1 : 1;
		} );

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
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_spam' ),
				],
				[
					'slug'  => 'integrations-login',
					'title' => __( 'Custom Login Forms', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_user_forms' ),
				],
			],
		];
	}

	private function docs() :array {
		return [
			'slug'  => 'docs',
			'title' => __( "View Docs", 'wp-simple-firewall' ),
			'img'   => $this->getCon()->svgs->raw( 'bootstrap/book-half.svg' ),
			'href'  => $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_DOCS ),
		];
	}

	private function gopro() :array {
		$con = $this->getCon();
		if ( $con->isPremiumActive() ) {
			$subItems = [];
		}
		else {
			$subItems = [
				[
					'slug'   => 'license-gopro',
					'title'  => __( 'Check License', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_LICENSE ),
					'active' => $this->getInav() === PluginURLs::NAV_LICENSE
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
			'title'     => $con->isPremiumActive() ? __( 'ShieldPRO', 'wp-simple-firewall' ) : __( 'Go PRO!', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'bootstrap/award.svg' ),
			'img_hover' => $con->svgs->raw( 'bootstrap/award-fill.svg' ),
			'href'      => $con->plugin_urls->adminTop( PluginURLs::NAV_LICENSE ),
			'sub_items' => $subItems,
		];
	}

	private function tools() :array {
		$con = $this->getCon();
		$pageURLs = $con->plugin_urls;
		$slug = 'tools';
		return [
			'slug'      => $slug,
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'img'       => $this->getCon()->svgs->raw( 'bootstrap/tools.svg' ),
			'introjs'   => [
				'body' => __( "Important security tools, such a import/export, whitelabel and admin notes.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-importexport',
					'title'  => __( 'Import / Export', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTop( PluginURLs::NAV_IMPORT_EXPORT ),
					'active' => $this->getInav() === PluginURLs::NAV_IMPORT_EXPORT
				],
				[
					'slug'  => $slug.'-whitelabel',
					'title' => __( 'White Label', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_whitelabel' ),
				],
				[
					'slug'   => $slug.'-notes',
					'title'  => __( 'Admin Notes', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTop( PluginURLs::NAV_NOTES ),
					'active' => $this->getInav() === PluginURLs::NAV_NOTES
				],
				[
					'slug'   => $slug.'-'.PluginURLs::NAV_WIZARD,
					'title'  => __( 'Guided Setup', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTop( PluginURLs::NAV_WIZARD ),
					'active' => $this->getInav() === PluginURLs::NAV_WIZARD
				],
				[
					'slug'   => $slug.'-rules',
					'title'  => __( 'Rules', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTop( PluginURLs::NAV_RULES_VIEW ),
					'active' => $this->getInav() === PluginURLs::NAV_RULES_VIEW
				],
				[
					'slug'   => $slug.'-debug',
					'title'  => __( "Debug Info", 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTop( PluginURLs::NAV_DEBUG ),
					'active' => $this->getInav() === PluginURLs::NAV_DEBUG
				]
			],
		];
	}

	private function traffic() :array {
		$con = $this->getCon();
		$slug = PluginURLs::NAV_TRAFFIC_VIEWER;
		return [
			'slug'      => 'traffic',
			'title'     => __( 'Traffic', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'bootstrap/stoplights.svg' ),
			'introjs'   => [
				'body' => __( "Monitor and watch traffic as it hits your site.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-log',
					'title'  => __( 'View Traffic', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTop( PluginURLs::NAV_TRAFFIC_VIEWER ),
					'active' => $this->getInav() === PluginURLs::NAV_TRAFFIC_VIEWER,
				],
				[
					'slug'  => $slug.'-settings',
					'title' => __( 'Configure', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_traffic_options' ),
				],
			],
		];
	}

	private function users() :array {
		$con = $this->getCon();
		return [
			'slug'      => 'users',
			'title'     => __( 'Users', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'bootstrap/person-badge.svg' ),
			'introjs'   => [
				'body' => __( 'View sessions, and configure session timeouts and passwords requirements.', 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'  => 'users-sessions',
					'title' => __( 'View Sessions', 'wp-simple-firewall' ),
					'href'  => $con->plugin_urls->adminTop( PluginURLs::NAV_USER_SESSIONS ),
				],
				[
					'slug'  => 'users-secadmin',
					'title' => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_security_admin_settings' ),
				],
				[
					'slug'  => 'users-settings',
					'title' => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), __( 'Sessions', 'wp-simple-firewall' ) ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_user_session_management' ),
				],
				[
					'slug'  => 'users-passwords',
					'title' => __( 'Password Policies', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_passwords' ),
				],
				[
					'slug'  => 'users-suspend',
					'title' => __( 'User Suspension', 'wp-simple-firewall' ),
					'href'  => $this->getOffCanvasJavascriptLinkFor( 'section_suspend' ),
				],
			],
		];
	}

	private function getIntroJsTourID() :string {
		return 'navigation_v1';
	}

	private function getInav() :string {
		return (string)Services::Request()->query( Constants::NAV_ID );
	}

	private function getOffCanvasJavascriptLinkFor( string $for ) :string {
		return $this->getMod()->getUIHandler()->getOffCanvasJavascriptLinkFor( $for );
	}
}