<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_headers' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->mod()
																						->getMainFeatureName() );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_security_headers' :
				$title = __( 'Advanced Security Headers', 'wp-simple-firewall' );
				$titleShort = __( 'Security Headers', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_content_security_policy' :
				$title = __( 'Content Security Policy', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restrict the sources and types of content that may be loaded and processed by visitor browsers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Content Security Policy', 'wp-simple-firewall' );
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
		$modName = $this->mod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_headers' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;

			case 'x_frame' :
				$name = __( 'Block iFrames', 'wp-simple-firewall' );
				$summary = __( 'Block Remote iFrames Of This Site', 'wp-simple-firewall' );
				$desc = [
					__( 'The setting prevents any external website from embedding your site in an iFrame.', 'wp-simple-firewall' ),
					__( 'This is useful for preventing so-called "ClickJack attacks".', 'wp-simple-firewall' )
				];
				break;

			case 'x_referrer_policy' :
				$name = __( 'Referrer Policy', 'wp-simple-firewall' );
				$summary = __( 'Referrer Policy Header', 'wp-simple-firewall' );
				$desc = [ __( 'The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site.', 'wp-simple-firewall' ) ];
				break;

			case 'x_xss_protect' :
				$name = __( 'XSS Protection', 'wp-simple-firewall' );
				$summary = __( 'Employ Built-In Browser XSS Protection', 'wp-simple-firewall' );
				$desc = [ __( 'Directs compatible browsers to block what they detect as Reflective XSS attacks.', 'wp-simple-firewall' ) ];
				break;

			case 'x_content_type' :
				$name = __( 'Prevent Mime-Sniff', 'wp-simple-firewall' );
				$summary = __( 'Turn-Off Browser Mime-Sniff', 'wp-simple-firewall' );
				$desc = [ __( 'Reduces visitor exposure to malicious user-uploaded content.', 'wp-simple-firewall' ) ];
				break;

			case 'enable_x_content_security_policy' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), 'CSP' );
				$summary = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Content Security Policy', 'wp-simple-firewall' ) );
				$desc = [
					__( 'Allows for permission and restriction of all resources loaded on your site.', 'wp-simple-firewall' ),
					__( 'Use this in conjunction with the Manual Rules section - you must supply your own CSP rules.', 'wp-simple-firewall' ),
				];
				break;

			case 'xcsp_custom' :
				$name = __( 'Manual Rules', 'wp-simple-firewall' );
				$summary = __( 'Manual CSP Rules', 'wp-simple-firewall' );
				$desc = [
					'- '.__( 'Take a new line per rule.', 'wp-simple-firewall' ),
					'- '.__( 'We provide this feature as-is: to allow you to add custom CSP rules to your site.', 'wp-simple-firewall' ),
					'- '.__( "We don't provide support for creating CSP rules and whether they're correct for your site.", 'wp-simple-firewall' ),
					'- '.__( "Many WordPress caching plugins ignore HTTP Headers - if they're not showing up, disable page caching.", 'wp-simple-firewall' )
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