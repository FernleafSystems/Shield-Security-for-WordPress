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
			return $this->loadDataProcessor()->GetIsRequestPost() && $this->loadWpFunctionsProcessor()->getIsCurrentPage( 'wp-comments-post.php' );
		}
	}

endif;