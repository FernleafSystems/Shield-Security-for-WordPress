<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SpamUserRegisterBlock extends Base {

	public function title() :string {
		return __( 'Block SPAM User Registrations', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Attempt to identify SPAM users and prevent their successful account creation.', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( !empty( self::con()->comps->opts_lookup->getEmailValidateChecks() ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			// Checks only run when registration email validation is on, so that is the setting to change first.
			$status[ 'exp' ][] = $this->explanationFixedBy(
				__( 'There are no checks for SPAM user email addresses during user registration.', 'wp-simple-firewall' ),
				self::con()->opts->optGet( 'reg_email_validate' ) === 'disabled' ? 'reg_email_validate' : 'email_checks'
			);
		}

		return $status;
	}

	protected function configureTreatsBadStatusAsWarning() :bool {
		return true;
	}
}
