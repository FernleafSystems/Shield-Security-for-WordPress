<?php

if ( !class_exists( 'ICWP_WPSF_Processor_CommentsFilter_HumanSpam', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_CommentsFilter_HumanSpam extends ICWP_WPSF_Processor_Base {

	const Spam_Blacklist_Source = 'https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt';

	const TWODAYS = 172800;

	/**
	 * @var array
	 */
	private $aRawCommentData;

	/**
	 * @var string
	 */
	static protected $sSpamBlacklistFile;

	/**
	 * @var string
	 */
	protected $sCommentStatus = '';

	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation = '';

	/**
	 * @param bool $fIfDoCheck
	 *
	 * @return bool
	 */
	public function getIfDoCommentsCheck( $fIfDoCheck ) {
		if ( !$fIfDoCheck ) {
			return $fIfDoCheck;
		}

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $oWp->comments_getIfCommentAuthorPreviouslyApproved( $this->getRawCommentData( 'comment_author_email' ) ) ) {
			return false;
		}
		return $fIfDoCheck;
	}

	/**
	 * @param string $sKey
	 *
	 * @return array|mixed
	 */
	public function getRawCommentData( $sKey = '' ) {
		if ( !isset( $this->aRawCommentData ) ) {
			$this->aRawCommentData = array();
		}
		if ( !empty( $sKey ) && isset( $this->aRawCommentData[$sKey] ) ) {
			return $this->aRawCommentData[$sKey];
		}
		return $this->aRawCommentData;
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->sCommentStatus = '';
		$this->sCommentStatusExplanation = '';
		self::$sSpamBlacklistFile = $this->getFeatureOptions()->getResourcesDir().'spamblacklist.txt';
	}
	
	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		add_filter( 'preprocess_comment', array( $this, 'doCommentChecking' ), 1, 1 );
		add_filter( $oFO->doPluginPrefix( 'if-do-comments-check' ), array( $this, 'getIfDoCommentsCheck' ) );
		add_filter( $oFO->doPluginPrefix( 'comments_filter_status' ), array( $this, 'getCommentStatus' ), 2 );
		add_filter( $oFO->doPluginPrefix( 'comments_filter_status_explanation' ), array( $this, 'getCommentStatusExplanation' ), 2 );
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status.
	 *
	 * @param $sCurrentCommentStatus
	 * @return string
	 */
	public function getCommentStatus( $sCurrentCommentStatus ) {
		return empty( $sCurrentCommentStatus )? $this->sCommentStatus : $sCurrentCommentStatus;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status explanation
	 *
	 * @param $sCurrentCommentStatusExplanation
	 * @return string
	 */
	public function getCommentStatusExplanation( $sCurrentCommentStatusExplanation ) {
		return empty( $sCurrentCommentStatusExplanation )? $this->sCommentStatusExplanation : $sCurrentCommentStatusExplanation;
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
		$this->aRawCommentData = $aCommentData;

		if ( !apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'if-do-comments-check' ), true ) ) {
			return $aCommentData;
		}

		$this->doBlacklistSpamCheck( $aCommentData );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( $this->sCommentStatus == 'reject' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->redirectToHome();
		}

		return $aCommentData;
	}

	/**
	 * @param $aCommentData
	 */
	protected function doBlacklistSpamCheck( $aCommentData ) {
		$oDp = $this->loadDataProcessor();
		$this->doBlacklistSpamCheck_Action(
			$aCommentData['comment_author'],
			$aCommentData['comment_author_email'],
			$aCommentData['comment_author_url'],
			$aCommentData['comment_content'],
			$oDp->getVisitorIpAddress( true ),
			substr( $oDp->FetchServer( 'HTTP_USER_AGENT', '' ), 0, 254 )
		);
	}

	/**
	 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher performance.
	 *
	 * It also uses defined options for which fields are checked for SPAM instead of just checking EVERYTHING!
	 *
	 * @param string $sAuthor
	 * @param string $sEmail
	 * @param string $sUrl
	 * @param string $sComment
	 * @param string $sUserIp
	 * @param string $sUserAgent
	 */
	public function doBlacklistSpamCheck_Action( $sAuthor, $sEmail, $sUrl, $sComment, $sUserIp, $sUserAgent ) {

		// Check that we haven't already marked the comment through another scan, say GASP
		if ( !empty( $this->sCommentStatus ) || !$this->getIsOption('enable_comments_human_spam_filter', 'Y') ) {
			return;
		}

		// read the file of spam words
		$sSpamWords = $this->getSpamBlacklist();
		if ( empty($sSpamWords) ) {
			return;
		}
		$aWords = explode( "\n", $sSpamWords );

		$aItemsMap = array(
			'comment_content'	=> $sComment,
			'url'				=> $sUrl,
			'author_name'		=> $sAuthor,
			'author_email'		=> $sEmail,
			'ip_address'		=> $sUserIp,
			'user_agent'		=> $sUserAgent
		);
		$aDesiredItemsToCheck = $this->getOption('enable_comments_human_spam_filter_items');
		$aItemsToCheck = array();
		foreach( $aDesiredItemsToCheck as $sKey ) {
			$aItemsToCheck[$sKey] = $aItemsMap[$sKey];
		}

		foreach( $aItemsToCheck as $sKey => $sItem ) {
			foreach ( $aWords as $sWord ) {
				if ( stripos( $sItem, $sWord ) !== false ) {
					//mark as spam and exit;
					$this->doStatIncrement( sprintf( 'spam.human.%s', $sKey ) );
					$this->doStatHumanSpamWords( $sWord );
					$this->sCommentStatus = $this->getOption( 'comments_default_action_human_spam' );
					$this->setCommentStatusExplanation( sprintf( _wpsf__('Human SPAM filter found "%s" in "%s"' ), $sWord, $sKey ) );
					break 2;
				}
			}
		}
	}

	/**
	 * @param $sStatWord
	 */
	protected function doStatHumanSpamWords( $sStatWord = '' ) {
		$this->loadStatsProcessor();
		if ( !empty( $sStatWord ) ) {
			ICWP_Stats_WPSF::DoStatIncrementKeyValue( 'spam.human.words', base64_encode( $sStatWord ) );
		}
	}

	/**
	 * @return null|string
	 */
	protected function getSpamBlacklist() {
		$oFs = $this->loadFileSystemProcessor();

		// first, does the file exist? If not import
		if ( !$oFs->exists( self::$sSpamBlacklistFile ) ) {
			$this->doSpamBlacklistImport();
		}
		// second, if it exists and it's older than 48hrs, update
		else if ( $this->time() - $oFs->getModifiedTime( self::$sSpamBlacklistFile ) > self::TWODAYS ) {
			$this->doSpamBlacklistUpdate();
		}

		$sList = $oFs->getFileContent( self::$sSpamBlacklistFile );
		return empty($sList)? '' : $sList;
	}

	/**
	 */
	protected function doSpamBlacklistUpdate() {
		$oFs = $this->loadFileSystemProcessor();
		$oFs->deleteFile( self::$sSpamBlacklistFile );
		$this->doSpamBlacklistImport();
	}

	/**
	 */
	protected function doSpamBlacklistImport() {
		$oFs = $this->loadFileSystemProcessor();
		if ( !$oFs->exists( self::$sSpamBlacklistFile ) ) {

			$sRawList = $this->doSpamBlacklistDownload();

			if ( empty($sRawList) ) {
				$sList = '';
			}
			else {
				// filter out empty lines
				$aWords = explode( "\n", $sRawList );
				foreach ( $aWords as $nIndex => $sWord ) {
					$sWord = trim($sWord);
					if ( empty($sWord) ) {
						unset( $aWords[$nIndex] );
					}
				}
				$sList = implode( "\n", $aWords );
			}

			// save the list to disk for the future.
			$oFs->putFileContent( self::$sSpamBlacklistFile, $sList );
		}
	}

	/**
	 * @return string
	 */
	protected function doSpamBlacklistDownload() {
		$oFs = $this->loadFileSystemProcessor();
		return $oFs->getUrlContent( self::Spam_Blacklist_Source );
	}

	/**
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		$this->sCommentStatusExplanation =
			'[* '.sprintf(
				_wpsf__( '%s plugin marked this comment as "%s".' ).' '._wpsf__( 'Reason: %s' ),
				$this->getController()->getHumanName(),
				( $this->sCommentStatus == 0 ) ? _wpsf__('pending') : $this->sCommentStatus,
				$sExplanation
			)." *]\n";
	}
}
endif;