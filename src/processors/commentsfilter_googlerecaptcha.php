<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		parent::run();
		add_action( 'wp', [ $this, 'setup' ] );
	}

	/**
	 * The WP Query is alive and well at this stage so we can assume certain data is available.
	 */
	public function setup() {
		if ( Services::WpComments()->isCommentsOpen() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'registerGoogleRecaptchaJs' ], 99 );
			add_action( 'comment_form_after_fields', [ $this, 'printGoogleRecaptchaCheck' ] );
		}
	}

	/**
	 * @return string
	 */
	public function printGoogleRecaptchaCheck_Filter() {
		$this->setRecaptchaToEnqueue();
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 */
	public function printGoogleRecaptchaCheck() {
		$this->setRecaptchaToEnqueue();
		echo $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	protected function getGoogleRecaptchaHtml() {
		return '<div class="icwpg-recaptcha" style="margin: 10px 0; clear:both;"></div>';
	}
}