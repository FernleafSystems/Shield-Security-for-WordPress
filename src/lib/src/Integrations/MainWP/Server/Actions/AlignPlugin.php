<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\Consumers\MWPSiteConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use MainWP\Dashboard\MainWP_Connect;
use MainWP\Dashboard\MainWP_Sync;

class AlignPlugin {

	use PluginControllerConsumer;
	use MWPSiteConsumer;

	public function run() {
		$site = $this->getMwpSite();

		$oStatus = ( new Server\Data\PluginStatus() )
			->setCon( $this->getCon() )
			->setMwpSite( $site );

		switch ( $oStatus->status() ) {

			case Server\Data\PluginStatus::INACTIVE:
				if ( $this->activate() ) {
					$this->sync();
				}
				break;

			case Server\Data\PluginStatus::NEED_SYNC:
				$this->sync();
				break;

			case Server\Data\PluginStatus::VERSION_OLDER_THAN_SERVER:
				$this->sync();
				$this->update();
				$this->sync();
				break;

			case Server\Data\PluginStatus::ACTIVE:
			default:
				// nothing
				break;
		}
	}

	public function update() :bool {
		$siteObj = $this->getMwpSite()->siteobj;
		$info = MainWP_Connect::fetch_url_authed(
			$siteObj,
			'upgradeplugintheme',
			[
				'type' => 'plugin',
				'list' => ( new Server\Data\PluginStatus() )
							  ->setCon( $this->getCon() )
							  ->setMwpSite( $this->getMwpSite() )
							  ->getInstalledPlugin()[ 'slug' ],
			],
			true
		);
		error_log( var_export( $info, true ) );
		return true;
	}

	public function sync() :bool {
		return (bool)MainWP_Sync::sync_site( $this->getMwpSite()->siteobj );
	}

	public function activate() :bool {
		$siteObj = $this->getMwpSite()->siteobj;
		$info = MainWP_Connect::fetch_url_authed(
			$siteObj,
			'plugin_action',
			[
				'action' => 'activate',
				'plugin' => ( new Server\Data\PluginStatus() )
								->setCon( $this->getCon() )
								->setMwpSite( $this->getMwpSite() )
								->getInstalledPlugin()[ 'slug' ],
			]
		);

		return $info[ 'status' ] ?? false === 'SUCCESS';
	}
}
