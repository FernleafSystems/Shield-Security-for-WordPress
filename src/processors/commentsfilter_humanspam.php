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

		$aItemsToCheck = array_intersect_key(
			[
				'comment_content' => $sComment,
				'url'             => $sUrl,
				'author_name'     => $sAuthor,
				'author_email'    => $sEmail,
				'ip_address'      => $sUserIp,
				'user_agent'      => $sUserAgent
			],
			array_flip( $oFO->getHumanSpamFilterItems() )
		);

		foreach ( $this->getSpamBlacklist() as $sBlacklistWord ) {
			foreach ( $aItemsToCheck as $sKey => $sItem ) {
				if ( stripos( $sItem, $sBlacklistWord ) !== false ) { //mark as spam and exit;
					$this->doStatIncrement( sprintf( 'spam.human.%s', $sKey ) );
					$this->setCommentStatus( $this->getOption( 'comments_default_action_human_spam' ) );
					$this->setCommentStatusExplanation( sprintf( __( 'Human SPAM filter found "%s" in "%s"', 'wp-simple-firewall' ), $sBlacklistWord, $sKey ) );

					$this->getCon()->fireEvent( 'spam_block_human' );
					$oFO->setOptInsightsAt( 'last_comment_block_at' )
						->setIpTransgressed();
					break 2;
				}
			}
		}
	}

	/**
	 * @return string[]
	 */
	private function getSpamBlacklist() {
		$aList = [];
		$oFs = Services::WpFs();
		$sBLFile = $this->getSpamBlacklistFile();

		// Download if doesn't exist or expired.
		if ( !$oFs->exists( $sBLFile ) || ( $this->time() - $oFs->getModifiedTime( $sBLFile ) > WEEK_IN_SECONDS ) ) {
			Services::WpFs()->deleteFile( $this->getSpamBlacklistFile() );
			$this->importBlacklist();
		}

		if ( $oFs->exists( $sBLFile ) ) {
			$sList = $oFs->getFileContent( $sBLFile );
			if ( !empty( $sList ) ) {
				$aList = array_map( 'base64_decode', explode( "\n", $sList ) );
			}
		}
		return $aList;
	}

	/**
	 */
	private function importBlacklist() {
		$oFs = Services::WpFs();
		$sBLFile = $this->getSpamBlacklistFile();
		if ( !$oFs->exists( $sBLFile ) ) {
			$sRawList = Services::HttpRequest()->getContent( $this->getMod()->getDef( 'url_spam_blacklist_terms' ) );
			$sList = '';
			if ( !empty( $sRawList ) ) {
				$sList = implode( "\n", array_map( 'base64_encode', array_filter( array_map( 'trim', explode( "\n", $sRawList ) ) ) ) );
			}
			// save the list to disk for the future.
			$oFs->putFileContent( $sBLFile, $sList );
		}
	}

	/**
	 * @return string
	 */
	private function getSpamBlacklistFile() {
		return $this->getCon()->getPluginCachePath( 'spamblacklist.txt' );
	}
}