<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionDeactivate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_deactivate';

	protected function getMainwpActionFailureMessage() :string {
		return sprintf( __( "%s plugin couldn't be deactivated", 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getMainwpActionSuccessMessage() :string {
		return sprintf( __( '%s plugin deactivated', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getMainwpActionParams() :array {
		return [
			'action' => 'deactivate',
			'plugin' => ( new ClientPluginStatus() )
							->setMwpSite( $this->getMwpSite() )
							->getInstalledPlugin()[ 'slug' ],
		];
	}
}
