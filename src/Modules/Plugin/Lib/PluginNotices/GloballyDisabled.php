<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ProtectionEnableDialogBuilder;

class GloballyDisabled extends Base {

	public function check() :?array {
		if ( self::con()->comps->opts_lookup->isPluginEnabled() ) {
			return null;
		}
		$dialog = new ProtectionEnableDialogBuilder();
		return [
			'id'        => 'plugin_globally_disabled',
			'type'      => 'warning',
			'text'      => [
				sprintf(
					'%s %s',
					__( "All security protection offered by Shield is completely disabled.", 'wp-simple-firewall' ),
					sprintf( '<button type="button" class="shield-button-link fw-semibold text-decoration-underline" data-enable-protection="%s" aria-haspopup="dialog">%s</button>',
						esc_attr( $dialog->forPlugin() ),
						esc_html( $dialog->pluginTitle() )
					)
				)
			],
			'locations' => [
				'shield_admin_top_page',
			]
		];
	}
}
