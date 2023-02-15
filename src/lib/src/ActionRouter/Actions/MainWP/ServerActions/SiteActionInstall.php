<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Api;

class SiteActionInstall extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_install';

	protected function getMainwpActionFailureMessage() :string {
		return "Shield plugin couldn't be installed";
	}

	protected function getMainwpActionSuccessMessage() :string {
		return 'Shield plugin installed';
	}

	protected function checkResponse() :bool {
		return ( $this->clientActionResponse[ 'installation' ] ?? false ) === 'SUCCESS';
	}

	protected function getMainwpActionSlug() :string {
		return 'installplugintheme';
	}

	protected function getMainwpActionParams() :array {
		return [
			'type'           => 'plugin',
			'url'            => wp_json_encode(
				( new Api() )
					->setWorkingSlug( 'wp-simple-firewall' )
					->getInfo()->download_link
			),
			'activatePlugin' => 'yes',
			'overwrite'      => true,
		];
	}
}