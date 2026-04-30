<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;

class SecurityAdminRequestRemoveByEmail extends SecurityAdminBase {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'req_email_remove';

	protected function exec() {
		if ( !self::con()->opts->optIs( 'allow_email_override', 'Y' ) ) {
			throw new ActionException( __( 'Email override is not enabled.', 'wp-simple-firewall' ) );
		}

		( new RemoveSecAdmin() )->sendConfirmationEmail();
		$this->response()->setPayload( [
			'message' => __( 'Email sent. Ensure the link opens in THIS browser.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( true );
	}
}
