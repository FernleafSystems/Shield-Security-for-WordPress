<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class ReportsSettingsActionDataBuilder {

	/**
	 * @param list<string> $zoneComponentSlugs
	 * @return array{options:list<string>}
	 */
	public function build( array $zoneComponentSlugs ) :array {
		return [
			'options' => ( new GetOptionsForZoneComponents() )->run( $zoneComponentSlugs ),
		];
	}
}
