<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $mod->isXmlrpcBypass() ) {
			return;
		}

		// So we can allow access to the login pages if IP is whitelisted
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !empty( $opts->getCustomLoginPath() ) ) {
			( new \ICWP_WPSF_Processor_LoginProtect_WpLogin( $mod ) )->execute();
		}

		if ( !$mod->isVisitorWhitelisted() ) {
			( new Lib\AntiBot\AntibotSetup() )->setMod( $mod );
			$mod->getLoginIntentController()->run();
		}
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $data
	 * @return array
	 */
	public function tracking_DataCollect( $data ) {
		$data = parent::tracking_DataCollect( $data );
		$sSlug = $this->getMod()->getSlug();
		$data[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ]
			= ( $data[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ) ? 1 : 0;
		return $data;
	}
}