<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

class SecurityZone extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_security_zone';
	public const TEMPLATE = '/components/zones/security_zone_%s.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$zonesCon = $con->comps->zones;
		$zone = $zonesCon->getZone( $this->action_data[ 'zone_slug' ] );
		return [
			'flags'   => [
			],
			'imgs'    => [
				'svgs' => [
					'title'   => $con->svgs->raw( $zone->icon() ),
					'heading' => $con->svgs->rawImage( sprintf( 'zones/zone_%s.svg', $zone::Slug() ) ),
				],
			],
			'strings' => [
				'title'       => $zone->title(),
				'description' => $zone->description(),
				'actions' => __( 'Actions', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'components' => \array_map(
					function ( $zoneComponent ) {
						return self::con()->action_router->render( SecurityZoneComponent::class, [
							'zone_component' => $zoneComponent::Slug(),
						] );
					},
					$zonesCon->getComponentsForZone( $zone )
				),
				'actions' => $zone->actions(),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_slug',
		];
	}

	protected function getRenderTemplate() :string {
		$rend = self::con()->comps->render;
		return sprintf( static::TEMPLATE, $rend->templateExists( sprintf( static::TEMPLATE, $this->getZone()::Slug() ) ) ? $this->getZone()::Slug() : 'base' );
	}

	private function getZone() {
		return self::con()->comps->zones->getZone( $this->action_data[ 'zone_slug' ] );
	}
}