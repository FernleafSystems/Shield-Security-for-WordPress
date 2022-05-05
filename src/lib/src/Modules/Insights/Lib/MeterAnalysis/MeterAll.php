<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterAll extends MeterBase {

	const SLUG = 'all';

	protected function title() :string {
		return __( 'All', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'All', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "This section reviews how your plugins & themes are scanned, where there are unused items, and any particular issues that need to be addressed.", 'wp-simple-firewall' ),
			__( "Generally you should keep all assets updated, remove unused items, and use only plugins that are regularly maintained.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		$allSlugs = ( new Components() )
			->setCon( $this->getCon() )
			->getAllComponentsSlugs();
		return array_diff( $allSlugs, [ 'all' ] );
	}
}