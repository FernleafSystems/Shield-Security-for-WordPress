<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class GloballyDisabled extends Base {

	public function check() :?array {
		/** @var Options $pluginOpts */
		$pluginOpts = self::con()->getModule_Plugin()->opts();
		return $pluginOpts->isPluginGloballyDisabled() ?
			[
				'id'        => 'plugin_globally_disabled',
				'type'      => 'warning',
				'text'      => [
					sprintf(
						'%s %s',
						__( "All security protection offered by Shield is completely disabled.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="">%s</a>',
							self::con()->plugin_urls->modCfgOption( 'global_enable_plugin_features' ),
							__( 'Go To Option', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
				]
			]
			: null;
	}
}