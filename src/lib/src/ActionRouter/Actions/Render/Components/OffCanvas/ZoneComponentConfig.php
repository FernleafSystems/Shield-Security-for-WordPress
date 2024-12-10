<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class ZoneComponentConfig extends OffCanvasBase {

	public const SLUG = 'offcanvas_zone_component_config';

	protected function buildCanvasTitle() :string {
		return sprintf( '%s: %s',
			__( 'Configure Component', 'wp-simple-firewall' ),
			\implode( ', ',
				\array_map(
					fn( string $slug ) => self::con()->comps->zones->getZoneComponent( $slug )->title(),
					$this->getZoneComponentSlugs()
				)
			)
		);
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( OptionsFormFor::class, [
			'options'      => ( new GetOptionsForZoneComponents() )->run( $this->getZoneComponentSlugs() ),
			'focus_option' => $this->action_data[ 'config_item' ] ?? '',
		] );
	}

	protected function getZoneComponentSlugs() :array {
		return \explode( ',', $this->action_data[ 'zone_component_slug' ] );
	}
}