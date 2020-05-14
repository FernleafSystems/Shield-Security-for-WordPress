<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Email extends Modules\BaseShield\ShieldProcessor {

	const Slug = 'email';

	/**
	 * @return array
	 */
	protected function getEmailHeader() {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}

	/**
	 * @return array
	 */
	protected function getEmailFooter() {
		$oCon = $this->getCon();
		$oWp = Services::WpGeneral();

		{
			$aGoProPhrases = [
				'Go PRO For The Equivalent Of 1 Cappuccino Per Month &#9749;',
				'Go PRO For The Equivalent Of 1 Beer Per Month &#127866;',
				'Go PRO For The Equivalent Of 1 Glass Of Wine Per Month &#127863;',
			];
			$aBenefits = [
				'The Easiest, Frustration-Free Pro-Upgrade Available Anywhere',
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
			shuffle( $aBenefits );
		}
		$aFooter = [
			'',
			$this->getMod()
				 ->renderTemplate( '/snippets/email/footer.twig', [
					 'strings' => [
						 'benefits'  => $aBenefits,
						 'much_more' => 'And So Much More',
						 'upgrade'   => $aGoProPhrases[ array_rand( $aGoProPhrases ) ],
					 ],
					 'hrefs'   => [
						 'upgrade'   => 'https://shsec.io/buyshieldproemailfooter',
						 'much_more' => 'https://shsec.io/gp'
					 ],
					 'flags'   => [
						 'is_pro'           => $oCon->isPremiumActive(),
						 'is_whitelabelled' => $oCon->getModule_SecAdmin()->isWlEnabled()
					 ]
				 ] ),
			'',
			sprintf( __( 'Email sent from the %s Plugin v%s, on %s.', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName(),
				$this->getCon()->getVersion(),
				$oWp->getHomeUrl()
			),
			__( 'Note: Email delays are caused by website hosting and email providers.', 'wp-simple-firewall' ),
			sprintf( __( 'Time Sent: %s', 'wp-simple-firewall' ), $oWp->getTimeStampForDisplay() )
		];

		return apply_filters( 'icwp_shield_email_footer', $aFooter );
	}

	/**
	 * Wraps up a message with header and footer
	 * @param string $sAddress
	 * @param string $sSubject
	 * @param array  $aMessage
	 * @return bool
	 */
	public function sendEmailWithWrap( $sAddress = '', $sSubject = '', $aMessage = [] ) {
		$oWP = Services::WpGeneral();
		return $this->send(
			$sAddress,
			sprintf( '[%s] %s', html_entity_decode( $oWP->getSiteName(), ENT_QUOTES ), $sSubject ),
			sprintf( '<html lang="%s">%s</html>',
				$oWP->getLocale( '-' ),
				implode( "<br />", array_merge( $this->getEmailHeader(), $aMessage, $this->getEmailFooter() ) )
			)
		);
	}

	/**
	 * @param string $sAddress
	 * @param string $sSubject
	 * @param string $sMessageBody
	 * @return bool
	 * @uses wp_mail
	 */
	public function send( $sAddress = '', $sSubject = '', $sMessageBody = '' ) {

		$this->emailFilters( true );
		$bSuccess = wp_mail(
			$this->verifyEmailAddress( $sAddress ),
			$sSubject,
			$sMessageBody
		);
		$this->emailFilters( false );

		return $bSuccess;
	}

	/**
	 * @param $bAdd - true to add, false to remove
	 */
	protected function emailFilters( $bAdd ) {
		if ( $bAdd ) {
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

	/**
	 * @return string
	 */
	public function setMailContentType() {
		return 'text/html';
	}

	/**
	 * @param string $sFrom
	 * @return string
	 */
	public function setMailFrom( $sFrom ) {
		$oDP = Services::Data();
		$sProposedFrom = apply_filters( 'icwp_shield_from_email', '' );
		if ( $oDP->validEmail( $sProposedFrom ) ) {
			$sFrom = $sProposedFrom;
		}
		// We help out by trying to correct any funky "from" addresses
		// So, at the very least, we don't fail on this for our emails.
		if ( !$oDP->validEmail( $sFrom ) ) {
			$aUrlParts = @parse_url( Services::WpGeneral()->getWpUrl() );
			if ( !empty( $aUrlParts[ 'host' ] ) ) {
				$sProposedFrom = 'wordpress@'.$aUrlParts[ 'host' ];
				if ( $oDP->validEmail( $sProposedFrom ) ) {
					$sFrom = $sProposedFrom;
				}
			}
		}
		return $sFrom;
	}

	/**
	 * @param string $sFromName
	 * @return string
	 */
	public function setMailFromName( $sFromName ) {
		$sProposedFromName = apply_filters( 'icwp_shield_from_email_name', '' );
		if ( !empty( $sProposedFromName ) ) {
			$sFromName = $sProposedFromName;
		}
		else {
			$sFromName = sprintf( '%s - %s', $sFromName, $this->getCon()->getHumanName() );
		}
		return $sFromName;
	}

	/**
	 * Will send email to the default recipient setup in the object.
	 * @param string $sEmailSubject
	 * @param array  $aMessage
	 * @return bool
	 */
	public function sendEmail( $sEmailSubject, $aMessage ) {
		return $this->sendEmailWithWrap( null, $sEmailSubject, $aMessage );
	}

	/**
	 * @param string $sEmail
	 * @return string
	 */
	public function verifyEmailAddress( $sEmail = '' ) {
		return Services::Data()->validEmail( $sEmail ) ? $sEmail : Services::WpGeneral()->getSiteAdminEmail();
	}
}