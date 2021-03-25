<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Forms;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Gasp {

	use ModConsumer;
	use ExecOnce;

	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	private $formID;

	/**
	 * @var bool
	 */
	private $formItemsPrinted = false;

	protected function canRun() :bool {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		return !Services::Request()->isPost() && $opts->isEnabledGaspCheck() && !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		add_action( 'wp', [ $this, 'onWP' ] );
		add_action( 'wp_footer', [ $this, 'maybeDequeueScript' ] );
	}

	public function onWP() {
		$this->enqueueJS();
		add_action( 'comment_form', [ $this, 'printGaspFormItems' ], 1 );
	}

	protected function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/comments';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				/** @var CommentsFilter\ModCon $mod */
				$mod = $this->getMod();
				/** @var CommentsFilter\Options $opts */
				$opts = $this->getOptions();

				$ts = Services::Request()->ts();
				$nonce = $mod->getAjaxActionData( 'comment_token'.Services::IP()->getRequestIp() );
				$nonce[ 'ts' ] = $ts;
				$nonce[ 'post_id' ] = Services::WpPost()->getCurrentPostId();

				$localz[] = [
					'shield/comments',
					'shield_comments',
					[
						'ajax'    => [
							'comment_token' => $nonce,
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
				];
				return $localz;
			} );

			return $enqueues;
		} );
	}

	/**
	 * If the comment form component hasn't been printed, there's no comment form to protect.
	 */
	public function maybeDequeueScript() {
		if ( empty( $this->formItemsPrinted ) ) {
			wp_dequeue_script( $this->getCon()->prefix( 'shield/comments' ) );
		}
	}

	public function printGaspFormItems() {
		$this->formItemsPrinted = true;
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
