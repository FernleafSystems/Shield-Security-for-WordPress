<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( 'icwp-processor-basedb.php' );

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_V1') ):

	class ICWP_WPSF_Processor_AuditTrail_V1 extends ICWP_WPSF_BaseDbProcessor {

		/**
		 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions, $oFeatureOptions->getAuditTrailTableName() );
		}

		public function action_doFeatureProcessorShutdown () {
			if ( ! $this->getFeatureOptions()->getIsPluginDeleting() ) {
				$this->commitAuditTrial();
			}
		}

		/**
		 */
		public function run() {

			/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFo */
			$oFo = $this->getFeatureOptions();

			if ( $this->getIsOption( 'enable_audit_context_users', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_users.php' );
				$oUsers = new ICWP_WPSF_Processor_AuditTrail_Users( $oFo );
				$oUsers->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_plugins', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_plugins.php' );
				$oPlugins = new ICWP_WPSF_Processor_AuditTrail_Plugins( $oFo );
				$oPlugins->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_themes', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_themes.php' );
				$oThemes = new ICWP_WPSF_Processor_AuditTrail_Themes( $oFo );
				$oThemes->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_wordpress.php' );
				$oWp = new ICWP_WPSF_Processor_AuditTrail_Wordpress( $oFo );
				$oWp->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_posts', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_posts.php' );
				$oPosts = new ICWP_WPSF_Processor_AuditTrail_Posts( $oFo );
				$oPosts->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_emails', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_emails.php' );
				$oEmails = new ICWP_WPSF_Processor_AuditTrail_Emails( $oFo );
				$oEmails->run();
			}

			if ( $this->getIsOption( 'enable_audit_context_wpsf', 'Y' ) ) {
				require_once( 'icwp-processor-audit_trail_wpsf.php' );
				$oWpsf = new ICWP_WPSF_Processor_AuditTrail_Wpsf( $oFo );
				$oWpsf->run();
			}
		}

		/**
		 * @return array|bool
		 */
		public function getAllAuditEntries() {
			return array_reverse( $this->selectAllRows() );
		}

		/**
		 * @param string $sContext
		 * @param int $nLimit
		 * @return array|bool
		 */
		public function getAuditEntriesForContext( $sContext, $nLimit = 50 ) {
			$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`context`			= '%s'
					AND `deleted_at`	= '0'
				ORDER BY `created_at` DESC
				LIMIT %s
			";
			$sQuery = sprintf( $sQuery, $this->getTableName(), $sContext, $nLimit );
			return $this->selectCustom( $sQuery );
		}

		/**
		 */
		protected function commitAuditTrial() {
			$aEntries = $this->getAuditTrailEntries()->getAuditTrailEntries( true );
			$aEntries = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'wpsf_audit_trail_gather' ), $aEntries );
			if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
				return;
			}

			$sIp = $this->loadDataProcessor()->getVisitorIpAddress( true );
			foreach( $aEntries as $aEntry ) {
				if ( empty( $aEntry['ip'] ) ) {
					$aEntry['ip'] = $sIp;
				}
				if ( is_array( $aEntry['message'] ) ) {
					$aEntry['message'] = implode( ' ', $aEntry['message'] );
				}
				$this->insertData( $aEntry );
			}
		}

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			return ICWP_WPSF_AuditTrail_Entries::GetInstance();
		}

		/**
		 * @return string
		 */
		protected function getCreateTableSql() {
			$sSqlTables = "
				CREATE TABLE IF NOT EXISTS `%s` (
				`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`ip` VARCHAR(40) NOT NULL DEFAULT '0',
				`wp_username` VARCHAR(255) NOT NULL DEFAULT 'none',
				`context` VARCHAR(32) NOT NULL DEFAULT 'none',
				`event` VARCHAR(50) NOT NULL DEFAULT 'none',
				`category` INT(3) UNSIGNED NOT NULL DEFAULT '0',
				`message` TEXT,
				`immutable` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
				`created_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
				`deleted_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

			return sprintf( $sSqlTables, $this->getTableName() );
		}

		/**
		 * @return array
		 */
		protected function getTableColumnsByDefinition() {
			return $this->getOption( 'audit_trail_table_columns' );
		}

		/**
		 * This is hooked into a cron in the base class and overrides the parent method.
		 *
		 * It'll delete everything older than 30 days.
		 */
		public function cleanupDatabase() {
			$nDays = $this->getOption( 'audit_trail_auto_clean' );
			if ( !$this->getTableExists() || $nDays <= 0 ) {
				return;
			}
			$nTimeStamp = $this->time() - $nDays * DAY_IN_SECONDS;
			$this->deleteAllRowsOlderThan( $nTimeStamp );
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail') ):
	class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_Processor_AuditTrail_V1 { }
endif;

class ICWP_WPSF_AuditTrail_Entries {

	/**
	 * @var ICWP_WPSF_AuditTrail_Entries
	 */
	protected static $oInstance = NULL;

	/**
	 * @return ICWP_WPSF_AuditTrail_Entries
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @var array
	 */
	protected $aEntries;

	public function add( $sContext, $sEvent, $nCategory, $sMessage = '', $sWpUsername = '' ) {
		$oDp = $this->loadDataProcessor();

		if ( empty( $sWpUsername ) ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oCurrentUser = $oWp->getCurrentWpUser();
			$sWpUsername = empty( $oCurrentUser ) ? 'unknown' : $oCurrentUser->get( 'user_login' );
		}

		$aNewEntry = array(
			'ip' => $oDp->getVisitorIpAddress( true ),
			'created_at' => $oDp->GetRequestTime(),
			'wp_username' => $sWpUsername,
			'context' => $sContext,
			'event' => $sEvent,
			'category' => $nCategory,
			'message' => $sMessage
		);
		$aEntries = $this->getAuditTrailEntries();
		$aEntries[] = $aNewEntry;
		$this->aEntries = $aEntries;
	}

	/**
	 * For use inside the object
	 *
	 * @return array
	 */
	protected function & getEntries() {
		if ( !isset( $this->aEntries ) ) {
			$this->aEntries = array();
		}
		return $this->aEntries;
	}

	/**
	 * @param boolean $fFlush
	 * @return array
	 */
	public function getAuditTrailEntries( $fFlush = false ) {
		if ( !isset( $this->aEntries ) ) {
			$this->aEntries = array();
		}
		$aEntries = $this->aEntries;
		if ( $fFlush ) {
			$this->aEntries = array();
		}
		return $aEntries;
	}


	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	public function loadDataProcessor() {
		if ( !class_exists('ICWP_WPSF_DataProcessor') ) {
			require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		}
		return ICWP_WPSF_DataProcessor::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	public function loadFileSystemProcessor() {
		if ( !class_exists('ICWP_WPSF_WpFilesystem') ) {
			require_once( dirname(__FILE__) . '/icwp-wpfilesystem.php' );
		}
		return ICWP_WPSF_WpFilesystem::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	public function loadWpFunctionsProcessor() {
		require_once( dirname(__FILE__) . '/icwp-wpfunctions.php' );
		return ICWP_WPSF_WpFunctions::GetInstance();
	}
}