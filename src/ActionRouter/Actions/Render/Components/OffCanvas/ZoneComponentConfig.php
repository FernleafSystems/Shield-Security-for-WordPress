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
			'options'         => ( new GetOptionsForZoneComponents() )->run( $this->getZoneComponentSlugs(), $this->getOptionKeys() ),
			'form_context'    => $this->getFormContext(),
			// The Configure topic whose panel lays these settings out.
			'configure_panel' => $this->fields()->string( 'configure_panel', '' ),
		] );
	}

	protected function getZoneComponentSlugs() :array {
		return \explode( ',', $this->fields()->string( 'zone_component_slug', '' ) );
	}

	protected function getOptionKeys() :array {
		$keys = \array_filter( \array_map(
			static fn( string $key ) :string => \trim( $key ),
			\explode( ',', $this->fields()->string( 'option_keys', '' ) )
		) );
		return \array_values( $keys );
	}

	private function getFormContext() :string {
		return $this->fields()->string( 'form_context', 'offcanvas' );
	}
}
