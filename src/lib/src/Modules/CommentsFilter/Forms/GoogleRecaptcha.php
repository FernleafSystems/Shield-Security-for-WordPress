<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Forms;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class GoogleRecaptcha {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		/** @var CommentsFilter\ModCon $mod */
		$mod = $this->getMod();
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledCaptcha() && $mod->getCaptchaCfg()->ready;
	}

	protected function run() {
		add_action( 'wp', [ $this, 'setup' ] );
	}

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
