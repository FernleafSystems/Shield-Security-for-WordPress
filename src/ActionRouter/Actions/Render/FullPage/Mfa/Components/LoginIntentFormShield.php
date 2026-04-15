<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

class LoginIntentFormShield extends BaseForm {

	public const SLUG = 'render_shield_login_intent_form';
	public const TEMPLATE = '/components/login_intent/form.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$errorMsg = (string)( $this->action_data[ 'msg_error' ] ?? '' );
		$hasError = $errorMsg !== '';

		return [
			'hrefs'   => [
				'what_is_this' => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
			],
			'strings' => [
				'message'            => $errorMsg,
				'page_title'         => __( 'Verify your login', 'wp-simple-firewall' ),
				'page_subtitle'      => __( "Choose a method you've registered.", 'wp-simple-firewall' ),
				'secure_session'     => __( 'Secure session', 'wp-simple-firewall' ),
				'hero_heading'       => __( 'Confirm Your Identity.', 'wp-simple-firewall' ),
				'hero_body'          => __( "Pick any method you've set up. We'll get you in as soon as you confirm it's really you.", 'wp-simple-firewall' ),
				'stat_session'       => __( 'Session', 'wp-simple-firewall' ),
				'stat_session_value' => __( 'Encrypted', 'wp-simple-firewall' ),
				'stat_expires'       => __( 'Expires', 'wp-simple-firewall' ),
				'email_heading'      => __( 'Enter the 6-character code', 'wp-simple-firewall' ),
				'email_hint'         => __( 'sent to your email', 'wp-simple-firewall' ),
				'email_resend_ask'   => __( "Didn't receive it?", 'wp-simple-firewall' ),
				'ga_heading'         => __( 'Enter the 6-digit code', 'wp-simple-firewall' ),
				'ga_hint'            => __( 'from your authenticator app', 'wp-simple-firewall' ),
				'ga_rotate_note'     => __( 'Codes rotate every 30 seconds', 'wp-simple-firewall' ),
				'passkey_heading'    => __( 'Use your passkey', 'wp-simple-firewall' ),
				'passkey_body'       => __( 'Your browser will prompt you for Windows Hello, Touch ID, or a registered security key.', 'wp-simple-firewall' ),
				'passkey_cta'        => __( 'Verify with passkey', 'wp-simple-firewall' ),
				'yubi_heading'       => __( 'Touch your YubiKey', 'wp-simple-firewall' ),
				'yubi_body'          => __( 'Insert your key, tap the gold disc, and the OTP will fill in below.', 'wp-simple-firewall' ),
				'yubi_placeholder'   => __( 'Waiting for YubiKey touch...', 'wp-simple-firewall' ),
				'what_is_this'       => __( 'What is this?', 'wp-simple-firewall' ),
				/* translators: %d is a 1-indexed digit position in the OTP input row. */
				'otp_digit_aria'     => __( 'Digit %d', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'show_message' => $hasError,
			],
		];
	}
}
