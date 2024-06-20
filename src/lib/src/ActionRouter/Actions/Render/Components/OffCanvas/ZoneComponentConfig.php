<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;

class ZoneComponentConfig extends OffCanvasBase {

	public const SLUG = 'offcanvas_zone_component_config';

	protected function buildCanvasTitle() :string {
		$components = \array_map(
			function ( string $slug ) {
				return self::con()->comps->zones->getZoneComponent( $slug )->title();
			},
			$this->getZoneComponentSlugs()
		);
		return sprintf( '%s: %s',
			__( 'Configure Component', 'wp-simple-firewall' ),
			\implode( ', ', $components )
		);
	}

	protected function buildCanvasBody() :string {
		$options = [];
		foreach ( $this->getZoneComponentSlugs() as $zoneComponentSlug ) {
			$options = \array_merge(
				$options,
				self::con()->comps->zones->getZoneComponent( $zoneComponentSlug )->getOptions()
			);
		}
		return self::con()->action_router->render( OptionsFormFor::class, [
			'options' => $options,
		] );
	}

	protected function getZoneComponentSlugs() :array {
		return \explode( ',', $this->action_data[ 'zone_component_slug' ] );
	}
}