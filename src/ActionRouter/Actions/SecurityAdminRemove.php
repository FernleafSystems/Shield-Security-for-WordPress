<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

class SecurityAdminRemove extends SecurityAdminBase {

	use NonceVerifyRequired;

	public const SLUG = 'secadmin_remove_confirm';
	public const RETURN_CONTEXT_CONFIGURE_SECADMIN = 'configure_secadmin';

	protected function exec() {
		( new RemoveSecAdmin() )->remove( (bool)( $this->action_data[ 'quietly' ] ?? false ) );
		$redirectUrl = self::con()->plugin_urls->zone( Secadmin::Slug() );
		switch ( sanitize_key( (string)( $this->action_data[ 'return_context' ] ?? '' ) ) ) {
			case self::RETURN_CONTEXT_CONFIGURE_SECADMIN:
				$redirectUrl = self::con()->plugin_urls->configureHome( Secadmin::Slug() );
				break;
		}
		$this->response()
			 ->setPayloadSuccess( true )
			 ->setPayloadRedirectNextStep( $redirectUrl );
	}
}
