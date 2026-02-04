<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class UserEmailValidation extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'user_email_validation';

	protected function getOptConfigKey() :string {
		return 'reg_email_validate';
	}

	protected function testIfProtected() :bool {
		return !empty( self::con()->comps->opts_lookup->getEmailValidateChecks() );
	}

	public function title() :string {
		return __( 'User Registration Email Validation', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Newly registered users have their email address checked for valid and non-SPAM domain names.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Newly registered users don't have their email address checked for valid and non-SPAM domain names.", 'wp-simple-firewall' );
	}
}