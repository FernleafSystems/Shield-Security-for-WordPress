<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter_BotSpam extends Modules\BaseShield\ShieldProcessor {

	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	private $sFormId;

	/**
	 * @var bool
	 */
	private $bFormItemPrinted = false;

	public function run() {
		add_action( 'wp', [ $this, 'onWp' ] );
		add_action( 'wp_footer', [ $this, 'maybeDequeueScript' ] );
	}

	public function onWp() {
		add_action( 'comment_form', [ $this, 'printGaspFormItems' ], 1 );
	}

	public function onWpEnqueueJs() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$oConn = $this->getCon();

		$sAsset = 'shield-comments';
		$sUnique = $oConn->prefix( 'shield-comments' );
		wp_register_script(
			$sUnique,
			$oConn->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$oConn->getVersion(),
			true
		);
		wp_enqueue_script( $sUnique );

		$nTs = Services::Request()->ts();
		$aNonce = $oMod->getAjaxActionData( 'comment_token'.Services::IP()->getRequestIp() );
		$aNonce[ 'ts' ] = $nTs;
		$aNonce[ 'post_id' ] = Services::WpPost()->getCurrentPostId();

		wp_localize_script(
			$sUnique,
			'shield_comments',
			[
				'ajax'    => [
					'comment_token' => $aNonce,
				],
				'vars'    => [
					'cbname'   => 'cb_nombre'.rand(),
					'botts'    => $nTs,
					'token'    => 'not created',
					'uniq'     => $this->getUniqueFormId(),
					'cooldown' => $oOpts->getTokenCooldown(),
					'expires'  => $oOpts->getTokenExpireInterval(),
				],
				'strings' => [
					'label'           => $oMod->getTextOpt( 'custom_message_checkbox' ),
					'alert'           => $oMod->getTextOpt( 'custom_message_alert' ),
					'comment_reload'  => $oMod->getTextOpt( 'custom_message_comment_reload' ),
					'js_comment_wait' => $oMod->getTextOpt( 'custom_message_comment_wait' ),
				],
				'flags'   => [
					'gasp'  => true,
					'recap' => $oMod->isEnabledCaptcha(),
				]
			]
		);
	}

	/**
	 * If the comment form component hasn't been printed, there's no comment form to protect.
	 */
	public function maybeDequeueScript() {
		if ( empty( $this->bFormItemPrinted ) ) {
			wp_dequeue_script( $this->getCon()->prefix( 'shield-comments' ) );
		}
	}

	public function printGaspFormItems() {
		$this->bFormItemPrinted = true;
		echo $this->getMod()
				  ->renderTemplate(
					  'snippets/comment_form_botbox.twig',
					  [ 'uniq' => $this->getUniqueFormId() ],
					  true
				  );
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