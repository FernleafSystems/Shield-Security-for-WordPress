<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionActivate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_activate';

	protected function getMainwpActionFailureMessage() :string {
		return sprintf( __( "%s plugin couldn't be activated", 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getMainwpActionSuccessMessage() :string {
		return sprintf( __( '%s plugin activated', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getMainwpActionParams() :array {
		return [
			'action' => 'activate',
			'plugin' => ( new ClientPluginStatus() )
							->setMwpSite( $this->getMwpSite() )
							->getInstalledPlugin()[ 'slug' ],
		];
	}
}
