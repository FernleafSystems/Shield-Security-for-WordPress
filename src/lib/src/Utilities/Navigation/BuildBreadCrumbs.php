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
		$urls = self::con()->plugin_urls;
		$crumbs = [];
		if ( empty( $nav ) ) {
			throw new \Exception( 'No nav provided.' );
		}
		if ( empty( $subNav ) ) {
			$subNav = PluginNavs::SUBNAV_INDEX;
		}

		$hierarchy = PluginNavs::GetNavHierarchy();
		$navStruct = $hierarchy[ $nav ] ?? null;
		if ( empty( $navStruct ) ) {
			throw new \Exception( 'Not a valid nav: '.$nav );
		}
		if ( empty( $navStruct[ 'sub_navs' ][ $subNav ] ) ) {
			throw new \Exception( 'Not a valid sub_nav: '.$subNav );
		}

		$crumbs[] = [
			'text'  => $navStruct[ 'name' ],
			'title' => sprintf( '%s: %s', __( 'Navigation' ), sprintf( __( '%s Home', 'wp-simple-firewall' ), $navStruct[ 'name' ] ) ),
			'href'  => $urls->adminTopNav( $nav, PluginNavs::GetDefaultSubNavForNav( $nav ) ),
		];

		foreach ( $navStruct[ 'parents' ] as $parentNav ) {
			if ( $parentNav !== $nav ) {
				$name = $hierarchy[ $parentNav ][ 'name' ];
				\array_unshift( $crumbs, [
					'text'  => $name,
					'title' => sprintf( '%s: %s', __( 'Navigation' ), sprintf( __( '%s Home', 'wp-simple-firewall' ), $name ) ),
					'href'  => $urls->adminTopNav( $parentNav, \key( $hierarchy[ $parentNav ][ 'sub_navs' ] ) ),
				] );
			}
		}

		return $crumbs;
	}
}