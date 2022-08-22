<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax\AjaxHandlerMainwp;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\ExtensionSettingsPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Init {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :string {
		$con = $this->getCon();
		/** @var Options $optsInt */
		$optsInt = $con->getModule_Integrations()->getOptions();

		// TODO: Consider have an "error" screen message to show it's not enabled instead?
		if ( !$optsInt->isEnabledMainWP() ) {
			throw new \Exception( 'MainWP Extension is not enabled' );
		}

		$extensionsPage = $this->addOurExtension();

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

			if ( $this->getMod()->isModuleRequest() && Services::WpGeneral()->isAjax() ) {
				new AjaxHandlerMainwp( $this->getMod() );
			}
		}

		return $key;
	}

	private function addOurExtension() :ExtensionSettingsPage {
		$con = $this->getCon();

		$extensionsPage = new ExtensionSettingsPage();
		$extensionParams = [
			'plugin'   => $con->getRootFile(),
			// while this is a "callback" field, a Closure isn't supported as it's serialized for DB storage. Sigh.
			'callback' => [ $extensionsPage, 'render' ],
			'icon'     => $con->urls->forImage( 'pluginlogo_col_32x32.png' ),
		];
		add_filter( 'mainwp_getextensions', function ( $exts ) use ( $extensionParams ) {
			return array_merge( $exts, [ $extensionParams ] );
		} );

		// We add Mod afterwards. It wasn't correctly saving for some reason while with it in the callback.
		return $extensionsPage->setMod( $this->getMod() );
	}
}