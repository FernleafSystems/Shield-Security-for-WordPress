<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\ExtensionSettingsPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Init {

	use PluginControllerConsumer;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function run() :string {
		$con = $this->getCon();
		/** @var Options $pluginOpts */
		$pluginOpts = $con->getModule_Plugin()->getOptions();

		// TODO: Consider have an "error" screen message to show it's not enabled instead?
		if ( !$pluginOpts->isEnabledMainWP() ) {
			throw new \Exception( 'MainWP Extension is not enabled' );
		}

		add_filter( 'mainwp_getextensions', function ( $exts ) {
			$con = $this->getCon();
			$exts[] = [
				'plugin'   => $this->getCon()->getRootFile(),
				// while this is a "callback" field, a Closure isn't supported as it's serialized for DB storage. Sigh.
				'callback' => [ ( new ExtensionSettingsPage() )->setCon( $con ), 'render' ],
				'icon'     => $con->getPluginUrl_Image( 'pluginlogo_col_32x32.png' ),
			];
			return $exts;
		}, 10, 1 );

		$childEnabled = apply_filters( 'mainwp_extension_enabled_check', $con->getRootFile() );
		$key = $childEnabled[ 'key' ] ?? '';
		if ( empty( $key ) ) {
			throw new \Exception( 'No child key provided' );
		}

		if ( Controller::isMainWPServerVersionSupported() && $con->isPremiumActive() ) {
			( new SyncHandler() )
				->setCon( $this->getCon() )
				->execute();
			( new UI\ManageSites\SitesListTableHandler() )
				->setCon( $this->getCon() )
				->execute();
		}

		return $key;
	}
}