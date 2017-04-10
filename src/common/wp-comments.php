<?php
if ( !class_exists( 'ICWP_WPSF_WpComments', false ) ):

	class ICWP_WPSF_WpComments extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_WpComments
		 */
		protected static $oInstance = NULL;

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
		public function getIfCommentsMustBePreviouslyApproved() {
			return ( $this->loadWpFunctions()->getOption( 'comment_whitelist' ) == 1 );
		}

		/**
		 * @param WP_Post|null $oPost - queries the current post if null
		 * @return bool
		 */
		public function isCommentsOpen( $oPost = null ) {
			if ( is_null( $oPost ) || !is_a( $oPost, 'WP_Post' )) {
				global $post;
				$oPost = $post;
			}
			return ( is_a( $oPost, 'WP_Post' ) ? ( $oPost->comment_status == 'open' ) : $this->isCommentsOpenByDefault() );
		}

		/**
		 * @return bool
		 */
		public function isCommentsOpenByDefault() {
			return ( $this->loadWpFunctions()->getOption( 'default_comment_status' ) == 'open' );
		}

		/**
		 * @param string $sAuthorEmail
		 * @return bool
		 */
		public function isCommentAuthorPreviouslyApproved( $sAuthorEmail ) {

			if ( empty( $sAuthorEmail ) || !is_email( $sAuthorEmail ) ) {
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
			return $this->loadDataProcessor()->GetIsRequestPost() && $this->loadWpFunctions()->getIsCurrentPage( 'wp-comments-post.php' );
		}
	}

endif;