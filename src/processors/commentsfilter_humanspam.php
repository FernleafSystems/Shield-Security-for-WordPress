<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter_HumanSpam extends ICWP_WPSF_Processor_CommentsFilter_Base {

	/**
	 * @param array $aCommData
	 * @return array
	 */
	public function doCommentChecking( $aCommData ) {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		if ( $oFO->getIfDoCommentsCheck( $aCommData[ 'comment_post_ID' ], $aCommData[ 'comment_author_email' ] ) ) {

			$this->performBlacklistSpamCheck(
				$aCommData[ 'comment_author' ],
				$aCommData[ 'comment_author_email' ],
				$aCommData[ 'comment_author_url' ],
				$aCommData[ 'comment_content' ],
				$this->ip(),
				substr( Services::Request()->getUserAgent(), 0, 254 )
			);

			// Now we check whether comment status is to completely reject and then we simply redirect to "home"
			if ( self::$sCommentStatus == 'reject' ) {
				Services::Response()->redirectToHome();
			}
		}

		return $aCommData;
	}

	/**
	 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher
	 * performance. It also uses defined options for which fields are checked for SPAM instead of just checking
	 * EVERYTHING!
	 * @param string $sAuthor
	 * @param string $sEmail
	 * @param string $sUrl
	 * @param string $sComment
	 * @param string $sUserIp
	 * @param string $sUserAgent
	 */
	public function performBlacklistSpamCheck( $sAuthor, $sEmail, $sUrl, $sComment, $sUserIp, $sUserAgent ) {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		$sCurrentStatus = $this->getStatus();
		// Check that we haven't already marked the comment through another scan, say GASP
		if ( !empty( $sCurrentStatus ) ) {
			return;
		}
		// read the file of spam words
		$sSpamWords = $this->getSpamBlacklist();
		if ( empty( $sSpamWords ) ) {
			return;
		}
		$aWords = explode( "\n", $sSpamWords );

		$aItemsMap = array(
			'comment_content' => $sComment,
			'url'             => $sUrl,
			'author_name'     => $sAuthor,
			'author_email'    => $sEmail,
			'ip_address'      => $sUserIp,
			'user_agent'      => $sUserAgent
		);
		$aDesiredItemsToCheck = $this->getOption( 'enable_comments_human_spam_filter_items' );
		$aItemsToCheck = [];
		foreach ( $aDesiredItemsToCheck as $sKey ) {
			$aItemsToCheck[ $sKey ] = $aItemsMap[ $sKey ];
		}

		foreach ( $aItemsToCheck as $sKey => $sItem ) {
			foreach ( $aWords as $sWord ) {
				if ( stripos( $sItem, $sWord ) !== false ) {
					//mark as spam and exit;
					$this->doStatIncrement( sprintf( 'spam.human.%s', $sKey ) );
					$this->setCommentStatus( $this->getOption( 'comments_default_action_human_spam' ) );
					$this->setCommentStatusExplanation( sprintf( _wpsf__( 'Human SPAM filter found "%s" in "%s"' ), $sWord, $sKey ) );
					$oFO->setOptInsightsAt( 'last_comment_block_at' )
						->setIpTransgressed();
					break 2;
				}
			}
		}
	}

	/**
	 * @return null|string
	 */
	protected function getSpamBlacklist() {
		$oFs = $this->loadFS();
		$sBLFile = $this->getSpamBlacklistFile();

		// first, does the file exist? If not import
		if ( !$oFs->exists( $sBLFile ) || ( $this->time() - $oFs->getModifiedTime( $sBLFile ) > ( DAY_IN_SECONDS*2 ) ) ) {
			$this->doSpamBlacklistUpdate();
		}
		return $this->readSpamList();
	}

	/**
	 * @return string
	 */
	protected function readSpamList() {
		$oFs = $this->loadFS();
		$sBLFile = $this->getSpamBlacklistFile();
		if ( $oFs->exists( $sBLFile ) ) {
			$sList = $oFs->getFileContent( $sBLFile );
			if ( !empty( $sList ) ) {
				return implode( "\n", array_map( 'base64_decode', explode( "\n", $sList ) ) );
			}
		}
		return '';
	}

	/**
	 */
	protected function doSpamBlacklistUpdate() {
		Services::WpFs()->deleteFile( $this->getSpamBlacklistFile() );
		$this->doSpamBlacklistImport();
	}

	/**
	 */
	protected function doSpamBlacklistImport() {
		$oFs = $this->loadFS();
		$sBLFile = $this->getSpamBlacklistFile();
		if ( !$oFs->exists( $sBLFile ) ) {

			$sRawList = $this->doSpamBlacklistDownload();

			if ( empty( $sRawList ) ) {
				$sList = '';
			}
			else {
				// filter out empty lines
				$aWords = explode( "\n", $sRawList );
				foreach ( $aWords as $nIndex => $sWord ) {
					$sWord = trim( $sWord );
					if ( empty( $sWord ) ) {
						unset( $aWords[ $nIndex ] );
					}
					else {
						$aWords[ $nIndex ] = base64_encode( $sWord );
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
		return Services::HttpRequest()->getContent( $this->getMod()->getDef( 'url_spam_blacklist_terms' ) );
	}

	/**
	 * @return string
	 */
	protected function getSpamBlacklistFile() {
		return $this->getCon()->getPath_Assets( 'spamblacklist.txt' );
	}
}