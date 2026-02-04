<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class StringsModules {

	use PluginControllerConsumer;

	/**
	 * @return array{name:string, subtitle:string, description:string[]}
	 */
	public function getFor( string $modSlug ) :array {
		$cfg = self::con()->modCfg( $modSlug );
		return [
			'name'        => __( $cfg->properties[ 'name' ], 'wp-simple-firewall' ),
			'subtitle'    => __( $cfg->properties[ 'tagline' ] ?? '', 'wp-simple-firewall' ),
			'description' => [],
		];
	}
}