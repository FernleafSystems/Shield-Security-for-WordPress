<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_headers' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_security_headers' :
				$sTitle = __( 'Advanced Security Headers', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Security Headers', 'wp-simple-firewall' );
				break;

			case 'section_content_security_policy' :
				$sTitle = __( 'Content Security Policy', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restrict the sources and types of content that may be loaded and processed by visitor browsers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Content Security Policy', 'wp-simple-firewall' );
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_headers' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'x_frame' :
				$sName = __( 'Block iFrames', 'wp-simple-firewall' );
				$sSummary = __( 'Block Remote iFrames Of This Site', 'wp-simple-firewall' );
				$sDescription = __( 'The setting prevents any external website from embedding your site in an iFrame.', 'wp-simple-firewall' )
								.__( 'This is useful for preventing so-called "ClickJack attacks".', 'wp-simple-firewall' );
				break;

			case 'x_referrer_policy' :
				$sName = __( 'Referrer Policy', 'wp-simple-firewall' );
				$sSummary = __( 'Referrer Policy Header', 'wp-simple-firewall' );
				$sDescription = __( 'The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site.', 'wp-simple-firewall' );
				break;

			case 'x_xss_protect' :
				$sName = __( 'XSS Protection', 'wp-simple-firewall' );
				$sSummary = __( 'Employ Built-In Browser XSS Protection', 'wp-simple-firewall' );
				$sDescription = __( 'Directs compatible browsers to block what they detect as Reflective XSS attacks.', 'wp-simple-firewall' );
				break;

			case 'x_content_type' :
				$sName = __( 'Prevent Mime-Sniff', 'wp-simple-firewall' );
				$sSummary = __( 'Turn-Off Browser Mime-Sniff', 'wp-simple-firewall' );
				$sDescription = __( 'Reduces visitor exposure to malicious user-uploaded content.', 'wp-simple-firewall' );
				break;

			case 'enable_x_content_security_policy' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), 'CSP' );
				$sSummary = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Content Security Policy', 'wp-simple-firewall' ) );
				$sDescription = __( 'Allows for permission and restriction of all resources loaded on your site.', 'wp-simple-firewall' );
				break;

			case 'xcsp_self' :
				$sName = __( 'Self', 'wp-simple-firewall' );
				$sSummary = __( "Allow 'self' Directive", 'wp-simple-firewall' );
				$sDescription = __( "Using 'self' is generally recommended.", 'wp-simple-firewall' )
								.__( "It essentially means that resources from your own host:protocol are permitted.", 'wp-simple-firewall' );
				break;

			case 'xcsp_inline' :
				$sName = __( 'Inline Entities', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Inline Scripts and CSS', 'wp-simple-firewall' );
				$sDescription = __( 'Allows parsing of Javascript and CSS declared in-line in your html document.', 'wp-simple-firewall' );
				break;

			case 'xcsp_data' :
				$sName = __( 'Embedded Data', 'wp-simple-firewall' );
				$sSummary = __( 'Allow "data:" Directives', 'wp-simple-firewall' );
				$sDescription = __( 'Allows use of embedded data directives, most commonly used for images and fonts.', 'wp-simple-firewall' );
				break;

			case 'xcsp_eval' :
				$sName = __( 'Allow eval()', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Javascript eval()', 'wp-simple-firewall' );
				$sDescription = __( 'Permits the use of Javascript the eval() function.', 'wp-simple-firewall' );
				break;

			case 'xcsp_https' :
				$sName = __( 'HTTPS', 'wp-simple-firewall' );
				$sSummary = __( 'HTTPS Resource Loading', 'wp-simple-firewall' );
				$sDescription = __( 'Allows loading of any content provided over HTTPS.', 'wp-simple-firewall' );
				break;

			case 'xcsp_hosts' :
				$sName = __( 'Permitted Hosts', 'wp-simple-firewall' );
				$sSummary = __( 'Permitted Hosts and Domains', 'wp-simple-firewall' );
				$sDescription = __( 'You can explicitly state which hosts/domain from which content may be loaded.', 'wp-simple-firewall' )
								.' '.__( 'Take great care and test your site as you may block legitimate resources.', 'wp-simple-firewall' )
								.'<br />- '.__( 'If in-doubt, leave blank or use "*" only.', 'wp-simple-firewall' )
								.'<br />- '.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You can force only HTTPS for a given domain by prefixing it with "https://".', 'wp-simple-firewall' ) );
				break;

			case 'xcsp_custom' :
				$sName = __( 'Manual Rules', 'wp-simple-firewall' );
				$sSummary = __( 'Manual CSP Rules', 'wp-simple-firewall' );
				$sDescription = __( 'Manual CSP rules which are not covered by the rules above.', 'wp-simple-firewall' )
								.'<br />- '.__( 'Take a new line per rule.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}