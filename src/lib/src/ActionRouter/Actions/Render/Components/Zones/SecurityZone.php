<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\GlobalPluginEnable;

class SecurityZone extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_security_zone';
	public const TEMPLATE = '/components/zones/security_zone_%s.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$zonesCon = $con->comps->zones;
		$zone = $zonesCon->getZone( $this->action_data[ 'zone_slug' ] );
		return [
			'flags'   => [
				'global_plugin_disabled' => !$con->comps->opts_lookup->isPluginEnabled(),
			],
			'hrefs'   => [
				// not currently used
				//				'all_zones' => \array_filter( \array_map(
				//					function ( $zone ) {
				//						return [
				//							'title'   => $zone->title(),
				//							'classes' => [
				//								'dynamic_body_load',
				//								'body_content_link'
				//							],
				//							'data'    => [
				//								'dynamic_page_load' => \wp_json_encode( [
				//									'dynamic_load_slug' => Zone::SLUG,
				//									'dynamic_load_data' => [
				//										'zone_slug' => $zone::Slug(),
				//									],
				//								] ),
				//							],
				//						];
				//					},
				//					$zonesCon->getZones()
				//				) ),
			],
			'imgs'    => [
				'svgs' => [
					'title'   => $con->svgs->raw( $zone->icon() ),
					'heading' => $con->svgs->rawImage( sprintf( 'zones/zone_%s.svg', $zone::Slug() ) ),
				],
			],
			'strings' => [
				'global_plugin_disabled' => sprintf( __( "%s's protections are currently disabled so any changes made here won't apply until it is re-enabled.", 'wp-simple-firewall' ), $con->labels->Name ),
				'reenable_plugin'        => __( 'Re-enable Protection.', 'wp-simple-firewall' ),
				'title'                  => $zone->title(),
				'description'            => $zone->description(),
				'actions'                => __( 'Zone Actions', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'href_global_plugin_enable' => $zonesCon->getZoneComponent( GlobalPluginEnable::Slug() )
														->getActions()[ 'config' ],
				'components'                => \array_map(
					function ( $zoneComponent ) {
						return self::con()->action_router->render( SecurityZoneComponent::class, [
							'zone_component' => $zoneComponent::Slug(),
						] );
					},
					$zonesCon->getComponentsForZone( $zone )
				),
				'actions'                   => $zone->actions(),
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