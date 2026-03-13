<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	LoginHide,
	Modules\ModuleIntegrations,
	Whitelabel
};
use FernleafSystems\Wordpress\Services\Services;

class NavMenuBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   back_item:array<string,mixed>|null,
	 *   mode_items:list<array<string,mixed>>,
	 *   tool_items:list<array<string,mixed>>,
	 *   home_license_item:array<string,mixed>|null,
	 *   home_connect_title:string,
	 *   home_connect_items:list<array<string,mixed>>
	 * }
	 */
	public function build() :array {
		$mode = $this->resolveCurrentMode();
		$actionsSummary = $this->getActionsQueueSummary();

		$modeItems = $this->normalizeItems( $this->buildModeItems( $mode, $actionsSummary ) );

		if ( empty( $mode ) ) {
			$licenseItem = $this->buildHomeLicenseItem();
			$connect = $this->buildHomeConnectItems();

			return [
				'back_item'          => null,
				'mode_items'         => $modeItems,
				'tool_items'         => [],
				'home_license_item'  => $licenseItem === null ? null : $this->normalizeItems( [ $licenseItem ] )[ 0 ],
				'home_connect_title' => $connect[ 'title' ],
				'home_connect_items' => $this->normalizeItems( $connect[ 'items' ] ),
			];
		}

		$backItem = $this->buildBackItem();
		return [
			'back_item'          => $this->normalizeItems( [ $backItem ] )[ 0 ],
			'mode_items'         => $modeItems,
			'tool_items'         => $this->normalizeItems( $this->toolsForMode( $mode ) ),
			'home_license_item'  => null,
			'home_connect_title' => '',
			'home_connect_items' => [],
		];
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string} $actionsSummary
	 * @return list<array<string,mixed>>
	 */
	private function buildModeItems( string $currentMode, array $actionsSummary ) :array {
		$items = [];
		foreach ( PluginNavs::allOperatorModes() as $mode ) {
			$entry = PluginNavs::defaultEntryForMode( $mode );
			$item = [
				'slug'    => 'mode-'.$mode,
				'mode'    => $mode,
				'title'   => PluginNavs::modeLabel( $mode ),
				'img'     => $this->modeIconClass( $mode ),
				'href'    => self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] ),
				'active'  => !empty( $currentMode ) && $currentMode === $mode,
				'classes' => [ 'mode-item-link' ],
			];
			if ( $mode === PluginNavs::MODE_ACTIONS && $actionsSummary[ 'total_items' ] > 0 ) {
				$item[ 'badge' ] = [
					'text'   => (string)$actionsSummary[ 'total_items' ],
					'status' => $actionsSummary[ 'severity' ],
				];
			}
			$items[] = $item;
		}
		return $items;
	}

	private function modeIconClass( string $mode ) :string {
		switch ( $mode ) {
			case PluginNavs::MODE_ACTIONS:
				$icon = 'exclamation-triangle-fill';
				break;
			case PluginNavs::MODE_INVESTIGATE:
				$icon = 'search';
				break;
			case PluginNavs::MODE_REPORTS:
				$icon = 'bar-chart-line';
				break;
			case PluginNavs::MODE_CONFIGURE:
			default:
				$icon = 'sliders';
				break;
		}
		return self::con()->svgs->iconClass( $icon );
	}

	private function buildBackItem() :array {
		return [
			'slug'    => 'mode-selector-back',
			'title'   => __( 'Dashboard', 'wp-simple-firewall' ),
			'img'     => self::con()->svgs->iconClass( 'arrow-left' ),
			'href'    => self::con()->plugin_urls->adminHome(),
			'classes' => [ 'sidebar-back-link' ],
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function toolsForMode( string $mode ) :array {
		switch ( $mode ) {
			case PluginNavs::MODE_ACTIONS:
				$items = $this->buildStaticToolItemsForMode( PluginNavs::MODE_ACTIONS );
				break;

			case PluginNavs::MODE_INVESTIGATE:
				$items = $this->buildStaticToolItemsForMode( PluginNavs::MODE_INVESTIGATE );
				break;

			case PluginNavs::MODE_CONFIGURE:
				$items = $this->buildConfigureTools();
				break;

			case PluginNavs::MODE_REPORTS:
			default:
				$items = [];
				break;
		}
		return $items;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildConfigureTools() :array {
		$con = self::con();
		$zoneCon = $con->comps->zones;
		return \array_merge(
			$this->buildStaticToolItemsForMode( PluginNavs::MODE_CONFIGURE ),
			[
			\array_merge(
				$zoneCon->getZoneComponent( Whitelabel::Slug() )->getActions()[ 'config' ],
				[
					'slug'  => PluginNavs::NAV_TOOLS.'-whitelabel',
					'title' => __( 'White Label', 'wp-simple-firewall' ),
					'img'   => $con->svgs->iconClass( 'palette' ),
				]
			),
			\array_merge(
				$zoneCon->getZoneComponent( LoginHide::Slug() )->getActions()[ 'config' ],
				[
					'slug'  => PluginNavs::NAV_TOOLS.'-loginhide',
					'title' => __( 'Hide Login', 'wp-simple-firewall' ),
					'img'   => $con->svgs->iconClass( 'person-lock' ),
				]
			),
			\array_merge(
				$zoneCon->getZoneComponent( ModuleIntegrations::Slug() )->getActions()[ 'config' ],
				[
					'slug'  => PluginNavs::NAV_TOOLS.'-integrations',
					'title' => __( 'Integrations', 'wp-simple-firewall' ),
					'img'   => $con->svgs->iconClass( 'puzzle' ),
				]
			),
			]
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildStaticToolItemsForMode( string $mode ) :array {
		return \array_map(
			fn( array $definition ) :array => $this->buildToolItemFromDefinition( $definition ),
			StaticToolDefinitions::forMode( $mode )
		);
	}

	/**
	 * @param array{
	 *   id:string,
	 *   title:string,
	 *   icon:string,
	 *   nav:string,
	 *   subnav:string
	 * } $definition
	 */
	private function buildToolItemFromDefinition( array $definition ) :array {
		return $this->buildToolItem(
			$definition[ 'nav' ].'-'.$definition[ 'subnav' ],
			$definition[ 'title' ],
			$definition[ 'icon' ],
			self::con()->plugin_urls->adminTopNav( $definition[ 'nav' ], $definition[ 'subnav' ] ),
			$this->isCurrentRoute( $definition[ 'nav' ], $definition[ 'subnav' ] )
		);
	}

	private function buildHomeLicenseItem() :?array {
		$item = $this->gopro();
		if ( empty( $item ) ) {
			return null;
		}
		return [
			'slug'    => $item[ 'slug' ] ?? PluginNavs::NAV_LICENSE,
			'title'   => __( 'Shield Pro License', 'wp-simple-firewall' ),
			'subtitle'=> (string)( $item[ 'subtitle' ] ?? '' ),
			'img'     => (string)( $item[ 'img' ] ?? self::con()->svgs->iconClass( 'award' ) ),
			'href'    => (string)( $item[ 'href' ] ?? self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE, PluginNavs::SUBNAV_LICENSE_CHECK ) ),
			'active'  => (bool)( $item[ 'active' ] ?? false ),
			'classes' => [ 'sidebar-license-link' ],
			'badge'   => [
				'text'   => self::con()->isPremiumActive() ? 'PRO' : __( 'Go PRO!', 'wp-simple-firewall' ),
				'status' => self::con()->isPremiumActive() ? 'good' : 'warning',
			],
		];
	}

	/**
	 * @return array{title:string,items:list<array<string,mixed>>}
	 */
	private function buildHomeConnectItems() :array {
		if ( self::con()->comps->whitelabel->isEnabled() ) {
			return [
				'title' => '',
				'items' => [],
			];
		}

		$connectMeta = $this->connectMetaItem();
		return [
			'title' => (string)( $connectMeta[ 'title' ] ?? __( 'Connect', 'wp-simple-firewall' ) ),
			'items' => \array_values( $connectMeta[ 'sub_items' ] ?? [] ),
		];
	}

	private function connectMetaItem() :array {
		$links = new ExternalLinks();
		return [
			'slug'      => 'meta-connect',
			'title'     => __( 'Connect', 'wp-simple-firewall' ),
			'img'       => self::con()->svgs->iconClass( 'box-arrow-up-right' ),
			'sub_items' => [
				[
					'slug'   => 'connect-home',
					'title'  => __( 'Shield Home', 'wp-simple-firewall' ),
					'img'    => self::con()->svgs->iconClass( 'house-door' ),
					'href'   => $links->url( ExternalLinks::HOME ),
					'target' => '_blank',
				],
				[
					'slug'   => 'connect-facebook',
					'title'  => __( 'Facebook Group', 'wp-simple-firewall' ),
					'img'    => self::con()->svgs->iconClass( 'facebook' ),
					'href'   => $links->url( ExternalLinks::FACEBOOK_GROUP ),
					'target' => '_blank',
				],
				[
					'slug'   => 'connect-helpdesk',
					'title'  => __( 'Help Desk', 'wp-simple-firewall' ),
					'img'    => self::con()->svgs->iconClass( 'life-preserver' ),
					'href'   => $links->url( ExternalLinks::HELPDESK ),
					'target' => '_blank',
				],
				[
					'slug'   => 'connect-newsletter',
					'title'  => __( 'Newsletter', 'wp-simple-firewall' ),
					'img'    => self::con()->svgs->iconClass( 'envelope-paper' ),
					'href'   => $links->url( ExternalLinks::NEWSLETTER ),
					'target' => '_blank',
				],
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
					'title'  => sprintf( __( '%s Features', 'wp-simple-firewall' ), self::con()->labels->Name ),
					'target' => '_blank',
				],
			];
		}

		return [
			'slug'      => PluginNavs::NAV_LICENSE,
			'title'     => $con->isPremiumActive() ? self::con()->labels->Name : __( 'Go PRO!', 'wp-simple-firewall' ),
			'subtitle'  => __( 'Supercharged Security', 'wp-simple-firewall' ),
			'img'       => $con->svgs->iconClass( 'award' ),
			'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE, PluginNavs::SUBNAV_LICENSE_CHECK ),
			'sub_items' => $subItems,
			'active'    => $this->inav() === PluginNavs::NAV_LICENSE,
		];
	}

	/**
	 * @return array{has_items:bool,total_items:int,severity:string}
	 */
	private function getActionsQueueSummary() :array {
		try {
			$summary = $this->buildActionsQueueSummaryContract();
		}
		catch ( \Throwable $e ) {
			$summary = [
				'total_items'  => 0,
				'severity'     => 'good',
				'has_items'    => false,
			];
		}
		return [
			'has_items'   => (bool)$summary[ 'has_items' ],
			'total_items' => (int)$summary[ 'total_items' ],
			'severity'    => (string)$summary[ 'severity' ],
		];
	}

	/**
	 * @return array{has_items:bool,total_items:int,severity:string}
	 */
	protected function buildActionsQueueSummaryContract() :array {
		$summary = ( new BuildAttentionItems() )->build()[ 'summary' ];
		return [
			'has_items'   => !$summary[ 'is_all_clear' ],
			'total_items' => $summary[ 'total' ],
			'severity'    => $summary[ 'severity' ],
		];
	}

	private function buildToolItem( string $slug, string $title, string $icon, string $href, bool $active ) :array {
		return [
			'slug'   => $slug,
			'title'  => $title,
			'img'    => self::con()->svgs->iconClass( $icon ),
			'href'   => $href,
			'active' => $active,
		];
	}

	private function isCurrentRoute( string $nav, string $subnav = '' ) :bool {
		return $this->inav() === $nav && $this->subnav() === $subnav;
	}

	private function normalizeItems( array $items ) :array {
		$isSecAdmin = self::con()->isPluginAdmin();
		return \array_values( \array_map(
			fn( array $item ) :array => $this->normalizeItem( $item, $isSecAdmin ),
			$items
		) );
	}

	private function normalizeItem( array $item, bool $isSecAdmin ) :array {
		$item = Services::DataManipulation()->mergeArraysRecursive( [
			'slug'      => 'no-slug',
			'title'     => __( 'NO TITLE', 'wp-simple-firewall' ),
			'href'      => 'javascript:{}',
			'classes'   => [],
			'id'        => '',
			'active'    => false,
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
			$item[ 'sub_items' ] = \array_values( \array_map( function ( $sub ) use ( $isSecAdmin ) {
				$sub = Services::DataManipulation()->mergeArraysRecursive( [
					'slug'    => 'no-slug',
					'title'   => __( 'NO TITLE', 'wp-simple-firewall' ),
					'href'    => '#',
					'active'  => false,
					'classes' => [],
					'data'    => [],
					'target'  => '',
				], $sub );
				if ( $sub[ 'active' ] ) {
					$sub[ 'classes' ][] = 'active';
				}
				if ( !$isSecAdmin ) {
					$sub[ 'classes' ][] = 'disabled';
				}
				$sub[ 'classes' ] = \array_values( \array_unique( \array_filter( $sub[ 'classes' ] ) ) );
				return $sub;
			}, $item[ 'sub_items' ] ) );

			if ( !$item[ 'active' ] ) {
				$item[ 'active' ] = \count( \array_filter( $item[ 'sub_items' ], fn( array $sub ) :bool => (bool)( $sub[ 'active' ] ?? false ) ) ) > 0;
			}
		}

		if ( $item[ 'active' ] ) {
			$item[ 'classes' ][] = 'active';
		}
		if ( !$isSecAdmin ) {
			$item[ 'classes' ][] = 'disabled';
		}

		$item[ 'classes' ] = \array_values( \array_unique( \array_filter( $item[ 'classes' ] ) ) );
		return $item;
	}

	private function getIntroJsTourID() :string {
		return 'navigation_v1';
	}

	private function resolveCurrentMode() :string {
		$nav = $this->inav();
		if ( empty( $nav ) ) {
			return '';
		}

		$subNav = $this->subnav();
		if ( $nav === PluginNavs::NAV_DASHBOARD && $subNav === PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ) {
			return '';
		}

		return PluginNavs::modeForRoute( $nav, $subNav );
	}

	private function inav() :string {
		return (string)Services::Request()->query( Constants::NAV_ID );
	}

	private function subnav() :string {
		return (string)Services::Request()->query( Constants::NAV_SUB_ID );
	}
}
