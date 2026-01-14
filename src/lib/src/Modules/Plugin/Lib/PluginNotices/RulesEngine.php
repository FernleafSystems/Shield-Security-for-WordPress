<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

class RulesEngine extends Base {

	public function check() :?array {
		return ( !self::con()->rules->isRulesEngineReady() || !self::con()->rules->processComplete ) ?
			[
				'id'        => 'rules_engine_not_running',
				'type'      => 'warning',
				'text'      => [
					sprintf( __( "%s's Rules Engine isn't running.", 'wp-simple-firewall' ), self::con()->labels->Name )
					.' '.__( "If this message still appears after refreshing this page, please reinstall the plugin.", 'wp-simple-firewall' ),
				],
				'locations' => [
					'shield_admin_top_page',
				]
			]
			: null;
	}
}