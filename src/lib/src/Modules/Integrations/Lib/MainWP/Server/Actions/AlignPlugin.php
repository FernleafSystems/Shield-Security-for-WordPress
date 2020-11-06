<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Common\Consumers\MWPSiteConsumer,
	Server\Data\ClientPluginStatus};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Api;
use MainWP\Dashboard\MainWP_Connect;
use MainWP\Dashboard\MainWP_Sync;

class AlignPlugin {

	use ModConsumer;
	use MWPSiteConsumer;

	public function run() {
		$oStatus = ( new ClientPluginStatus() )
			->setMod( $this->getMod() )
			->setMwpSite( $this->getMwpSite() );

		switch ( $oStatus->status() ) {

			case ClientPluginStatus::INACTIVE:
				if ( $this->activate() ) {
					$this->sync();
				}
				break;

			case ClientPluginStatus::NEED_SYNC:
				$this->sync();
				break;

			case ClientPluginStatus::VERSION_OLDER_THAN_SERVER:
				$this->sync();
				$this->upgrade();
				$this->sync();
				break;

			case ClientPluginStatus::NOT_INSTALLED:
				$this->install();
				$this->sync();
				break;

			case ClientPluginStatus::ACTIVE:
			default:
				// nothing
				break;
		}
	}

	public function activate() :bool {
		$siteObj = $this->getMwpSite()->siteobj;
		$info = MainWP_Connect::fetch_url_authed(
			$siteObj,
			'plugin_action',
			[
				'action' => 'activate',
				'plugin' => ( new ClientPluginStatus() )
								->setMod( $this->getMod() )
								->setMwpSite( $this->getMwpSite() )
								->getInstalledPlugin()[ 'slug' ],
			]
		);

		$status = $info[ 'status' ] ?? false;
		return $status === 'SUCCESS';
	}

	public function install() :bool {
		$siteObj = [ $this->getMwpSite()->siteobj ];
		$urlInstall = ( new Api() )
			->setWorkingSlug( 'wp-simple-firewall' )
			->getInfo()->download_link;

		$info = MainWP_Connect::fetch_urls_authed(
			$siteObj,
			'installplugintheme',
			[
				'type'           => 'plugin',
				'url'            => wp_json_encode( $urlInstall ),
				'activatePlugin' => 'yes',
				'overwrite'      => true,
			],
			null,
			$o
		);
		return (bool)$info;
	}

	public function sync() :bool {
		return (bool)MainWP_Sync::sync_site( $this->getMwpSite()->siteobj );
	}

	public function upgrade() :bool {
		$siteObj = $this->getMwpSite()->siteobj;
		MainWP_Connect::fetch_url_authed(
			$siteObj,
			'upgradeplugintheme',
			[
				'type' => 'plugin',
				'list' => ( new ClientPluginStatus() )
							  ->setMod( $this->getMod() )
							  ->setMwpSite( $this->getMwpSite() )
							  ->getInstalledPlugin()[ 'slug' ],
			],
			true
		);
		return true;
	}
}
