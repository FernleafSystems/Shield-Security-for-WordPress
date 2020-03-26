<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

class WooCommerce extends BaseFormProvider {

	public function run() {
		add_action( 'woocommerce_login_form', [ $this, 'printLoginFormItems_Woo' ], 100 );
		add_filter( 'woocommerce_process_login_errors', [ $this, 'checkReqLogin_Woo' ], 10, 2 );

	}

}