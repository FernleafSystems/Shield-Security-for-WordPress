<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRequestRemoveByEmail extends SecurityAdminBase {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'req_email_remove';

	protected function exec() {
		( new RemoveSecAdmin() )->sendConfirmationEmail();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'Email sent. Ensure the link opens in THIS browser.' ),
		];
	}
}