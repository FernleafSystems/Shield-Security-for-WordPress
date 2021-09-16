<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'spam_form_pass'     => [
				'name'  => __( 'SPAM Check Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission passed SPAM check.', 'wp-simple-firewall' ),
				],
			],
			'spam_form_fail'     => [
				'name'  => __( 'SPAM Check Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission failed SPAM check.', 'wp-simple-firewall' ),
				],
			],
			'user_form_bot_pass' => [
				'name'  => __( 'User Bot Check Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission for form "{{action}}" with username "{{username}}" passed Bot check.', 'wp-simple-firewall' ),
				],
			],
			'user_form_bot_fail' => [
				'name'  => __( 'User Bot Check Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission for form "{{action}}" with username "{{username}}" failed Bot check.', 'wp-simple-firewall' ),
				],
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
					sprintf( '%s - %s %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Shield can automatically protect 3rd party login and registration forms against Bots.", 'wp-simple-firewall' ),
						__( "It uses our exclusive AntiBot Detection Engine to reliably identify bots.", 'wp-simple-firewall' )
					),
					sprintf( '%s - %s (%s)', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Only enable the integrations you require.", 'wp-simple-firewall' ),
						__( "WordPress is always enabled.", 'wp-simple-firewall' )
					),
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
					__( 'Many 3rd party plugins provide custom user login, registration, and lost password forms.', 'wp-simple-firewall' )
					.' '.__( "They aren't normally checked for Bots since they require a custom integration.", 'wp-simple-firewall' ),
					__( "Select your 3rd party providers to have Shield automatically detect Bot requests to these forms.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( 'Only the form types (login, registration, lost password), that you have selected in the Login Guard module will be checked.', 'wp-simple-firewall' ) ),
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