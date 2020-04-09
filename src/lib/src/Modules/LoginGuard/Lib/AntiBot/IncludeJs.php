<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class IncludeJs
 * @package    FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot
 * @deprecated 9.0
 */
class IncludeJs {

	use ModConsumer;

	private static $bAntiBotJsEnqueued = false;

	public function run() {
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
		}
	}

	public function onWpEnqueueJs() {
		$oCon = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$sAsset = 'shield-antibot';
		$sUnique = $oCon->prefix( $sAsset );
		wp_register_script(
			$sUnique,
			$oCon->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$oCon->getVersion(),
			true
		);
		wp_enqueue_script( $sUnique );

		wp_localize_script(
			$sUnique,
			'icwp_wpsf_vars_lpantibot',
			[
				'form_selectors' => implode( ',', $oOpts->getAntiBotFormSelectors() ),
				'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
				'cbname'         => $oMod->getGaspKey(),
				'strings'        => [
					'label'   => $oMod->getTextImAHuman(),
					'alert'   => $oMod->getTextPleaseCheckBox(),
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				],
				'flags'          => [
					'gasp'  => $oOpts->isEnabledGaspCheck(),
					'recap' => $oMod->isGoogleRecaptchaEnabled(),
				]
			]
		);
	}
}
