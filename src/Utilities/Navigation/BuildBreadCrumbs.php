<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BuildBreadCrumbs {

	use PluginControllerConsumer;

	public function current() :array {
		return $this->for( PluginNavs::GetNav(), PluginNavs::GetSubNav() );
	}

	public function for( string $nav, string $subnav ) :array {
		try {
			$crumbs = $this->parse( $nav, $subnav );
		}
		catch ( \Exception $e ) {
			$crumbs = [];
		}
		return $crumbs;
	}

	/**
	 * @throws \Exception
	 */
	public function parse( string $nav, string $subNav ) :array {
		$crumbs = [];
		if ( empty( $nav ) ) {
			throw new \Exception( 'No nav provided.' );
		}
		if ( empty( $subNav ) ) {
			$subNav = PluginNavs::SUBNAV_INDEX;
		}

		$hierarchy = $this->getNavHierarchy();
		$navStruct = $hierarchy[ $nav ] ?? null;
		if ( empty( $navStruct ) ) {
			throw new \Exception( 'Not a valid nav: '.$nav );
		}
		if ( empty( $navStruct[ 'sub_navs' ][ $subNav ] ) ) {
			throw new \Exception( 'Not a valid sub_nav: '.$subNav );
		}

		$crumbs[] = [
			'text'  => __( 'Shield Security', 'wp-simple-firewall' ),
			'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), __( 'Mode Selector', 'wp-simple-firewall' ) ),
			'href'  => $this->buildNavUrl( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ),
		];

		if ( $nav !== PluginNavs::NAV_RESTRICTED ) {
			$mode = PluginNavs::modeForNav( $nav );
			if ( !empty( $mode ) ) {
				$entry = PluginNavs::defaultEntryForMode( $mode );
				$crumbs[] = [
					'text'  => PluginNavs::modeLabel( $mode ),
					'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), PluginNavs::modeLabel( $mode ) ),
					'href'  => $this->buildNavUrl( $entry[ 'nav' ], $entry[ 'subnav' ] ),
				];
			}
		}

		if ( !( $nav === PluginNavs::NAV_DASHBOARD && $subNav === PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ) ) {
			$crumbs[] = [
				'text'  => $navStruct[ 'name' ],
				'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), sprintf( __( '%s Home', 'wp-simple-firewall' ), $navStruct[ 'name' ] ) ),
				'href'  => $this->buildNavUrl( $nav, $this->getDefaultSubNavForNav( $nav, $hierarchy ) ),
			];
		}

		return $crumbs;
	}

	protected function getNavHierarchy() :array {
		return PluginNavs::GetNavHierarchy();
	}

	protected function buildNavUrl( string $nav, string $subNav ) :string {
		return self::con()->plugin_urls->adminTopNav( $nav, $subNav );
	}

	protected function getDefaultSubNavForNav( string $nav, array $hierarchy ) :string {
		return PluginNavs::GetDefaultSubNavForNav( $nav );
	}
}
