<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Processor_Lockdown
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_LoginProtect extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $mod->isXmlrpcBypass() ) {
			return;
		}

		// So we can allow access to the login pages if IP is whitelisted
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		if ( !empty( $opts->getCustomLoginPath() ) ) {
			$this->getSubPro( 'rename' )->execute();
		}

		if ( !$mod->isVisitorWhitelisted() ) {
			( new AntiBot\AntibotSetup() )->setMod( $mod );
			$mod->getLoginIntentController()->run();
		}
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		return $aData;
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() :array {
		return [
			'rename' => 'ICWP_WPSF_Processor_LoginProtect_WpLogin',
		];
	}
}