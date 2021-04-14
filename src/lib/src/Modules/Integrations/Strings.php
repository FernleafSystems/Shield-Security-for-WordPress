<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	protected function getAuditMessages() :array {
		return [
			'spam_form_pass'     => [
				__( '"%s" submission passed SPAM check.', 'wp-simple-firewall' ),
			],
			'spam_form_fail'     => [
				__( '"%s" submission failed SPAM check.', 'wp-simple-firewall' )
			],
			'user_form_bot_pass' => [
				__( '"%s" submission for form "%s" with username "%s" passed SPAM check.', 'wp-simple-firewall' ),
			],
			'user_form_bot_fail' => [
				__( '"%s" submission for form "%s" with username "%s" failed SPAM check.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_integrations':
				$titleShort = __( 'Integrations', 'wp-simple-firewall' );
				$title = __( 'Built-In Shield Integrations', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Shield can automatically integrate with 3rd party plugins.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Only enable the integrations you require.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_user_forms':
				$titleShort = __( 'User Forms Bot Checking', 'wp-simple-firewall' );
				$title = __( 'User Forms Bot Checking', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Shield can automatically protect custom user login and registration forms provided by 3rd party plugins.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Only enable the integrations you require.", 'wp-simple-firewall' ) ),
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => is_array( $summary ) ? $summary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$con = $this->getCon();

		switch ( $key ) {

			case 'enable_mainwp' :
				$name = __( 'MainWP Integration', 'wp-simple-firewall' );
				$summary = __( "Turn-On Shield's Built-In Extension For MainWP Server And Client Installations", 'wp-simple-firewall' );
				$desc = [
					__( 'This is a ShieldPRO-only feature.', 'wp-simple-firewall' ),
					__( 'Easily integrate Shield Security to help you manage your site security from within MainWP.', 'wp-simple-firewall' ),
					__( "You don't need to install a separate extension for MainWP.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( "If this is a MainWP client site, you should add your MainWP Admin Server's IP address to your IP bypass list.", 'wp-simple-firewall' ) )
				];
				break;

			case 'user_form_providers' :
				$name = __( 'User Forms Bot Detection', 'wp-simple-firewall' );
				$summary = __( "Select The User Forms Provider That You Use", 'wp-simple-firewall' );
				$desc = [
					__( 'This is a ShieldPRO-only feature.', 'wp-simple-firewall' ),
					__( 'Many 3rd party plugins provide customer user login, registration, and lost password forms.', 'wp-simple-firewall' ),
					__( "By default, these aren't checked for Bots as they require a custom integration.", 'wp-simple-firewall' ),
					__( "Select the 3rd party plugin provider you use to have Shield automatically detect Bot requests to these forms.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( 'Only the form types (login, registration, etc) that you select in the Login Guard module will be checked.', 'wp-simple-firewall' ) ),
					sprintf( '<a href="%s">%s</a>', $con->getModule_LoginGuard()
														->getUrl_DirectLinkToSection( 'section_brute_force_login_protection' ),
						sprintf( __( 'Choose the types of forms you want %s to check', 'wp-simple-firewall' ), $con->getHumanName() ) ),
				];
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}