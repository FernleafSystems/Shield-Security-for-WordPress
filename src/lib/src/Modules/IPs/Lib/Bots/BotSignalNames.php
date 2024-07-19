<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

class BotSignalNames {

	public function getBotSignalName( $field ) :string {
		return $this->getBotSignalNames()[ \str_replace( '_at', '', $field ) ] ?? 'Unknown';
	}

	/**
	 * @return string[]
	 */
	public function getBotSignalNames() :array {
		return [
			'created'         => __( 'New Visitor Bonus', 'wp-simple-firewall' ),
			'known'           => __( 'A Known Service Provider/Bot', 'wp-simple-firewall' ),
			'notbot'          => __( 'silentCAPTCHAv1 Registration', 'wp-simple-firewall' ),
			'altcha'          => __( 'silentCAPTCHAv2 Registration', 'wp-simple-firewall' ),
			'frontpage'       => __( 'Any Frontend Page Visited', 'wp-simple-firewall' ),
			'loginpage'       => __( 'Login Page Visited', 'wp-simple-firewall' ),
			'bt404'           => __( '404 Triggered', 'wp-simple-firewall' ),
			'btauthorfishing' => __( 'Username Fishing', 'wp-simple-firewall' ),
			'btfake'          => __( 'Fake Web Crawler', 'wp-simple-firewall' ),
			'btcheese'        => __( 'Link Cheese', 'wp-simple-firewall' ),
			'btloginfail'     => __( 'Login Fail', 'wp-simple-firewall' ),
			'btua'            => __( 'Invalid User Agent', 'wp-simple-firewall' ),
			'btxml'           => __( 'XMLRPC Access', 'wp-simple-firewall' ),
			'btlogininvalid'  => __( 'Invalid Login Username', 'wp-simple-firewall' ),
			'btinvalidscript' => __( 'Invalid Script Access', 'wp-simple-firewall' ),
			'cooldown'        => __( 'Cooldown Triggered', 'wp-simple-firewall' ),
			'humanspam'       => __( 'Comment Triggered Human SPAM Detection', 'wp-simple-firewall' ),
			'markspam'        => __( 'Comment Marked As SPAM', 'wp-simple-firewall' ),
			'unmarkspam'      => __( 'Comment Unmarked As SPAM', 'wp-simple-firewall' ),
			'auth'            => __( 'Authenticated With Site', 'wp-simple-firewall' ),
			'ratelimit'       => __( 'Rate Limit Exceeded', 'wp-simple-firewall' ),
			'captchapass'     => __( 'Captcha Verification Passed', 'wp-simple-firewall' ),
			'captchafail'     => __( 'Captcha Verification Failed', 'wp-simple-firewall' ),
			'firewall'        => __( 'Firewall Triggered', 'wp-simple-firewall' ),
			'offense'         => __( 'Offense Triggered', 'wp-simple-firewall' ),
			'blocked'         => __( 'IP Blocked', 'wp-simple-firewall' ),
			'unblocked'       => __( 'IP Unblocked', 'wp-simple-firewall' ),
			'bypass'          => __( 'IP Bypassed', 'wp-simple-firewall' ),
		];
	}
}