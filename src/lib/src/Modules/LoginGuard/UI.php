<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\WorldTimeApi;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function getSectionNotices( string $section ) :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [];

		if ( $section == 'section_2fa_email' ) {

			if ( $opts->isEnabledEmailAuth() && !$opts->getIfCanSendEmailVerified() ) {
				$notices[] = __( "The ability of this site to send email hasn't been verified.", 'wp-simple-firewall' )
							 .'<br/>'.__( 'Please click to re-save your settings to trigger another verification email.', 'wp-simple-firewall' );
			}

			$notices[] =
				__( '2FA by email demands that your WP site is properly configured to send email.', 'wp-simple-firewall' )
				.'<br/>'.__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', trim( __( 'Learn More.', 'wp-simple-firewall' ), '.' ) );
		}

		return $notices;
	}

	public function getSectionWarnings( string $section ) :array {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$warnings = [];

		if ( $section == 'section_brute_force_login_protection' ) {

			if ( empty( $opts->getBotProtectionLocations() ) ) {
				$warnings[] = __( "AntiBot detection isn't being applied to your site because you haven't selected any forms to protect, such as Login or Register.", 'wp-simple-firewall' );
			}

			$modIntegrations = $con->getModule_Integrations();
			$installedButNotEnabledProviders = array_filter(
				$modIntegrations->getController_UserForms()->enumProviders(),
				function ( $providerClass ) use ( $modIntegrations ) {
					/** @var BaseHandler $provider */
					$provider = ( new $providerClass() )->setMod( $modIntegrations );
					return !$provider->isEnabled() && $provider::IsProviderInstalled();
				}
			);

			if ( !empty( $installedButNotEnabledProviders ) ) {
				$warnings[] = sprintf( __( "%s has an integration available to protect the login forms of a 3rd party plugin you're using: %s", 'wp-simple-firewall' ),
					$con->getHumanName(),
					sprintf( '<a href="%s">%s</a>',
						$con->getModule_Integrations()->getUrl_DirectLinkToSection( 'section_user_forms' ),
						sprintf( __( "View the available integrations.", 'wp-simple-firewall' ), $con->getHumanName() )
					)
				);
			}
		}
		elseif ( $section == 'section_2fa_ga' ) {
			try {
				$diff = ( new WorldTimeApi() )->diffServerWithReal();
				if ( $diff > 10 ) {
					$warnings[] = __( 'It appears that your server time configuration is out of sync - Please contact your server admin, as features like Google Authenticator wont work.', 'wp-simple-firewall' );
				}
			}
			catch ( \Exception $e ) {
			}
		}
		elseif ( $section == 'section_2fa_email' ) {
			$nonRoles = array_diff(
				$opts->getEmail2FaRoles(),
				Services::WpUsers()->getAvailableUserRoles()
			);
			if ( count( $nonRoles ) > 0 ) {
				$warnings[] = sprintf( '%s: %s',
					__( "Certain user roles are set for email authentication enforcement that aren't currently available" ),
					implode( ', ', $nonRoles ) );
			}
		}

		return $warnings;
	}

	public function getSectionCriticalWarnings( string $section ) :array {
		$warnings = [];
		if ( $section == 'section_rename_wplogin' ) {
			$isBypass = ( new IpRuleStatus( $this->getCon()->this_req->ip ) )
				->setMod( $this->getMod() )
				->isBypass();
			if ( $isBypass ) {
				$warnings[] = sprintf( __( "Your IP address is whitelisted! This setting doesn't apply to YOU, so you must always use the normal login page: %s" ),
					basename( Services::WpGeneral()->getLoginUrl() ) );
			}
		}
		return $warnings;
	}
}