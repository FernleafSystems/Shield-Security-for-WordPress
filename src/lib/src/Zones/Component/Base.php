<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	protected function run() {
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( 'No Description Yet', 'wp-simple-firewall' )
		];
	}

	abstract public function enabledStatus() :string;

	protected function hasCapability() :bool {
		return true;
	}

	public function getActions() :array {
		return [
			'config' => [
				'title'   => __( 'Configure Options', 'wp-simple-firewall' ),
				'data'    => [
					'zone_component_action' => ZoneComponentConfig::SLUG,
					'zone_component_slug'   => static::Slug(),
				],
				'icon'    => self::con()->svgs->raw( 'sliders' ),
				'classes' => [
					'btn-outline-secondary',
					'zone_component_action',
				],
			]
		];
	}

	public function getOptions() :array {
		return \array_keys( \array_filter( self::con()->cfg->configuration->options, function ( array $option ) {
			return \in_array( static::Slug(), $option[ 'zone_comp_slugs' ] ?? [] );
		} ) );
	}

	public function getLinks() :array {
		return [];
	}
}