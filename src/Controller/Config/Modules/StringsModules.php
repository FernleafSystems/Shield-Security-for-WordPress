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
			'name'        => __( $cfg->properties[ 'name' ], 'wp-simple-firewall' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Module names come from plugin.json, so they can't be literals here. The generated translations-strings-auto.php is how they reach the translation template.
			'subtitle'    => __( $cfg->properties[ 'tagline' ] ?? '', 'wp-simple-firewall' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Module taglines come from plugin.json, so they can't be literals here. The generated translations-strings-auto.php is how they reach the translation template.
			'description' => [],
		];
	}
}