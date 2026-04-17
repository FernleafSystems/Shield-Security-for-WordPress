<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type AllClearChip array{
 *   slug:string,
 *   label:string,
 *   icon_class:string,
 *   severity:string
 * }
 * @phpstan-type AllClearData array{
 *   title:string,
 *   subtitle:string,
 *   icon_class:string,
 *   zone_chips:list<AllClearChip>
 * }
 */
class ActionsQueueAllClearDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @param array<string,array{slug?:string,label:string}> $zonesIndexed
	 * @return AllClearData
	 */
	public function build( array $zonesIndexed ) :array {
		$chipIconClass = self::con()->svgs->iconClass( 'check-circle-fill' );

		return [
			'title'      => __( 'All security zones are clear', 'wp-simple-firewall' ),
			'subtitle'   => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
			'icon_class' => self::con()->svgs->iconClass( 'shield-check' ),
			'zone_chips' => \array_values( \array_map(
				static fn( string $slug, array $zone ) :array => [
					'slug'       => $zone[ 'slug' ] ?? $slug,
					'label'      => $zone[ 'label' ],
					'icon_class' => $chipIconClass,
					'severity'   => 'good',
				],
				\array_keys( $zonesIndexed ),
				\array_values( $zonesIndexed )
			) ),
		];
	}
}
