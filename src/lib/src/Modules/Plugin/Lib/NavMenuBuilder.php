<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Config;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Wizards;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NavMenuBuilder {

	use PluginControllerConsumer;

	public function build() :array {
		$menu = [
			$this->overview(),
			$this->ips(),
			$this->scans(),
			$this->activity(),
			$this->traffic(),
			$this->users(),
			$this->configuration(),
			$this->reports(),
			$this->tools(),
			$this->gopro(),
			$this->docs(),
		];

		$isSecAdmin = $this->con()->isPluginAdmin();
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
		$con = $this->con();
		$slug = PluginURLs::NAV_IP_RULES;
		return [
			'slug'      => $slug,
			'title'     => __( 'IP Rules', 'wp-simple-firewall' ),
			'subtitle'  => __( "Blocked & Bypass IPs", 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'diagram-3' ),
			'img_hover' => $con->svgs->raw( 'diagram-3-fill' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginURLs::NAV_IP_RULES ),
			'active'    => $this->inav() === PluginURLs::NAV_IP_RULES,
			'introjs'   => [
				'title' => __( 'IP Rules', 'wp-simple-firewall' ),
				'body'  => __( "Protection start by detecting bad bots - Review all IP Rules that have an impact on your site visitors.", 'wp-simple-firewall' ),
			],
		];
	}

	private function activity() :array {
		$con = $this->con();
		$slug = PluginURLs::NAV_ACTIVITY_LOG;
		return [
			'slug'     => $slug.'-log',
			'title'    => __( 'Activity', 'wp-simple-firewall' ),
			'subtitle' => __( "All WP Site Activity", 'wp-simple-firewall' ),
			'img'      => $this->con()->svgs->raw( 'person-lines-fill' ),
			'href'     => $con->plugin_urls->adminTopNav( $slug ),
			'active'   => $this->inav() === $slug,
			'introjs'  => [
				'title' => __( 'Activity Log', 'wp-simple-firewall' ),
				'body'  => __( "Review all important activity on your site - see the Who, What, When and Where.", 'wp-simple-firewall' ),
			],
		];
	}

	private function scans() :array {
		$con = $this->con();
		return [
			'slug'      => 'scans',
			'title'     => __( 'Scans', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Results & Manual Scans', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'shield-shaded' ),
			'img_hover' => $con->svgs->raw( 'shield-fill' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ),
			'active'    => \in_array( $this->inav(), [ PluginNavs::NAV_SCANS_RESULTS, PluginNavs::NAV_SCANS_RUN ] ),
			'introjs'   => [
				'title' => __( 'Security Scans', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Run a %s scan at any time, or view the results from the latest scan.", 'wp-simple-firewall' ),
					$this->con()->getHumanName() ),
			],
		];
	}

	private function overview() :array {
		$con = $this->con();
		return [
			'slug'     => 'overview',
			'title'    => __( 'Overview', 'wp-simple-firewall' ),
			'subtitle' => __( 'Security Posture At A Glance', 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'speedometer' ),
			'href'     => $con->plugin_urls->adminTopNav( PluginURLs::NAV_OVERVIEW ),
			'introjs'  => [
				'title' => __( 'Security Overview', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "Review your entire %s configuration at a glance to see what's working and what's not.", 'wp-simple-firewall' ),
					$con->getHumanName() ),
			],
		];
	}

	private function configuration() :array {
		$con = $this->con();

		$slug = 'configuration';

		$baseClasses = [
			'dynamic_body_load',
			'body_content_link'
		];

		$subItems = [];
		foreach ( $con->modules as $module ) {
			$cfg = $module->cfg;
			if ( $cfg->properties[ 'show_module_options' ] ) {
				$subItems[ $cfg->slug ] = [
					'mod_slug'      => $cfg->slug,
					'slug'          => $slug.'-'.$cfg->slug,
					'title'         => __( $cfg->properties[ 'sidebar_name' ], 'wp-simple-firewall' ),
					'tooltip'       => $module->isModOptEnabled() ?
						sprintf( 'Configure options for %s', __( $cfg->properties[ 'sidebar_name' ], 'wp-simple-firewall' ) )
						: sprintf( '%s: %s', __( 'Warning' ), __( 'Module is completely disabled' ) ),
					'href'          => $con->plugin_urls->modCfg( $module ),
					// 'href'          => $this->getOffCanvasJavascriptLinkForModule( $module ),
					'classes'       => \array_filter( \array_merge( $baseClasses, [
						$module->isModOptEnabled() ? '' : 'text-danger'
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
					'menu_priority' => $cfg->menus[ 'config_menu_priority' ],
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
			'slug'      => $slug,
			'title'     => __( 'Config', 'wp-simple-firewall' ),
			'subtitle'  => __( "Setup Your Security", 'wp-simple-firewall' ),
			'img'       => $this->con()->svgs->raw( 'sliders' ),
			'introjs'   => [
				'title' => __( 'Plugin Configuration', 'wp-simple-firewall' ),
				'body'  => sprintf( __( "%s is a big plugin split into modules, and each with their own options - use these jump-off points to find the specific option you need.", 'wp-simple-firewall' ),
					$this->con()->getHumanName() ),
			],
			'sub_items' => $subItems,
		];
	}

	private function docs() :array {
		return [
			'slug'     => 'docs',
			'title'    => __( 'Docs', 'wp-simple-firewall' ),
			'subtitle' => __( 'Changelog, Knowledgebase', 'wp-simple-firewall' ),
			'img'      => $this->con()->svgs->raw( 'book-half' ),
			'href'     => $this->con()->plugin_urls->adminTopNav( PluginURLs::NAV_DOCS ),
		];
	}

	private function gopro() :array {
		$con = $this->con();
		if ( $con->isPremiumActive() ) {
			$subItems = [];
		}
		else {
			$subItems = [
				[
					'slug'   => 'license-gopro',
					'title'  => __( 'Check License', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTopNav( PluginURLs::NAV_LICENSE ),
					'active' => $this->inav() === PluginURLs::NAV_LICENSE
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
			'subtitle'  => __( 'Supercharged Security', 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'award' ),
			'img_hover' => $con->svgs->raw( 'award-fill' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginURLs::NAV_LICENSE ),
			'sub_items' => $subItems,
		];
	}

	private function tools() :array {
		$con = $this->con();
		$pageURLs = $con->plugin_urls;
		$slug = 'tools';
		return [
			'slug'      => $slug,
			'title'     => __( 'Tools', 'wp-simple-firewall' ),
			'subtitle'  => __( "Import, Whitelabel, Wizard", 'wp-simple-firewall' ),
			'img'       => $this->con()->svgs->raw( 'tools' ),
			'introjs'   => [
				'title' => __( 'Security Tools', 'wp-simple-firewall' ),
				'body'  => __( "Important security tools, such a import/export, whitelabel and admin notes.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-importexport',
					'title'  => __( 'Import', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTopNav( PluginURLs::NAV_IMPORT_EXPORT ),
					'active' => $this->inav() === PluginURLs::NAV_IMPORT_EXPORT
				],
				[
					'slug'  => $slug.'-whitelabel',
					'title' => __( 'White Label', 'wp-simple-firewall' ),
					'href'  => $con->plugin_urls->offCanvasConfigRender( 'section_whitelabel' ),
				],
				[
					'slug'   => $slug.'-notes',
					'title'  => __( 'Admin Notes', 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTopNav( PluginURLs::NAV_NOTES ),
					'active' => $this->inav() === PluginURLs::NAV_NOTES
				],
				[
					'slug'   => $slug.'-'.PluginURLs::NAV_WIZARD,
					'title'  => __( 'Guided Setup', 'wp-simple-firewall' ),
					'href'   => $pageURLs->wizard( Wizards::WIZARD_WELCOME ),
					'active' => $this->inav() === PluginURLs::NAV_WIZARD
				],
				[
					'slug'   => $slug.'-debug',
					'title'  => __( "Debug Info", 'wp-simple-firewall' ),
					'href'   => $pageURLs->adminTopNav( PluginURLs::NAV_DEBUG ),
					'active' => $this->inav() === PluginURLs::NAV_DEBUG
				]
			],
		];
	}

	private function reports() :array {
		$con = $this->con();
		$slug = 'reports';
		return [
			'slug'      => $slug,
			'title'     => __( 'Reports', 'wp-simple-firewall' ),
			'subtitle'  => __( "See What's Happening", 'wp-simple-firewall' ),
			'img'       => $con->svgs->raw( 'clipboard-data-fill' ),
			'introjs'   => [
				'title' => __( 'Reports', 'wp-simple-firewall' ),
				'body'  => __( "Security Reports.", 'wp-simple-firewall' ),
			],
			'sub_items' => [
				[
					'slug'   => $slug.'-change',
					'title'  => __( 'Changes', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTopNav( PluginURLs::NAV_REPORTS, PluginNavs::SUBNAV_CHANGE_TRACK ),
					'active' => $this->inav() === PluginURLs::NAV_REPORTS && $this->subnav() === PluginNavs::SUBNAV_CHANGE_TRACK,
				],
				[
					'slug'   => $slug.'-charts',
					'title'  => __( 'Charts', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTopNav( PluginURLs::NAV_REPORTS, PluginNavs::SUBNAV_CHARTS ),
					'active' => $this->inav() === PluginURLs::NAV_REPORTS && $this->subnav() === PluginNavs::SUBNAV_CHARTS,
				],
				[
					'slug'   => $slug.'-stats',
					'title'  => __( 'Stats', 'wp-simple-firewall' ),
					'href'   => $con->plugin_urls->adminTopNav( PluginURLs::NAV_STATS, PluginNavs::SUBNAV_STATS ),
					'active' => $this->inav() === PluginURLs::NAV_STATS
				],
			],
		];
	}

	private function traffic() :array {
		$con = $this->con();
		$slug = PluginURLs::NAV_TRAFFIC_VIEWER;
		return [
			'slug'     => $slug.'-log',
			'title'    => __( 'Traffic', 'wp-simple-firewall' ),
			'subtitle' => __( "View HTTP Requests", 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'stoplights' ),
			'href'     => $con->plugin_urls->adminTopNav( $slug ),
			'active'   => $this->inav() === $slug,
			'introjs'  => [
				'title' => __( 'Traffic Log', 'wp-simple-firewall' ),
				'body'  => __( "Dig deeper into your WordPress traffic as it hits your site.", 'wp-simple-firewall' ),
			],
		];
	}

	private function users() :array {
		$con = $this->con();
		return [
			'slug'     => 'users',
			'title'    => __( 'Users', 'wp-simple-firewall' ),
			'subtitle' => __( "Manage User Sessions", 'wp-simple-firewall' ),
			'img'      => $con->svgs->raw( 'person-badge' ),
			'href'     => $con->plugin_urls->adminTopNav( PluginURLs::NAV_USER_SESSIONS ),
			'introjs'  => [
				'title' => __( 'User Management', 'wp-simple-firewall' ),
				'body'  => __( 'View sessions, and configure session timeouts and passwords requirements.', 'wp-simple-firewall' ),
			],
			//			'sub_items' => [
			//				[
			//					'slug'  => 'users-sessions',
			//					'title' => __( 'View Sessions', 'wp-simple-firewall' ),
			//					'href'  => $con->plugin_urls->adminTop( PluginURLs::NAV_USER_SESSIONS ),
			//				],
			//				[
			//					'slug'  => 'users-secadmin',
			//					'title' => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ),
			//					'href'  => $con->plugin_urls->offCanvasConfigRender( 'section_security_admin_settings' ),
			//				],
			//				[
			//					'slug'  => 'users-settings',
			//					'title' => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), __( 'Sessions', 'wp-simple-firewall' ) ),
			//					'href'  => $con->plugin_urls->offCanvasConfigRender( 'section_user_session_management' ),
			//				],
			//				[
			//					'slug'  => 'users-passwords',
			//					'title' => __( 'Password Policies', 'wp-simple-firewall' ),
			//					'href'  => $con->plugin_urls->offCanvasConfigRender( 'section_passwords' ),
			//				],
			//				[
			//					'slug'  => 'users-suspend',
			//					'title' => __( 'User Suspension', 'wp-simple-firewall' ),
			//					'href'  => $con->plugin_urls->offCanvasConfigRender( 'section_suspend' ),
			//				],
			//			],
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