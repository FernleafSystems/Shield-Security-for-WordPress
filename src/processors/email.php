<?php

if ( class_exists( 'ICWP_WPSF_Processor_Email', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Email extends ICWP_WPSF_Processor_BaseWpsf {

	const Slug = 'email';

	protected $m_sRecipientAddress;

	/**
	 * @var string
	 */
	static protected $sModeFile_EmailThrottled;

	/**
	 * @var int
	 */
	static protected $nThrottleInterval = 1;

	/**
	 * @var int
	 */
	protected $m_nEmailThrottleLimit;

	/**
	 * @var int
	 */
	protected $m_nEmailThrottleTime;

	/**
	 * @var int
	 */
	protected $m_nEmailThrottleCount;

	/**
	 * @var boolean
	 */
	protected $fEmailIsThrottled;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Email $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Email $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
	}

	public function init() {
		parent::init();
		self::$sModeFile_EmailThrottled = dirname( __FILE__ ).'/../mode.email_throttled';
	}

	public function run() {
	}

	/**
	 * @return array
	 */
	protected function getEmailHeader() {
		return array(
			_wpsf__( 'Hi !' ),
			'',
		);
	}

	/**
	 * @return array
	 */
	protected function getEmailFooter() {
		$oWp = $this->loadWp();
		$sUrl = array(
			'',
			sprintf( _wpsf__( 'This email was sent from the %s Plugin v%s, on %s.' ),
				$this->getController()->getHumanName(),
				$this->getController()->getVersion(),
				$this->loadWp()->getHomeUrl()
			),
			_wpsf__( 'Note: delays in receiving emails are caused by your website hosting and email providers.' ),
			sprintf( _wpsf__( 'Time Sent: %s' ), $oWp->getTimeStampForDisplay( time() ) )
			//				sprintf( '<a href="%s"><strong>%s</strong></a>', 'http://icwp.io/shieldicontrolwpemailfooter', 'iControlWP - WordPress Management and Backup Protection For Professionals' )
		);

		return apply_filters( 'icwp_shield_email_footer', $sUrl );
	}

	/**
	 * @param string $sEmailAddress
	 * @param string $sEmailSubject
	 * @param array  $aMessage
	 * @return boolean
	 * @uses wp_mail
	 */
	public function sendEmailTo( $sEmailAddress = '', $sEmailSubject = '', $aMessage = array() ) {

		// Add our filters for From.
		add_filter( 'wp_mail_from', array( $this, 'setMailFrom' ), 100 );
		add_filter( 'wp_mail_from_name', array( $this, 'setMailFromName' ), 100 );

		$sEmailTo = $this->verifyEmailAddress( $sEmailAddress );

		$this->updateEmailThrottle();
		// We make it appear to have "succeeded" if the throttle is applied.
		if ( $this->fEmailIsThrottled ) {
			return true;
		}

		$aMessage = array_merge( $this->getEmailHeader(), $aMessage, $this->getEmailFooter() );

		add_filter( 'wp_mail_content_type', array( $this, 'setMailContentType' ), 100, 0 );
		$bSuccess = wp_mail( $sEmailTo, $sEmailSubject, '<html>'.implode( "<br />", $aMessage ).'</html>' );

		// Remove our Filters for From
		remove_filter( 'wp_mail_from', array( $this, 'setMailFrom' ), 100 );
		remove_filter( 'wp_mail_from_name', array( $this, 'setMailFromName' ), 100 );
		remove_filter( 'wp_mail_content_type', array( $this, 'setMailContentType' ), 100 );

		return $bSuccess;
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
		$oDP = $this->loadDataProcessor();
		$sProposedFrom = apply_filters( 'icwp_shield_from_email', '' );
		if ( $oDP->validEmail( $sProposedFrom ) ) {
			$sFrom = $sProposedFrom;
		}
		// We help out by trying to correct any funky "from" addresses
		// So, at the very least, we don't fail on this for our emails.
		if ( !$oDP->validEmail( $sFrom ) ) {
			$aUrlParts = @parse_url( $this->loadWp()->getWpUrl() );
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
			$sFromName = sprintf( '%s - %s', $this->getSiteName(), $this->getController()->getHumanName() );
		}
		return $sFromName;
	}

	/**
	 * Will send email to the default recipient setup in the object.
	 * @param string $sEmailSubject
	 * @param array  $aMessage
	 * @return boolean
	 */
	public function sendEmail( $sEmailSubject, $aMessage ) {
		return $this->sendEmailTo( null, $sEmailSubject, $aMessage );
	}

	/**
	 * Whether we're throttled is dependent on 2 signals.  The time interval has changed, or the there's a file
	 * system object telling us we're throttled.
	 * The file system object takes precedence.
	 * @return boolean
	 */
	protected function updateEmailThrottle() {

		// Throttling Is Effectively Off
		if ( $this->getThrottleLimit() <= 0 ) {
			$this->setThrottledFile( false );
			return $this->fEmailIsThrottled;
		}

		// Check that there is an email throttle file. If it exists and its modified time is greater than the 
		// current $this->m_nEmailThrottleTime it suggests another process has touched the file and updated it
		// concurrently. So, we update our $this->m_nEmailThrottleTime accordingly.
		if ( is_file( self::$sModeFile_EmailThrottled ) ) {
			$nModifiedTime = filemtime( self::$sModeFile_EmailThrottled );
			if ( $nModifiedTime > $this->m_nEmailThrottleTime ) {
				$this->m_nEmailThrottleTime = $nModifiedTime;
			}
		}

		if ( !isset( $this->m_nEmailThrottleTime ) || $this->m_nEmailThrottleTime > $this->time() ) {
			$this->m_nEmailThrottleTime = $this->time();
		}
		if ( !isset( $this->m_nEmailThrottleCount ) ) {
			$this->m_nEmailThrottleCount = 0;
		}

		// If $nNow is greater than throttle interval (1s) we turn off the file throttle and reset the count
		$nDiff = $this->time() - $this->m_nEmailThrottleTime;
		if ( $nDiff > self::$nThrottleInterval ) {
			$this->m_nEmailThrottleTime = $this->time();
			$this->m_nEmailThrottleCount = 1;    //we set to 1 assuming that this was called because we're about to send, or have just sent, an email.
			$this->setThrottledFile( false );
		}
		else if ( is_file( self::$sModeFile_EmailThrottled ) || ( $this->m_nEmailThrottleCount >= $this->getThrottleLimit() ) ) {
			$this->setThrottledFile( true );
		}
		else {
			$this->m_nEmailThrottleCount++;
		}
	}

	public function setThrottledFile( $infOn = false ) {

		$this->fEmailIsThrottled = $infOn;

		if ( $infOn && !is_file( self::$sModeFile_EmailThrottled ) && function_exists( 'touch' ) ) {
			@touch( self::$sModeFile_EmailThrottled );
		}
		else if ( !$infOn && is_file( self::$sModeFile_EmailThrottled ) ) {
			@unlink( self::$sModeFile_EmailThrottled );
		}
	}

	public function setDefaultRecipientAddress( $insEmailAddress ) {
		$this->m_sRecipientAddress = $insEmailAddress;
	}

	/**
	 * @param string $sEmailAddress
	 * @return string
	 */
	public function verifyEmailAddress( $sEmailAddress = '' ) {
		return $this->loadDataProcessor()
					->validEmail( $sEmailAddress ) ? $sEmailAddress : $this->getPluginDefaultRecipientAddress();
	}

	/**
	 * @return string
	 */
	public function getSiteName() {
		return $this->loadWp()->getSiteName();
	}

	public function getThrottleLimit() {
		if ( empty( $this->m_nEmailThrottleLimit ) ) {
			$this->m_nEmailThrottleLimit = $this->getOption( 'send_email_throttle_limit' );
		}
		return $this->m_nEmailThrottleLimit;
	}
}