<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\PluginThemesBase;

class InvestigateAssetDataAdapter extends PluginThemesBase {

	public function buildPluginDataForInvestigate( $plugin ) :array {
		return $this->buildPluginData( $plugin );
	}

	public function buildThemeDataForInvestigate( $theme ) :array {
		return $this->buildThemeData( $theme );
	}
}
