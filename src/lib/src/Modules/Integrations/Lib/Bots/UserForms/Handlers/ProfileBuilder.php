<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * https://wordpress.org/plugins/profile-builder/
 */
class ProfileBuilder extends Base {

	protected function register() {
		add_filter( 'wppb_output_field_errors_filter', [ $this, 'checkRegister_PB' ], 100 );
	}

	/**
	 * @param array $errors
	 * @return array
	 */
	public function checkRegister_PB( $errors ) {
		if ( empty( $errors ) && $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			$errors[ 'shield-fail-register' ] = sprintf( '<span class="wppb-form-error">%s</span>', $this->getErrorMessage() );
		}
		return $errors;
	}
}