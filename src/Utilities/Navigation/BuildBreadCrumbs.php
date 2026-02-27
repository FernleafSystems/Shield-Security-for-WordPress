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

		$this->appendCrumbIfNotCurrentRoute(
			$crumbs,
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			$nav,
			$subNav,
			[
				'text'  => __( 'Shield Security', 'wp-simple-firewall' ),
				'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), __( 'Mode Selector', 'wp-simple-firewall' ) ),
				'href'  => $this->buildNavUrl( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ),
			]
		);

		if ( $nav !== PluginNavs::NAV_RESTRICTED ) {
			$mode = PluginNavs::modeForRoute( $nav, $subNav );
			if ( !empty( $mode ) ) {
				$entry = PluginNavs::defaultEntryForMode( $mode );
				$this->appendCrumbIfNotCurrentRoute(
					$crumbs,
					$entry[ 'nav' ],
					$entry[ 'subnav' ],
					$nav,
					$subNav,
					[
						'text'  => PluginNavs::modeLabel( $mode ),
						'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), PluginNavs::modeLabel( $mode ) ),
						'href'  => $this->buildNavUrl( $entry[ 'nav' ], $entry[ 'subnav' ] ),
					]
				);
			}
		}

		if ( !( $nav === PluginNavs::NAV_DASHBOARD && $subNav === PluginNavs::SUBNAV_DASHBOARD_OVERVIEW )
			 && !$this->isModeLandingRoute( $nav, $subNav ) ) {
			$crumbText = $navStruct[ 'name' ];
			$crumbHrefSubNav = $this->getDefaultSubNavForNav( $nav, $hierarchy );
			$crumbTitleLabel = sprintf( __( '%s Home', 'wp-simple-firewall' ), $navStruct[ 'name' ] );

			$subNavDefinition = PluginNavs::breadcrumbSubNavDefinition( $nav, $subNav );
			$subNavLabel = $subNavDefinition[ 'label' ] ?? null;
			if ( \is_string( $subNavLabel ) && $subNavLabel !== '' ) {
				$crumbText = $subNavLabel;
				$crumbHrefSubNav = $subNav;
				$crumbTitleLabel = $subNavLabel;
			}

			$this->appendCrumbIfNotCurrentRoute(
				$crumbs,
				$nav,
				$crumbHrefSubNav,
				$nav,
				$subNav,
				[
					'text'  => $crumbText,
					'title' => sprintf( '%s: %s', __( 'Navigation', 'wp-simple-firewall' ), $crumbTitleLabel ),
					'href'  => $this->buildNavUrl( $nav, $crumbHrefSubNav ),
				]
			);
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

	private function isModeLandingRoute( string $nav, string $subNav ) :bool {
		return PluginNavs::isModeLandingRoute( $nav, $subNav );
	}

	private function appendCrumbIfNotCurrentRoute( array &$crumbs, string $candidateNav, string $candidateSubNav, string $currentNav, string $currentSubNav, array $crumb ) :void {
		if ( !$this->isSameRoute( $candidateNav, $candidateSubNav, $currentNav, $currentSubNav ) ) {
			$crumbs[] = $crumb;
		}
	}

	private function isSameRoute( string $candidateNav, string $candidateSubNav, string $currentNav, string $currentSubNav ) :bool {
		return $candidateNav === $currentNav && $candidateSubNav === $currentSubNav;
	}
}
