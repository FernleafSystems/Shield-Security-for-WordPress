<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Config;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Wizards;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NavMenuBuilder {

	use PluginControllerConsumer;

	public function build() :array {
		$menu = [
			$this->dashboard(),
			$this->reports(),
			$this->ips(),
			$this->scans(),
			$this->activity(),
			$this->traffic(),
			$this->configuration(),
			$this->rules(),
			$this->tools(),
			$this->gopro(),
		];

		$isSecAdmin = self::con()->isPluginAdmin();
		foreach ( $menu as $key => $item ) {
			$item = Services::DataManipulation()->mergeArraysRecursive( [
				'slug'      => 'no-slug',
				'title'     => __( 'NO TITLE', 'wp-simple-firewall' ),
				'href'      => 'javascript:{}',
				'classes'   => [],
				'id'        => '',
				'active'    => $this->inav() === $item[ 'slug' ],
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
				$item[ 'sub_items' ] = \array_map( function ( $sub ) use ( $isSecAdmin ) {
					if ( empty( $sub[ 'classes' ] ) ) {
						$sub[ 'classes' ] = [];
					}
					if ( $sub[ 'active' ] ?? false ) {
						$sub[ 'classes' ][] = 'active';
					}
					if ( !$isSecAdmin ) {
						$sub[ 'classes' ][] = 'disabled';
					}
					return $sub;
				}, $item[ 'sub_items' ] );

				// Set parent active if any sub-items are active
				if ( !$item[ 'active' ] ) {
					$item[ 'active' ] = \count( \array_filter( $item[ 'sub_items' ], function ( $sub ) {
						return $sub[ 'active' ] ?? false;
					} ) );
				}
			}

			if ( $item[ 'active' ] ) {
				$item[ 'classes' ][] = 'active';
			}

			if ( !$isSecAdmin ) {
				$item[ 'classes' ][] = 'disabled';
			}

			$menu[ $key ] = $item;
		}

		return $menu;
	}

	private function ips() :array {
		$con = self::con();
		return [
			'slug'      => PluginNavs::NAV_IPS,
			'title'     => __( 'IP Rules', 'wp-simple-firewall' ),
			'subtitle'  => __( "Blocked & Bypass IPs", 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'diagram-3' ),
			'img_hover' => $con->svgs->raw( 'diagram-3-fill' ),
			'href'      => $con->plugin_urls->adminIpRules(),
			'active'    => $this->inav() === PluginNavs::NAV_IPS,
			'introjs'   => [
				'title' => __( 'IP Rules', 'wp-simple-firewall' ),
				'body'  => __( "Protection start by detecting bad bots - Review all IP Rules that have an impact on your site visitors.", 'wp-simple-firewall' ),
			],
		];
	}

	private function activity() :array {
		$con = self::con();
		return [
			'slug'     => PluginNavs::NAV_ACTIVITY,
			'title'    => __( 'Activity', 'wp-simple-firewall' ),
			'subtitle' => __( "All WP Site Activity", 'wp-simple-firewall' ),
			'img'      => self::con()->svgs->raw( 'person-lines-fill' ),
			'href'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
			'active'   => $this->inav() === PluginNavs::NAV_ACTIVITY,
			'introjs'  => [
				'title' => __( 'Activity Log', 'wp-simple-firewall' ),
				'body'  => __( "Review all important activity on your site - see the Who, What, When and Where.", 'wp-simple-firewall' ),
			],
		];
	}

	private function scans() :array {
		$con = self::con();
		return [
			'slug'      => PluginNavs::NAV_SCANS,
			'title'     => __( 'Scans', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Results & Manual Scans', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'shield-shaded' ),
			'img_hover' => $con->svgs->raw( 'shield-fill' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			'active'    => $this->inav() === PluginNavs::NAV_SCANS,
			'introjs'   => [
				'title' => __( 'Security Scans', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Run a %s scan at any time, or view the results from the latest scan.", 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
			],
		];
	}

	private function dashboard() :array {
		$con = self::con();
		return [
			'slug'     => PluginNavs::NAV_DASHBOARD,
			'title'    => __( 'Dashboard', 'wp-simple-firewall' ),
			'subtitle' => __( 'Security Posture At A Glance', 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'speedometer' ),
			'href'     => $con->plugin_urls->adminHome(),
			'introjs'  => [
				'title' => __( 'Security Overview', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Review your entire %s configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ),
					$con->getHumanName() ),
			],
		];
	}

	private function configuration() :array {
		$con = self::con();

		$baseClasses = [
			'dynamic_body_load',
			'body_content_link'
		];

		$subItems = [];
		foreach ( $con->modules as $mod ) {
			$cfg = $mod->cfg;
			if ( $cfg->properties[ 'show_module_options' ] ) {
				$subItems[ $cfg->slug ] = [
					'mod_slug'      => $cfg->slug,
					'slug'          => PluginNavs::NAV_OPTIONS_CONFIG.'-'.$cfg->slug,
					'title'         => __( $cfg->properties[ 'name' ], 'wp-simple-firewall' ),
					'tooltip'       => $mod->isModOptEnabled() ?
						sprintf( 'Configure options for %s', __( $cfg->properties[ 'name' ], 'wp-simple-firewall' ) )
						: sprintf( '%s: %s', __( 'Warning' ), __( 'Module is completely disabled' ) ),
					'href'          => $con->plugin_urls->modCfg( $mod ),
					'classes'       => \array_filter( \array_merge( $baseClasses, [
						$mod->isModOptEnabled() ? '' : 'text-danger'
					] ) ),
					'data'          => [
						'dynamic_page_load' => \json_encode( [
							'dynamic_load_slug' => Config::SLUG,
							'dynamic_load_data' => [
								'mod_slug' => $cfg->slug,
							],
						] ),
					],
					'active'        => Services::Request()->query( Constants::NAV_SUB_ID ) === $cfg->slug,
					'menu_priority' => $cfg->properties[ 'config_menu_priority' ],
				];
			}
		}

		\uasort( $subItems, function ( $a, $b ) {
			if ( $a[ 'menu_priority' ] == $b[ 'menu_priority' ] ) {
				return 0;
			}
			return ( $a[ 'menu_priority' ] < $b[ 'menu_priority' ] ) ? -1 : 1;
		} );

		return [
			'slug'      => PluginNavs::NAV_OPTIONS_CONFIG,
			'title'     => __( 'Config', 'wp-simple-firewall' ),
			'subtitle'  => __( "Setup Your Security", 'wp-simple-firewall' ),
			'img'       => self::con()->svgs->raw( 'sliders' ),
			'introjs'   => [
				'title' => __( 'Plugin Configuration', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "%s is a big plugin split into modules, and each with their own options - use these jump-off points to find the specific option you need.", 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
			],
			'sub_items' => $subItems,
		];
	}

	private function gopro() :array {
		$con = self::con();
		if ( $con->isPremiumActive() ) {
			$subItems = [];
		}
		else {
			$subItems = [
				[
					'slug'   => 'license-gopro',
					'title'  => __( 'Check License', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ),
					'active' => $this->inav() === PluginNavs::NAV_LICENSE
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
			'slug'      => PluginNavs::NAV_LICENSE,
			'title'     => $con->isPremiumActive() ? __( 'ShieldPRO', 'wp-simple-firewall' ) : __( 'Go PRO!', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Supercharged Security', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'award' ),
			'img_hover' => $con->svgs->raw( 'award-fill' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ),
			'sub_items' => $subItems,
		];
	}

	private function rules() :array {
		return [
			'slug'      => PluginNavs::NAV_RULES,
			'title'     => __( 'Rules', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Security Rules', 'wp-simple-firewall' ),
			'img'       => self::con()->svgs->raw( 'node-plus-fill' ),
			'introjs'   => [
				'title' => __( 'Security Rules', 'wp-simple-firewall' ),
				'body'  => __( "Create and view all your custom security rules.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				$this->createSubItemForNavAndSub(
					__( 'Manage', 'wp-simple-firewall' ),
					PluginNavs::NAV_RULES,
					PluginNavs::SUBNAV_RULES_MANAGE
				),
				$this->createSubItemForNavAndSub(
					__( 'New', 'wp-simple-firewall' ),
					PluginNavs::NAV_RULES,
					PluginNavs::SUBNAV_RULES_BUILD
				),
				$this->createSubItemForNavAndSub(
					__( 'Summary', 'wp-simple-firewall' ),
					PluginNavs::NAV_RULES,
					PluginNavs::SUBNAV_RULES_SUMMARY
				),
			],
		];
	}

	private function tools() :array {
		$pageURLs = self::con()->plugin_urls;
		return [
			'slug'      => PluginNavs::NAV_TOOLS,
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'subtitle'  => __( "Import, Whitelabel, Wizard", 'wp-simple-firewall' ),
			'img'       => self::con()->svgs->raw( 'tools' ),
			'introjs'   => [
				'title' => __( 'Security Tools', 'wp-simple-firewall' ),
				'body'  => __( "Important security tools, such a import/export, whitelabel, debug.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				$this->createSubItemForNavAndSub(
					__( 'User Sessions', 'wp-simple-firewall' ),
					PluginNavs::NAV_TOOLS,
					PluginNavs::SUBNAV_TOOLS_SESSIONS
				),
				$this->createSubItemForNavAndSub(
					__( 'Site Lockdown', 'wp-simple-firewall' ),
					PluginNavs::NAV_TOOLS,
					PluginNavs::SUBNAV_TOOLS_BLOCKDOWN
				),
				$this->createSubItemForNavAndSub(
					__( 'Import/Export', 'wp-simple-firewall' ),
					PluginNavs::NAV_TOOLS,
					PluginNavs::SUBNAV_TOOLS_IMPORT
				),
				[
					'slug'    => PluginNavs::NAV_TOOLS.'-whitelabel',
					'title'   => __( 'White Label', 'wp-simple-firewall' ),
					'classes' => [ 'offcanvas_form_mod_cfg' ],
					'data'    => [
						'config_item' => 'section_whitelabel'
					],
				],
				[
					'slug'   => PluginNavs::NAV_TOOLS.'-'.PluginNavs::NAV_WIZARD,
					'title'  => __( 'Guided Setup', 'wp-simple-firewall' ),
					'href'   => $pageURLs->wizard( Wizards::WIZARD_WELCOME ),
					'active' => $this->inav() === PluginNavs::NAV_WIZARD
				],
				$this->createSubItemForNavAndSub(
					__( 'Docs', 'wp-simple-firewall' ),
					PluginNavs::NAV_TOOLS,
					PluginNavs::SUBNAV_TOOLS_DOCS
				),
				$this->createSubItemForNavAndSub(
					__( 'Debug Info', 'wp-simple-firewall' ),
					PluginNavs::NAV_TOOLS,
					PluginNavs::SUBNAV_TOOLS_DEBUG
				),
			],
		];
	}

	private function reports() :array {
		$con = self::con();
		return [
			'slug'     => PluginNavs::NAV_REPORTS,
			'title'    => __( 'Reports', 'wp-simple-firewall' ),
			'subtitle' => __( "See What's Happening", 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'clipboard-data-fill' ),
			'href'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
			'active'   => $this->inav() === PluginNavs::NAV_REPORTS,
			'introjs'  => [
				'title' => __( 'Reports', 'wp-simple-firewall' ),
				'body'  => __( "Security Reports.", 'wp-simple-firewall' ),
			],
		];
	}

	private function traffic() :array {
		$con = self::con();
		return [
			'slug'     => PluginNavs::NAV_TRAFFIC.'-log',
			'title'    => __( 'Site Traffic', 'wp-simple-firewall' ),
			'subtitle' => __( "View HTTP Requests", 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'stoplights' ),
			'href'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
			'active'   => $this->inav() === PluginNavs::NAV_TRAFFIC,
			'introjs'  => [
				'title' => __( 'Traffic Log', 'wp-simple-firewall' ),
				'body'  => __( "Dig deeper into your WordPress traffic as it hits your site.", 'wp-simple-firewall' ),
			],
		];
	}

	private function createSubItemForNavAndSub( string $name, string $nav, string $subnav ) :array {
		return [
			'slug'   => $nav.'-'.$subnav,
			'title'  => $name,
			'href'   => self::con()->plugin_urls->adminTopNav( $nav, $subnav ),
			'active' => $this->inav() === $nav && $this->subnav() === $subnav,
		];
	}

	private function getIntroJsTourID() :string {
		return 'navigation_v1';
	}

	private function inav() :string {
		return (string)Services::Request()->query( Constants::NAV_ID );
	}

	private function subnav() :string {
		return (string)Services::Request()->query( Constants::NAV_SUB_ID );
	}
}