<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function getEmailHeader() :array {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}

	protected function getEmailFooter() :array {
		$con = $this->getCon();
		$WP = Services::WpGeneral();

		{
			$goPro = [
				'Go PRO For The Equivalent Of 1 Cappuccino Per Month &#9749;',
				'Go PRO For The Equivalent Of 1 Beer Per Month &#127866;',
				'Go PRO For The Equivalent Of 1 Glass Of Wine Per Month &#127863;',
			];
			$benefits = [
				'The Easiest, Frustration-Free Pro-Upgrade Available Anywhere',
				'MainWP Integration',
				'Powerful, Auto-Learning Malware Scanner',
				'Plugin and Theme File Guard',
				'Vulnerability Scanner',
				'Traffic Rate Limiting',
				'WooCommerce Support',
				'Automatic Import/Export Sync Of Options Across Your WP Portfolio',
				'Powerful User Password Policies',
				'Exclusive Customer Support',
				'That Warm And Fuzzy Feeling That Comes From Supporting Future Development',
			];
			shuffle( $benefits );
		}

		$isWhitelabelled = $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled();
		$footer = [
			$this->getMod()->renderTemplate( '/email/footer.twig', [
				'strings' => [
					'benefits'  => $benefits,
					'much_more' => 'And So Much More',
					'upgrade'   => $goPro[ array_rand( $goPro ) ],
					'sent_from' => sprintf( __( 'Email sent from the %s Plugin v%s, on %s.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName(),
						$this->getCon()->getVersion(),
						$WP->getHomeUrl()
					),
					'delays'    => __( 'Note: Email delays are caused by website hosting and email providers.', 'wp-simple-firewall' ),
					'time_sent' => sprintf( __( 'Time Sent: %s', 'wp-simple-firewall' ), $WP->getTimeStampForDisplay() ),
				],
				'hrefs'   => [
					'upgrade'   => 'https://shsec.io/buyshieldproemailfooter',
					'much_more' => 'https://shsec.io/gp'
				],
				'flags'   => [
					'is_pro'           => $con->isPremiumActive(),
					'is_whitelabelled' => $isWhitelabelled
				]
			] ),
		];

		return apply_filters( 'icwp_shield_email_footer', $footer );
	}

	/**
	 * Wraps up a message with header and footer
	 * @param string $to
	 * @param string $subject
	 * @param array  $message
	 * @return bool
	 */
	public function sendEmailWithWrap( $to = '', $subject = '', $message = [] ) :bool {
		$WP = Services::WpGeneral();
		return $this->send(
			$to,
			$subject,
			sprintf( '<html lang="%s">%s</html>',
				$WP->getLocale( '-' ),
				implode( "<br />", array_merge( $this->getEmailHeader(), $message, $this->getEmailFooter() ) )
			)
		);
	}

	public function sendEmailWithTemplate( string $template, string $to, string $subject, array $body ) :bool {
		return $this->send(
			$to,
			$subject,
			$this->getMod()->renderTemplate( $template, [
				'header' => $this->getEmailHeader(),
				'body'   => $body,
				'footer' => $this->getEmailFooter(),
				'vars'   => [
					'lang' => Services::WpGeneral()->getLocale( '-' )
				]
			] )
		);
	}

	/**
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 * @uses wp_mail
	 */
	public function send( $to = '', $subject = '', $body = '' ) :bool {

		$this->emailFilters( true );
		$success = wp_mail(
			$this->verifyEmailAddress( $to ),
			sprintf( '[%s] %s', html_entity_decode( Services::WpGeneral()->getSiteName(), ENT_QUOTES ), $subject ),
			$body
		);
		$this->emailFilters( false );
		return (bool)$success;
	}

	/**
	 * @param $add - true to add, false to remove
	 */
	private function emailFilters( bool $add ) {
		if ( $add ) {
			add_filter( 'wp_mail_from', [ $this, 'setMailFrom' ], 100 );
			add_filter( 'wp_mail_from_name', [ $this, 'setMailFromName' ], 100 );
			add_filter( 'wp_mail_content_type', [ $this, 'setMailContentType' ], 100, 0 );
		}
		else {
			remove_filter( 'wp_mail_from', [ $this, 'setMailFrom' ], 100 );
			remove_filter( 'wp_mail_from_name', [ $this, 'setMailFromName' ], 100 );
			remove_filter( 'wp_mail_content_type', [ $this, 'setMailContentType' ], 100 );
		}
	}

	public function setMailContentType() :string {
		return 'text/html';
	}

	/**
	 * @param string $from
	 * @return string
	 */
	public function setMailFrom( $from ) {
		$DP = Services::Data();

		$proposed = trim( (string)apply_filters( 'shield/email_from', apply_filters( 'icwp_shield_from_email', $from ) ) );

		if ( $DP->validEmail( $proposed ) ) {
			$from = $proposed;
		}

		return $from;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function setMailFromName( $name ) :string {
		$proposed = apply_filters(
			'shield/email_from_name',
			apply_filters( 'icwp_shield_from_email_name', '' )
		);

		if ( !empty( $proposed ) ) {
			$name = $proposed;
		}
		elseif ( empty( $name ) ) {
			$name = $this->getCon()->getHumanName();
		}
		else {
			$name = sprintf( '%s - %s', $name, $this->getCon()->getHumanName() );
		}
		return $name;
	}

	/**
	 * Will send email to the default recipient setup in the object.
	 * @param string $subject
	 * @param array  $message
	 * @return bool
	 */
	public function sendEmail( $subject, $message ) {
		return $this->sendEmailWithWrap( null, $subject, $message );
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public function verifyEmailAddress( $email = '' ) {
		return Services::Data()->validEmail( $email ) ? $email : Services::WpGeneral()->getSiteAdminEmail();
	}
}