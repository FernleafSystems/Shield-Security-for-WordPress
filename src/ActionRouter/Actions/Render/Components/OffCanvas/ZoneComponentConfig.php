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
			'options'      => ( new GetOptionsForZoneComponents() )->run( $this->getZoneComponentSlugs(), $this->getOptionKeys() ),
			'config_item'  => $this->action_data[ 'config_item' ] ?? '',
			'form_context' => (string)( $this->action_data[ 'form_context' ] ?? 'offcanvas' ),
		] );
	}

	protected function getZoneComponentSlugs() :array {
		return \explode( ',', $this->action_data[ 'zone_component_slug' ] );
	}

	protected function getOptionKeys() :array {
		$keys = \array_filter( \array_map(
			static fn( string $key ) :string => \trim( $key ),
			\explode( ',', (string)( $this->action_data[ 'option_keys' ] ?? '' ) )
		) );
		return \array_values( $keys );
	}
}
