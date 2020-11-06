<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\ExtensionSettingsPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Init {

	use ModConsumer;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function run() :string {
		$con = $this->getCon();
		/** @var Options $integOpts */
		$integOpts = $con->getModule_Integrations()->getOptions();

		// TODO: Consider have an "error" screen message to show it's not enabled instead?
		if ( !$integOpts->isEnabledMainWP() ) {
			throw new \Exception( 'MainWP Extension is not enabled' );
		}

		$extensionsPage = ( new ExtensionSettingsPage() )->setMod( $this->getMod() );
		add_filter( 'mainwp_getextensions', function ( $exts ) use ( $extensionsPage ) {
			$con = $this->getCon();
			$exts[] = [
				'plugin'   => $this->getCon()->getRootFile(),
				// while this is a "callback" field, a Closure isn't supported as it's serialized for DB storage. Sigh.
				'callback' => [ $extensionsPage, 'render' ],
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
				->setMod( $this->getMod() )
				->execute();
			( new UI\ManageSites\SitesListTableHandler() )
				->setMod( $this->getMod() )
				->execute();
			$extensionsPage->execute();
		}

		return $key;
	}
}