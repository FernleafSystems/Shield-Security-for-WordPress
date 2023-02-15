<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseLookup;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\SiteCustomAction;
use FernleafSystems\Wordpress\Services\Services;

class TabSitesListing extends BaseSubPage {

	public const SLUG = 'mainwp_page_sites_listing';
	public const TEMPLATE = '/integration/mainwp/pages/sites.twig';
	public const TAB = 'sites';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$mwp = $con->mwpVO;
		$WP = Services::WpGeneral();
		$req = Services::Request();

		$statsHead = [
			'connected'    => 0,
			'disconnected' => 0,
			'with_issues'  => 0,
			'needs_update' => 0,
		];

		$sites = array_filter( array_map(
			function ( $site ) use ( $statsHead ) {
				try {
					return $this->buildEntireSiteData( $site );
				}
				catch ( \Exception $e ) {
					return null;
				}
			},
			apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key )
		) );

		foreach ( $sites as $site ) {
			$shieldData = $site[ 'shield' ];
			if ( $shieldData[ 'is_active' ] ) {
				$statsHead[ 'connected' ]++;
			}
			else {
				$statsHead[ 'disconnected' ]++;
			}
			if ( $shieldData[ 'has_update' ] ) {
				$statsHead[ 'needs_update' ]++;
			}
			if ( $shieldData[ 'has_issues' ] ) {
				$statsHead[ 'with_issues' ]++;
			}
		}

		return [
			'vars' => [
				'sites'        => $sites,
				'stats_head'   => $statsHead,
			],
		];
	}
}