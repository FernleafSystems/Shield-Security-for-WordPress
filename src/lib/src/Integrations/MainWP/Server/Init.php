<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\ExtensionSettingsPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Init {

	use PluginControllerConsumer;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function run() :string {
		add_filter( 'mainwp_getextensions', function ( $exts ) {
			$exts[] = [
				'plugin'   => $this->getCon()->getRootFile(),
				'callback' => function () {
					( new ExtensionSettingsPage() )
						->setCon( $this->getCon() )
						->render();
				}
			];
			return $exts;
		}, 10, 1 );
		$childEnabled = apply_filters( 'mainwp_extension_enabled_check', $this->getCon()->getRootFile() );
		$key = $childEnabled[ 'key' ] ?? '';
		if ( empty( $key ) ) {
			throw new \Exception( 'No child key provided' );
		}

		( new SyncHandler() )
			->setCon( $this->getCon() )
			->execute();
		( new UI\SitesListTableHandler() )
			->setCon( $this->getCon() )
			->execute();

		return $key;
	}
}
