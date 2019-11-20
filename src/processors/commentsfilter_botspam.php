<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter_BotSpam extends Modules\BaseShield\ShieldProcessor {

	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	private $sFormId;

	public function run() {
		add_action( 'comment_form', [ $this, 'printGaspFormItems' ], 1 );
	}

	public function onWpEnqueueJs() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		$oConn = $this->getCon();

		$sAsset = 'shield-comments';
		$sUnique = $oMod->prefix( $sAsset );
		wp_register_script(
			$sUnique,
			$oConn->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$oConn->getVersion(),
			true
		);
		wp_enqueue_script( $sUnique );

		wp_localize_script(
			$sUnique,
			'shield_comments',
			[
				'form_selectors' => implode( ',', [ '' ] ),
				'cbname'         => $this->tokenCreateStore(),
				'vars'           => [
					'uniq'     => $this->getUniqueFormId(),
					'cooldown' => $oMod->getTokenCooldown(),
					'expires' => $oMod->getTokenExpireInterval(),
				],
				'strings'        => [
					'label'           => $oMod->getTextOpt( 'custom_message_checkbox' ),
					'alert'           => $oMod->getTextOpt( 'custom_message_alert' ),
					'comment_reload'  => $oMod->getTextOpt( 'custom_message_comment_reload' ),
					'js_comment_wait' => $oMod->getTextOpt( 'custom_message_comment_wait' ),
				],
				'flags'          => [
					'gasp'  => true,
					'recap' => $oMod->isGoogleRecaptchaEnabled(),
				]
			]
		);
	}

	public function printGaspFormItems() {
		echo $this->getGaspCommentsHtml();
	}

	/**
	 * @return string
	 */
	private function getGaspCommentsHtml() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();

		$sCommentWait = $oMod->getTextOpt( 'custom_message_comment_wait' );
		$aData = [
			'form_id'         => $this->getUniqueFormId(),
			'ts'              => Services::Request()->ts(),
			'token'           => $this->tokenCreateStore(),
			'alert'           => $oMod->getTextOpt( 'custom_message_alert' ),
			'comment_reload'  => $oMod->getTextOpt( 'custom_message_comment_reload' ),
			'cooldown'        => $oMod->getTokenCooldown(),
			'expire'          => $oMod->getTokenExpireInterval(),
			'comment_wait'    => $sCommentWait,
			'js_comment_wait' => str_replace( '%s', '"+nRemaining+"', $sCommentWait ),
			'confirm'         => $oMod->getTextOpt( 'custom_message_checkbox' ),
		];

		return $oMod->renderTemplate( 'snippets/comment_form_botbox.twig', $aData, true );
	}

	/**
	 * @return string
	 */
	private function tokenCreateStore() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();

		$nTs = Services::Request()->ts();
		$nPostId = Services::WpPost()->getCurrentPostId();
		$sToken = $this->generateNewToken( $nTs, $nPostId );

		Services::WpGeneral()->setTransient(
			$oMod->prefix( 'comtok-'.md5( sprintf( '%s-%s-%s', $nPostId, $nTs, Services::IP()->getRequestIp() ) ) ),
			$sToken,
			$oMod->getTokenExpireInterval()
		);

		return $sToken;
	}

	/**
	 * @param int    $nTs
	 * @param string $nPostId
	 * @return string
	 */
	private function generateNewToken( $nTs, $nPostId ) {
		$oMod = $this->getCon()->getModule_Plugin();
		return hash_hmac( 'sha1', $nPostId.Services::IP()->getRequestIp().$nTs, $oMod->getPluginInstallationId() );
	}

	/**
	 * @return string
	 */
	private function getUniqueFormId() {
		if ( !isset( $this->sFormId ) ) {
			$oDp = Services::Data();
			$sId = $oDp->generateRandomLetter().$oDp->generateRandomString( rand( 7, 23 ), 7 );
			$this->sFormId = preg_replace(
				'#[^a-zA-Z0-9]#', '',
				apply_filters( 'icwp_shield_cf_gasp_uniqid', $sId ) );
		}
		return $this->sFormId;
	}
}