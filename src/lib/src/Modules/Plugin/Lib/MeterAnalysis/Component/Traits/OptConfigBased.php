<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;

trait OptConfigBased {

	abstract protected function getOptConfigKey() :string;

	protected function cfgItem() :string {
		return $this->getOptConfigKey();
	}

	protected function hrefData() :array {
		$def = self::con()->opts->optDef( $this->getOptConfigKey() );
		if ( empty( $def ) || empty( $def[ 'zone_comp_slugs' ] ) ) {
			$def = self::con()->opts->optDef( 'visitor_address_source' );
		}
		return [
			'zone_component_action' => ZoneComponentConfig::SLUG,
			'zone_component_slug'   => \current( $def[ 'zone_comp_slugs' ] ),
		];
	}

	protected function getOptLink( string $for ) :string {
		return self::con()->plugin_urls->cfgForOpt( $for );
	}

	protected function hrefFull() :string {
		return $this->getOptLink( $this->cfgItem() );
	}

	protected function isOptConfigBased() :bool {
		return true;
	}
}