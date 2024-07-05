<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules\ModuleBase;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	public function components() :array {
		return [];
	}

	public function description() :array {
		return [];
	}

	public function icon() :string {
		return 'grid-1x2-fill';
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}

	public function actions() :array {
		$actions = [];
		$moduleZone = $this->getUnderlyingModuleZone();
		if ( !empty( $moduleZone ) ) {
			$actions[ 'config' ] = [
				'title'   => __( 'Configure All Options', 'wp-simple-firewall' ),
				'data'    => [
					'zone_component_action' => ZoneComponentConfig::SLUG,
					'zone_component_slug'   => $moduleZone::Slug(),
				],
				'icon'    => self::con()->svgs->raw( 'sliders' ),
				'classes' => [
					'btn-outline-secondary',
					'zone_component_action',
				],
			];
		}
		return $actions;
	}

	/**
	 * @return ?string|ModuleBase
	 */
	protected function getUnderlyingModuleZone() :?string {
		return null;
	}
}