<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionActivate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_activate';

	protected function getMainwpActionFailureMessage() :string {
		return "Shield plugin couldn't be activated";
	}

	protected function getMainwpActionSuccessMessage() :string {
		return 'Shield plugin activated';
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