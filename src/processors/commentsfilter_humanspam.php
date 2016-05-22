<?php

if ( !class_exists( 'ICWP_WPSF_Processor_CommentsFilter_HumanSpam', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_commentsfilter.php' );

class ICWP_WPSF_Processor_CommentsFilter_HumanSpam extends ICWP_WPSF_Processor_CommentsFilter_Base {

	const Spam_Blacklist_Source = 'https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt';

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		add_filter( $oFO->doPluginPrefix( 'if-do-comments-check' ), array( $this, 'getIfDoCommentsCheck' ) );
	}

	/**
	 * @param bool $fIfDoCheck
	 *
	 * @return bool
	 */
	public function getIfDoCommentsCheck( $fIfDoCheck ) {
		if ( !$fIfDoCheck ) {
			return $fIfDoCheck;
		}

		$oWpComments = $this->loadWpCommentsProcessor();

		// 1st are comments enabled on this post?
		$nPostId = $this->getRawCommentData( 'comment_post_ID' );
		$oPost = $nPostId ? $this->loadWpFunctionsProcessor()->getPostById( $nPostId ) : null;
		if ( $oPost ) {
			$fIfDoCheck = $oWpComments->isCommentsOpen( $oPost );
		}

		if ( $fIfDoCheck && $oWpComments->getIfCommentsMustBePreviouslyApproved()
			&& $oWpComments->isCommentAuthorPreviouslyApproved( $this->getRawCommentData( 'comment_author_email' ) ) ) {
			$fIfDoCheck = false;
		}

		return $fIfDoCheck;
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
		parent::doCommentChecking( $aCommentData );

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		if ( !$oFO->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$this->doBlacklistSpamCheck( $aCommentData );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( self::$sCommentStatus == 'reject' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->doRedirect( $oWp->getHomeUrl(), array(), true, false );
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

		$sCurrentStatus = $this->getStatus();
		// Check that we haven't already marked the comment through another scan, say GASP
		if ( !empty( $sCurrentStatus ) || !$this->getIsOption('enable_comments_human_spam_filter', 'Y') ) {
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
					$this->setCommentStatus( $this->getOption( 'comments_default_action_human_spam' ) );
					$this->setCommentStatusExplanation( sprintf( _wpsf__( 'Human SPAM filter found "%s" in "%s"' ), $sWord, $sKey ) );

					// We now black mark this IP
					add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
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
		$sBLFile = $this->getSpamBlacklistFile();

		// first, does the file exist? If not import
		if ( !$oFs->exists( $sBLFile ) ) {
			$this->doSpamBlacklistImport();
		}
		// second, if it exists and it's older than 48hrs, update
		else if ( $this->time() - $oFs->getModifiedTime( $sBLFile ) > ( DAY_IN_SECONDS * 2 ) ) {
			$this->doSpamBlacklistUpdate();
		}

		$sList = $oFs->getFileContent( $sBLFile );
		return empty($sList)? '' : $sList;
	}

	/**
	 */
	protected function doSpamBlacklistUpdate() {
		$oFs = $this->loadFileSystemProcessor();
		$oFs->deleteFile( $this->getSpamBlacklistFile() );
		$this->doSpamBlacklistImport();
	}

	/**
	 */
	protected function doSpamBlacklistImport() {
		$oFs = $this->loadFileSystemProcessor();
		$sBLFile = $this->getSpamBlacklistFile();
		if ( !$oFs->exists( $sBLFile ) ) {

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
			$oFs->putFileContent( $sBLFile, $sList );
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
	 * @return string
	 */
	protected function getSpamBlacklistFile() {
		return $this->getFeatureOptions()->getResourcesDir() . 'spamblacklist.txt';
	}
}
endif;