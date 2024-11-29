<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Zone;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Wizards;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	ActivityLogging,
	InstantAlerts,
	IpBlockingRules,
	LoginHide,
	Modules\ModuleIntegrations,
	Modules\ModulePlugin,
	Modules\ModuleScans,
	Reporting,
	RequestLogging,
	SilentCaptcha,
	Whitelabel
};
use FernleafSystems\Wordpress\Services\Services;

class NavMenuBuilder {

	use PluginControllerConsumer;

	public function build() :array {
		$menu = [
			$this->dashboard(),
			$this->zones(),
			$this->ips(),
			$this->scans(),
			$this->activity(),
			$this->rules(),
			$this->tools(),
			$this->reports(),
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
			'slug'     => PluginNavs::NAV_IPS,
			'title'    => __( 'Bots & IP Rules', 'wp-simple-firewall' ),
			'subtitle' => __( "Blocked & Bypass IPs", 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'diagram-3' ),
			'href'     => $con->plugin_urls->adminIpRules(),
			'active'   => $this->inav() === PluginNavs::NAV_IPS,
			'introjs'  => [
				'title' => __( 'IP Rules', 'wp-simple-firewall' ),
				'body'  => __( "Review IP Rules that control whether a site visitor is blocked.", 'wp-simple-firewall' ),
			],
//			'config'   => $this->createConfigItemForNav( PluginNavs::NAV_IPS,
//				[
//					IpBlockingRules::Slug(),
//					SilentCaptcha::Slug(),
//				],
//				__( 'Edit IP block settings', 'wp-simple-firewall' )
//			),
		];
	}

	private function activity() :array {
		$con = self::con();
		return [
			'slug'      => PluginNavs::NAV_ACTIVITY,
			'title'     => __( 'Activity Logs', 'wp-simple-firewall' ),
			'subtitle'  => __( "All WP Site Activity", 'wp-simple-firewall' ),
			//			'href'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
			'img'       => $con->svgs->raw( 'person-lines-fill' ),
			'active'    => $this->inav() === PluginNavs::NAV_ACTIVITY,
			'introjs'   => [
				'title' => __( 'Activity Log', 'wp-simple-firewall' ),
				'body'  => __( "Review all important activity on your site - see the Who, What, When and Where.", 'wp-simple-firewall' ),
			],
			'config'    => $this->createConfigItemForNav( PluginNavs::NAV_ACTIVITY,
				[
					ActivityLogging::Slug(),
					RequestLogging::Slug()
				],
				__( 'Edit logging settings', 'wp-simple-firewall' )
			),
			'sub_items' => [
				$this->createSubItemForNavAndSub( __( 'WP Activity Log' ), PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				$this->createSubItemForNavAndSub( __( 'HTTP Request Log' ), PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				$this->createSubItemForNavAndSub( __( 'Live HTTP Log' ), PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
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
//			'config'    => $this->createConfigItemForNav( PluginNavs::NAV_SCANS,
//				[ ModuleScans::Slug(), ],
//				__( 'Edit all scan settings', 'wp-simple-firewall' )
//			),
			'sub_items' => [
				$this->createSubItemForNavAndSub(
					__( 'Results', 'wp-simple-firewall' ),
					PluginNavs::NAV_SCANS,
					PluginNavs::SUBNAV_SCANS_RESULTS
				),
				$this->createSubItemForNavAndSub(
					__( 'Run', 'wp-simple-firewall' ),
					PluginNavs::NAV_SCANS,
					PluginNavs::SUBNAV_SCANS_RUN
				),
			],
		];
	}

	private function dashboard() :array {
		$con = self::con();
		return [
			'slug'     => PluginNavs::NAV_DASHBOARD,
			'title'    => __( 'Dashboard', 'wp-simple-firewall' ),
			'subtitle' => __( 'Security At A Glance', 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'speedometer' ),
			'href'     => $con->plugin_urls->adminHome(),
			'introjs'  => [
				'title' => __( 'Security Overview', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Review your entire %s configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ), $con->labels->Name ),
			],
			'config'   => $this->createConfigItemForNav( PluginNavs::NAV_DASHBOARD,
				[ ModulePlugin::Slug() ],
				__( 'Edit general plugin settings', 'wp-simple-firewall' )
			),
		];
	}

	private function zones() :array {
		$con = self::con();

		$subItems = [];
		foreach ( $con->comps->zones->getZones() as $zone ) {
			$slug = $zone::Slug();
			$subItems[ $slug ] = [
				'slug'    => PluginNavs::NAV_ZONES.'-'.$slug,
				'title'   => $zone->title(),
				'tooltip' => $zone->subtitle(),
				'href'    => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES, $slug ),
				'classes' => \array_filter( \array_merge( $this->getBaseDynamicLoadClasses(), [] ) ),
				'config'  => $zone->getAction_Config(),
				'data'    => [
					'dynamic_page_load' => \wp_json_encode( [
						'dynamic_load_slug' => Zone::SLUG,
						'dynamic_load_data' => [
							'zone_slug' => $slug,
						],
					] ),
				],
				'active'  => $this->inav() === PluginNavs::NAV_ZONES && $this->subnav() === $slug,
			];
		}

		return [
			'slug'      => PluginNavs::NAV_ZONES,
			'title'     => __( 'Security Zones', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Setup Your Security Zones', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'grid-1x2-fill' ),
			'sub_items' => $subItems,
			'introjs'   => [
				'title' => __( 'Security Zones', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Security Zones are the primary areas to configure your site security.", 'wp-simple-firewall' ), $con->labels->Name ),
			],
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
					'href'   => 'https://clk.shldscrty.com/shieldfreetrialinplugin',
					'target' => '_blank',
				],
				[
					'slug'   => 'license-features',
					'href'   => 'https://clk.shldscrty.com/gp',
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
			'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ),
			'sub_items' => $subItems,
		];
	}

	private function rules() :array {
		return [
			'slug'      => PluginNavs::NAV_RULES,
			'title'     => __( 'Custom Rules', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Custom Security Rules', 'wp-simple-firewall' ),
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
		$con = self::con();
		$pageURLs = $con->plugin_urls;
		$zoneCon = $con->comps->zones;
		return [
			'slug'      => PluginNavs::NAV_TOOLS,
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'subtitle'  => __( "Import, Whitelabel, Wizard", 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'tools' ),
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
				\array_merge(
					$zoneCon->getZoneComponent( Whitelabel::Slug() )->getActions()[ 'config' ],
					[
						'slug'  => PluginNavs::NAV_TOOLS.'-whitelabel',
						'title' => __( 'White Label', 'wp-simple-firewall' ),
					]
				),
				\array_merge(
					$zoneCon->getZoneComponent( LoginHide::Slug() )->getActions()[ 'config' ],
					[
						'slug'  => PluginNavs::NAV_TOOLS.'-loginhide',
						'title' => __( 'Hide Login', 'wp-simple-firewall' ),
					]
				),
				\array_merge(
					$zoneCon->getZoneComponent( ModuleIntegrations::Slug() )->getActions()[ 'config' ],
					[
						'slug'  => PluginNavs::NAV_TOOLS.'-integrations',
						'title' => __( 'Integrations', 'wp-simple-firewall' ),
					]
				),
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
			'config'   => $this->createConfigItemForNav( PluginNavs::NAV_REPORTS,
				[
					InstantAlerts::Slug(),
					Reporting::Slug()
				],
				__( 'Edit reporting settings', 'wp-simple-firewall' )
			),
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

	private function createConfigItemForNav( string $primaryNavSlug, array $componentSlugs, string $tooltip = '' ) :array {
		return [
			'slug'    => $primaryNavSlug.'-config',
			'title'   => __( 'Config', 'wp-simple-firewall' ),
			'img'     => self::con()->svgs->raw( 'gear' ),
			'tooltip' => empty( $tooltip ) ? __( 'Edit Settings' ) : $tooltip,
			'classes' => [
				'zone_component_action',
			],
			'data'    => [
				'zone_component_action' => ZoneComponentConfig::SLUG,
				'zone_component_slug'   => \implode( ',', $componentSlugs ),
			],
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

	private function getBaseDynamicLoadClasses() :array {
		return [
			'dynamic_body_load',
			'body_content_link'
		];
	}
}