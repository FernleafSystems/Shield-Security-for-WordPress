<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;

class SiteActionUpdate extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_update';

	protected function getMainwpActionFailureMessage() :string {
		return sprintf( __( "%s plugin couldn't be updated", 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getMainwpActionSuccessMessage() :string {
		return sprintf( __( '%s plugin updated', 'wp-simple-firewall' ), self::con()->labels->Name );
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
