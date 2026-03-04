<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

trait InvestigateAssetOptionsBuilder {

	protected function buildAssetOptions( array $assets, string $valueField ) :array {
		return ( new InvestigateAssetLookupOptionsBuilder() )->build( $assets, $valueField );
	}
}
