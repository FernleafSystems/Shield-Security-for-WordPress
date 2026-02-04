<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules\ModulePlugin;

class GloballyDisabled extends Base {

	public function check() :?array {
		return self::con()->comps->opts_lookup->isPluginEnabled() ? null :
			[
				'id'        => 'plugin_globally_disabled',
				'type'      => 'warning',
				'text'      => [
					sprintf(
						'%s %s',
						__( "All security protection offered by Shield is completely disabled.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="">%s</a>',
							self::con()->plugin_urls->cfgForZoneComponent( ModulePlugin::Slug() ),
							__( 'Go To Option', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
	}
}