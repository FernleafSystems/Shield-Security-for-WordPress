<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\Footer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ConvertHtmlToText;
use FernleafSystems\Wordpress\Services\Services;

class EmailCon {

	use PluginControllerConsumer;

	private ?string $plainTextBody = null;

	protected function getEmailHeader() :array {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}

	protected function getEmailFooter() :array {
		return apply_filters( 'icwp_shield_email_footer', [
			self::con()->action_router->render( Footer::class )
		] );
	}

	/**
	 * Wraps up a message with header and footer
	 * @param string $to
	 * @param string $subject
	 * @param array  $message
	 * @deprecated 21.2.0 Use sendVO()
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
		$this->plainTextBody = $this->buildPlainTextBody( $vo );
		$this->emailFilters( true );
		try {
			$result = wp_mail(
				$this->verifyEmailAddress( $vo->to ),
				$vo->buildSubject(),
				$vo->html
			);
		}
		finally {
			$this->emailFilters( false );
			$this->plainTextBody = null;
			$this->resetPhpMailer();
		}
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
			add_action( 'phpmailer_init', [ $this, 'setMailAltBody' ], 100 );
		}
		else {
			remove_filter( 'wp_mail_from', [ $this, 'setMailFrom' ], 100 );
			remove_filter( 'wp_mail_from_name', [ $this, 'setMailFromName' ], 100 );
			remove_filter( 'wp_mail_content_type', [ $this, 'setMailContentType' ], 100 );
			remove_action( 'phpmailer_init', [ $this, 'setMailAltBody' ], 100 );
		}
	}

	public function setMailAltBody( $phpmailer ) :void {
		if ( \is_object( $phpmailer ) && $this->plainTextBody !== null && $this->plainTextBody !== '' ) {
			$phpmailer->AltBody = $this->plainTextBody;
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
	 * @deprecated 21.2.0 Use sendVO()
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

	private function buildPlainTextBody( EmailVO $vo ) :string {
		if ( $vo->text !== '' ) {
			return $vo->text;
		}

		return ( new ConvertHtmlToText() )->run( $vo->html );
	}

	private function resetPhpMailer() :void {
		global $phpmailer;
		$phpmailer = null;
	}
}
