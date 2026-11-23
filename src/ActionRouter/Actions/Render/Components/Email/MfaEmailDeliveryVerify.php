<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

/**
 * The email whose link proves this site's email arrives, which turns email 2FA on.
 */
class MfaEmailDeliveryVerify extends EmailBase {

	public const SLUG = 'email_mfa_email_delivery_verify';
	public const TEMPLATE = '/email/mfa_email_delivery_verify.twig';

	protected function getBodyData() :array {
		$site = $this->action_data[ 'site' ];
		return [
			'hrefs'   => [
				// Internally generated via noncedPluginAction(); the template auto-escapes it.
				'verify' => $this->action_data[ 'verify_href' ],
			],
			'strings' => [
				'heading'    => __( 'Confirm this site can send email', 'wp-simple-firewall' ),
				'intro'      => sprintf(
					/* translators: %s: site domain, such as example.com */
					__( 'Email two-factor authentication was turned on for %s. It stays off until this link is clicked, so that Shield knows emails from your site arrive.', 'wp-simple-firewall' ),
					$site
				),
				'verify'     => __( 'Confirm email works', 'wp-simple-firewall' ),
				'or_copy'    => __( 'Or copy and paste this link:', 'wp-simple-firewall' ),
				'logged_in'  => sprintf(
					/* translators: %s: site domain, such as example.com */
					__( "Open it in a browser where you're logged in to %s as an administrator.", 'wp-simple-firewall' ),
					$site
				),
				'expiry'     => __( 'It works for 24 hours. Only the link in the newest email works.', 'wp-simple-firewall' ),
				'unexpected' => __( "Didn't expect this? You can ignore it. Email 2FA stays off.", 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site',
			'verify_href',
		];
	}
}
