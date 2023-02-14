<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionUpdate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_update';

	protected function getMainwpActionFailureMessage() :string {
		return "Shield plugin couldn't be updated";
	}

	protected function getMainwpActionSuccessMessage() :string {
		return 'Shield plugin updated';
	}

	protected function checkResponse() :bool {
		return true; // TODO
	}

	protected function getMainwpActionSlug() :string {
		return 'upgradeplugintheme';
	}

	protected function getMainwpActionParams() :array {
		return [
			'type' => 'plugin',
			'list' => ( new ClientPluginStatus() )
						  ->setMwpSite( $this->getMwpSite() )
						  ->getInstalledPlugin()[ 'slug' ],
		];
	}
}