<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Human {

	use ModConsumer;

	/**
	 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher
	 * performance. It also uses defined options for which fields are checked for SPAM instead of just checking
	 * EVERYTHING!
	 * @param array $aCommData
	 * @return \WP_Error|true
	 */
	public function scan( $aCommData ) {
		/** @var CommentsFilter\Options $oOpts */
		$oOpts = $this->getOptions();

		$aItemsToCheck = array_intersect_key(
			[
				'comment_content' => $aCommData[ 'comment_content' ],
				'url'             => $aCommData[ 'comment_author_url' ],
				'author_name'     => $aCommData[ 'comment_author' ],
				'author_email'    => $aCommData[ 'comment_author_email' ],
				'ip_address'      => Services::IP()->getRequestIp(),
				'user_agent'      => substr( Services::Request()->getUserAgent(), 0, 254 )
			],
			array_flip( $oOpts->getHumanSpamFilterItems() )
		);

		$mResult = true;
		foreach ( $this->getSpamBlacklist() as $sBlacklistWord ) {
			foreach ( $aItemsToCheck as $sKey => $sItem ) {
				if ( stripos( $sItem, $sBlacklistWord ) !== false ) { //mark as spam and exit;
					$mResult = new \WP_Error(
						'human',
						sprintf( __( 'Human SPAM filter found "%s" in "%s"', 'wp-simple-firewall' ),
							$sBlacklistWord, $sKey ),
						[
							'word' => $sBlacklistWord,
							'key'  => $sKey
						]
					);
					break 2;
				}
			}
		}

		return $mResult;
	}

	/**
	 * @return string[]
	 */
	private function getSpamBlacklist() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		$aList = [];
		$oFs = Services::WpFs();
		$sBLFile = $oMod->getSpamBlacklistFile();

		// Download if doesn't exist or expired.
		if ( !$oFs->exists( $sBLFile )
			 || ( Services::Request()->ts() - $oFs->getModifiedTime( $sBLFile ) > WEEK_IN_SECONDS ) ) {
			Services::WpFs()->deleteFile( $sBLFile );
			$this->importBlacklist();
		}

		if ( $oFs->exists( $sBLFile ) ) {
			$sList = $oFs->getFileContent( $sBLFile, true );
			if ( !empty( $sList ) ) {
				$aList = array_map( 'base64_decode', explode( "\n", $sList ) );
			}
		}
		return $aList;
	}

	/**
	 */
	private function importBlacklist() {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		$oFs = Services::WpFs();
		$sBLFile = $oMod->getSpamBlacklistFile();
		if ( !$oFs->exists( $sBLFile ) ) {
			$sRawList = Services::HttpRequest()->getContent( $this->getOptions()
																  ->getDef( 'url_spam_blacklist_terms' ) );
			$sList = '';
			if ( !empty( $sRawList ) ) {
				$sList = implode( "\n", array_map( 'base64_encode', array_filter( array_map( 'trim', explode( "\n", $sRawList ) ) ) ) );
			}
			// save the list to disk for the future.
			$oFs->putFileContent( $sBLFile, $sList, true );
		}
	}
}
