<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support;

trait LocalEmailCapture {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	private array $capturedMails = [];

	protected function startLocalEmailCapture() :void {
		$this->capturedMails = [];
		add_filter( 'pre_wp_mail', [ $this, 'captureLocalMailAttempt' ], 10, 2 );
	}

	protected function stopLocalEmailCapture() :void {
		remove_filter( 'pre_wp_mail', [ $this, 'captureLocalMailAttempt' ], 10 );
		$this->capturedMails = [];
	}

	/**
	 * @param mixed $pre
	 */
	public function captureLocalMailAttempt( $pre, array $atts ) :bool {
		$phpmailer = (object)[
			'Body'        => (string)( $atts[ 'message' ] ?? '' ),
			'AltBody'     => '',
			'ContentType' => (string)\apply_filters( 'wp_mail_content_type', 'text/plain' ),
			'Subject'     => (string)( $atts[ 'subject' ] ?? '' ),
			'to'          => $atts[ 'to' ] ?? [],
		];

		\do_action( 'phpmailer_init', $phpmailer );

		$this->capturedMails[] = [
			'atts'       => $atts,
			'phpmailer'  => $phpmailer,
			'alt_body'   => (string)( $phpmailer->AltBody ?? '' ),
			'html_body'  => (string)( $phpmailer->Body ?? '' ),
			'to'         => $atts[ 'to' ] ?? [],
			'subject'    => (string)( $atts[ 'subject' ] ?? '' ),
			'content_ty' => (string)( $phpmailer->ContentType ?? '' ),
		];

		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function lastCapturedMail() :array {
		$this->assertNotEmpty( $this->capturedMails, 'Expected at least one intercepted local mail.' );
		return $this->capturedMails[ \count( $this->capturedMails ) - 1 ];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function capturedMails() :array {
		return $this->capturedMails;
	}
}
