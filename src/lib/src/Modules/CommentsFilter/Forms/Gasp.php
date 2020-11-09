<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Forms;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Gasp {

	use ModConsumer;
	use OneTimeExecute;

	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	private $formID;

	/**
	 * @var bool
	 */
	private $bFormItemPrinted = false;

	protected function canRun() {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		return !Services::Request()->isPost() && $opts->isEnabledGaspCheck() && !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		add_action( 'wp', [ $this, 'onWP' ] );
		add_action( 'wp_footer', [ $this, 'maybeDequeueScript' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
	}

	public function onWP() {
		add_action( 'comment_form', [ $this, 'printGaspFormItems' ], 1 );
	}

	public function onWpEnqueueJs() {
		/** @var CommentsFilter\ModCon $mod */
		$mod = $this->getMod();
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		$con = $this->getCon();

		$sAsset = 'shield-comments';
		$handle = $con->prefix( 'shield-comments' );
		wp_register_script(
			$handle,
			$con->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$con->getVersion(),
			true
		);
		wp_enqueue_script( $handle );

		$ts = Services::Request()->ts();
		$aNonce = $mod->getAjaxActionData( 'comment_token'.Services::IP()->getRequestIp() );
		$aNonce[ 'ts' ] = $ts;
		$aNonce[ 'post_id' ] = Services::WpPost()->getCurrentPostId();

		wp_localize_script(
			$handle,
			'shield_comments',
			[
				'ajax'    => [
					'comment_token' => $aNonce,
				],
				'vars'    => [
					'cbname'   => 'cb_nombre'.rand(),
					'botts'    => $ts,
					'token'    => 'not created',
					'uniq'     => $this->getUniqueFormId(),
					'cooldown' => $opts->getTokenCooldown(),
					'expires'  => $opts->getTokenExpireInterval(),
				],
				'strings' => [
					'label'           => $mod->getTextOpt( 'custom_message_checkbox' ),
					'alert'           => $mod->getTextOpt( 'custom_message_alert' ),
					'comment_reload'  => $mod->getTextOpt( 'custom_message_comment_reload' ),
					'js_comment_wait' => $mod->getTextOpt( 'custom_message_comment_wait' ),
				],
				'flags'   => [
					'gasp'  => true,
					'recap' => $opts->isEnabledCaptcha() && $mod->getCaptchaCfg()->ready,
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

	private function getUniqueFormId() :string {
		if ( !isset( $this->formID ) ) {
			$DP = Services::Data();
			$id = $DP->generateRandomLetter().$DP->generateRandomString( rand( 7, 23 ), 7 );
			$this->formID = preg_replace(
				'#[^a-zA-Z0-9]#', '',
				apply_filters( 'icwp_shield_cf_gasp_uniqid', $id ) );
		}
		return $this->formID;
	}
}
