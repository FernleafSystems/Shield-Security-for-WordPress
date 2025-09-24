<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionDeactivate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_deactivate';

	protected function getMainwpActionFailureMessage() :string {
		return __( "Shield plugin couldn't be deactivated", 'wp-simple-firewall' );
	}

	protected function getMainwpActionSuccessMessage() :string {
		return __( 'Shield plugin deactivated', 'wp-simple-firewall' );
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
