<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRequestRemoveByEmail extends SecurityAdminBase {

	use Traits\NonceVerifyRequired;

	const SLUG = 'req_email_remove';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		( new RemoveSecAdmin() )
			->setMod( $this->primary_mod )
			->sendConfirmationEmail();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'Email sent. Ensure the link opens in THIS browser.' ),
		];
	}
}