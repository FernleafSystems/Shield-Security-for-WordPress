<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class ArmemberLite extends Base {

	protected function register() {
		add_filter( 'armember_validate_spam_filter_fields', [ $this, 'checkArmemberForm' ] );
	}

	public function checkArmemberForm( $validate ) {
		if ( $validate && $this->setAuditAction( 'armember_lite_form' )->isBotBlockRequired() ) {
			$labels = self::con()->labels;
			global $arm_global_settings;
			if ( !empty( $arm_global_settings->common_message ) && \is_array( $arm_global_settings->common_message ) ) {
				/* translators: %1$s: security plugin name, %2$s: silentCAPTCHA */
				$arm_global_settings->common_message[ 'arm_spam_msg' ] = sprintf( 'Failed %1$s %2$s Bot Check', $labels->Name, $labels->getBrandName( 'silentcaptcha' ) );
			}
			$validate = false;
		}
		return $validate;
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( 'MEMBERSHIPLITE_VERSION' ) && \version_compare( MEMBERSHIPLITE_VERSION, '4.0', '>=' );
	}
}