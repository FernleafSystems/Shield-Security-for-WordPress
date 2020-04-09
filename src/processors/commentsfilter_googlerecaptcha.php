<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha extends Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
		add_action( 'wp', [ $this, 'setup' ] );
	}

	/**
	 * The WP Query is alive and well at this stage so we can assume certain data is available.
	 */
	public function setup() {
		if ( Services::WpComments()->isCommentsOpen() ) {
			$this->getCon()
				 ->getModule_Plugin()
				 ->getCaptchaEnqueue()
				 ->setMod( $this->getMod() )
				 ->setToEnqueue();
			add_action( 'comment_form_after_fields', [ $this, 'printGoogleRecaptchaCheck' ] );
		}
	}

	public function printGoogleRecaptchaCheck() {
		echo $this->getCon()
				  ->getModule_Plugin()
				  ->getCaptchaEnqueue()
				  ->getCaptchaHtml();
	}
}