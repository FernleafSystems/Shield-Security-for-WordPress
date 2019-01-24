<?php

class ICWP_WPSF_WpComments extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpComments
	 */
	protected static $oInstance = null;

	private function __construct() {}

	/**
	 * @return ICWP_WPSF_WpComments
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @return bool
	 */
	public function getIfAllowCommentsByPreviouslyApproved() {
		return ( $this->loadWp()->getOption( 'comment_whitelist' ) == 1 );
	}

	/**
	 * @param WP_Post|null $oPost - queries the current post if null
	 * @return bool
	 */
	public function isCommentsOpen( $oPost = null ) {
		if ( is_null( $oPost ) || !is_a( $oPost, 'WP_Post' ) ) {
			global $post;
			$oPost = $post;
		}
		return ( is_a( $oPost, 'WP_Post' ) ? ( $oPost->comment_status == 'open' ) : $this->isCommentsOpenByDefault() );
	}

	/**
	 * @return bool
	 */
	public function isCommentsOpenByDefault() {
		return ( $this->loadWp()->getOption( 'default_comment_status' ) == 'open' );
	}

	/**
	 * @param string $sAuthorEmail
	 * @return bool
	 */
	public function isAuthorApproved( $sAuthorEmail ) {

		if ( !$this->loadDP()->validEmail( $sAuthorEmail ) ) {
			return false;
		}

		$oDb = $this->loadDbProcessor();
		$sQuery = "
				SELECT comment_approved
				FROM %s
				WHERE
					comment_author_email = '%s'
					AND comment_approved = '1'
					LIMIT 1
			";

		$sQuery = sprintf(
			$sQuery,
			$oDb->getTable_Comments(),
			esc_sql( $sAuthorEmail )
		);
		return $oDb->getVar( $sQuery ) == 1;
	}

	/**
	 * @return bool
	 */
	public function isCommentPost() {
		return $this->loadRequest()->isMethodPost() && $this->loadWp()->isCurrentPage( 'wp-comments-post.php' );
	}
}