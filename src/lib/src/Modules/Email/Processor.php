<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\Footer;
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
		return apply_filters( 'icwp_shield_email_footer', [
			$this->getCon()->action_router->render( Footer::SLUG )
		] );
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