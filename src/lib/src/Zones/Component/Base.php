<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	protected function run() {
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return '';
	}

	public function explanation() :array {
		return $this->status()[ 'exp' ];
	}

	public function enabledStatus() :string {
		return $this->status()[ 'level' ];
	}

	protected function hasCapability() :bool {
		return true;
	}

	public function getActions() :array {
		$actions = [];
		if ( $this->hasConfigAction() ) {
			$actions[ 'config' ] = [
				'title'   => __( 'Edit Settings', 'wp-simple-firewall' ),
				'data'    => \array_merge(
					[
						'zone_component_action' => ZoneComponentConfig::SLUG,
						'zone_component_slug'   => static::Slug(),
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
					'zone_component_action',
				],
			];
		}
		return $actions;
	}

	public function getOptions() :array {
		return \array_keys( \array_filter( self::con()->cfg->configuration->options, function ( array $option ) {
			return \in_array( static::Slug(), $option[ 'zone_comp_slugs' ] ?? [] );
		} ) );
	}

	public function getLinks() :array {
		return [];
	}

	protected function hasConfigAction() :bool {
		return true;
	}

	/**
	 * @return array{level:string,expl:string[]}
	 */
	protected function status() :array {
		return [
			'level' => EnumEnabledStatus::NEUTRAL,
			'exp'   => [],
		];
	}
}