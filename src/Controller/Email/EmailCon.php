<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\Footer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class EmailCon {

	use PluginControllerConsumer;

	protected function getEmailHeader() :array {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}

	protected function getEmailFooter() :array {
		return apply_filters( 'icwp_shield_email_footer', [
			self::con()->action_router->render( Footer::SLUG )
		] );
	}

	/**
	 * Wraps up a message with header and footer
	 * @param string $to
	 * @param string $subject
	 * @param array  $message
	 */
	public function sendEmailWithWrap( $to = '', $subject = '', $message = [] ) :bool {
		return $this->sendVO(
			EmailVO::Factory(
				$to,
				$subject,
				sprintf( '<html lang="%s">%s</html>',
					Services::WpGeneral()->getLocale( '-' ),
					\implode( "<br />", \array_merge( $this->getEmailHeader(), $message, $this->getEmailFooter() ) )
				)
			)
		);
	}

	public function sendVO( EmailVO $vo ) :bool {
		$this->emailFilters( true );
		$result = wp_mail(
			$this->verifyEmailAddress( $vo->to ),
			$vo->buildSubject(),
			$vo->html
		);
		$this->emailFilters( false );
		return (bool)$result;
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
		$proposed = \trim( (string)apply_filters( 'shield/email_from', apply_filters( 'icwp_shield_from_email', $from ) ) );

		if ( Services::Data()->validEmail( $proposed ) ) {
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
			$name = self::con()->labels->Name;
		}
		else {
			$name = sprintf( '%s - %s', $name, self::con()->labels->Name );
		}
		return $name;
	}

	/**
	 * Will send email to the default recipient setup in the object.
	 * @param string $subject
	 * @param array  $message
	 */
	public function sendEmail( $subject, $message ) :bool {
		return $this->sendEmailWithWrap( null, $subject, $message );
	}

	/**
	 * @param string $e
	 * @return string
	 */
	public function verifyEmailAddress( $e = '' ) {
		return Services::Data()->validEmail( $e ) ? $e : self::con()->comps->opts_lookup->getReportEmail();
	}
}