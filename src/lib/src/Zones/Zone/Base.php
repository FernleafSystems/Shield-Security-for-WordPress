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

	public function tooltip() :string {
		return '';
	}

	public function actions() :array {
		return [];
	}

	public function getAction_Config() :?array {
		$moduleZone = $this->getUnderlyingModuleZone();
		return empty( $moduleZone ) ? null : [
			'title'   => sprintf( __( "Configure All '%s' Options", 'wp-simple-firewall' ), $this->title() ),
			'data'    => \array_merge(
				[
					'zone_component_action' => ZoneComponentConfig::SLUG,
					'zone_component_slug'   => $moduleZone::Slug(),
				],
				empty( $this->tooltip() ) ? [] : [
					'bs-toggle'    => 'tooltip',
					'bs-trigger'   => 'hover',
					'bs-placement' => 'right',
					'bs-title'     => $this->tooltip(),
				]
			),
			'icon'    => self::con()->svgs->raw( 'gear' ),
			'classes' => [
				'list-group-item-primary',
				'zone_component_action',
			],
		];
	}

	/**
	 * @return ?string|ModuleBase
	 */
	protected function getUnderlyingModuleZone() :?string {
		return null;
	}
}