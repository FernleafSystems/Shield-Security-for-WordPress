<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use MainWP\Dashboard\MainWP_Sync;

class SiteActionSync extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_site_action_site_sync';

	protected function fireClientSiteAction() {
		return MainWP_Sync::sync_site( $this->getMwpSite()->siteobj );
	}

	protected function checkResponse() :bool {
		return (bool)$this->clientActionResponse;
	}

	protected function getMainwpActionFailureMessage() :string {
		return "Site failed to sync";
	}

	protected function getMainwpActionSuccessMessage() :string {
		return 'Site synced successfully';
	}
}