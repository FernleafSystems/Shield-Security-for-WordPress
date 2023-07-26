<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

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
			'summary'     => $summary,
		];
	}

	public function getOptionStrings( string $key ) :array {
		$con = $this->con();

		switch ( $key ) {

			case 'enable_mainwp' :
				$name = __( 'MainWP Integration', 'wp-simple-firewall' );
				$summary = __( "Turn-On Shield's Built-In Extension For MainWP Server And Client Installations", 'wp-simple-firewall' );
				$desc = [
					__( 'Easily integrate Shield Security to help you manage your site security from within MainWP.', 'wp-simple-firewall' ),
					__( "You don't need to install a separate extension for MainWP.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( "If this is a MainWP client site, you should add your MainWP Admin Server's IP address to your IP bypass list.", 'wp-simple-firewall' ) )
				];
				if ( !$con->caps->canMainwpLevel1() ) {
					$desc[] = __( 'Please upgrade your plan if you need to run the MainWP integration.', 'wp-simple-firewall' );
				}
				break;

			case 'user_form_providers' :
				$name = __( 'User Forms Bot Detection', 'wp-simple-firewall' );
				$summary = __( "Select The User Forms Provider That You Use", 'wp-simple-firewall' );
				$desc = [
					__( 'Many 3rd party plugins provide custom user login, registration, and lost password forms.', 'wp-simple-firewall' )
					.' '.__( "They aren't normally checked for Bots since they require a custom integration.", 'wp-simple-firewall' ),
					__( "Select your 3rd party providers to have Shield automatically detect Bot requests to these forms.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						__( 'Only the form types (login, registration, lost password), that you have selected in the Login Guard module will be checked.', 'wp-simple-firewall' ) ),
					sprintf( '<a href="%s">%s</a>', $con->plugin_urls->modCfgSection( $con->getModule_LoginGuard(), 'section_brute_force_login_protection' ),
						sprintf( __( 'Choose the types of forms you want %s to check', 'wp-simple-firewall' ), $con->getHumanName() ) ),
				];
				if ( !$con->caps->canThirdPartyScanUsers() ) {
					$desc[] = __( 'Please upgrade your plan if you need to protect and integrate with 3rd party user login forms.', 'wp-simple-firewall' );
				}
				break;

			case 'form_spam_providers' :
				$name = __( 'Contact Form SPAM', 'wp-simple-firewall' );
				$summary = __( 'Select The Form Providers That You Use', 'wp-simple-firewall' );
				$desc = [
					__( 'Just like WordPress comments, Contact Forms (or any type of form at all) is normally a victim of SPAM.', 'wp-simple-firewall' )
					.' '.__( "Some form developers provide CAPTCHAs to try and block SPAM, but these are often clunky and ineffective.", 'wp-simple-firewall' ),
					__( "If you want Shield to block SPAM from suspected bots without your users having to click CAPTCHAs, select the form providers you want to protect.", 'wp-simple-firewall' ),
					__( "If the provider you want isn't on the list, contact our support team and we'll see if an integration can be built.", 'wp-simple-firewall' ),
				];
				if ( !$con->caps->canThirdPartyScanSpam() ) {
					$desc[] = __( 'Please upgrade your plan if you need to protect and integrate with 3rd party form providers.', 'wp-simple-firewall' );
				}
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