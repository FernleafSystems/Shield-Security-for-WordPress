<?php

if ( !class_exists('ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam') ):

require_once( dirname(__FILE__).ICWP_DS.'basedb.php' );

class ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * The unique comment token assigned to this page
	 * @var integer
	 */
	protected $sUniqueCommentToken;
	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	protected $sUniqueFormId;
	/**
	 * @var string
	 */
	protected $sCommentStatus;
	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation;

	/**
	 * @var array
	 */
	private $aRawCommentData;

	/**
	 * @param ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_CommentsFilter $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getCommentsFilterTableName() );
	}

	/**
	 * @param bool $fIfDoCheck
	 * @return bool
	 */
	public function getIfDoCommentsCheck( $fIfDoCheck ) {
		if ( !$fIfDoCheck ) {
			return $fIfDoCheck;
		}

		$oWpComments = $this->loadWpCommentsProcessor();
		if ( $oWpComments->getIfCommentsMustBePreviouslyApproved()
			&& $oWpComments->isCommentAuthorPreviouslyApproved( $this->getRawCommentData( 'comment_author_email' ) ) ) {
			$fIfDoCheck = false;
		}

		return $fIfDoCheck;
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->sCommentStatus = '';
		$this->sCommentStatusExplanation = '';
		$this->getUniqueCommentToken(); //ensures the necessary cookie is set early
	}
	
	/**
	 */
	public function run() {
		$this->reset();

		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'if-do-comments-check' ), array( $this, 'getIfDoCommentsCheck' ) );

		// Add GASP checking to the comment form.
		add_action(	'comment_form',					array( $this, 'printGaspFormHook_Action' ), 1 );
		add_action(	'comment_form',					array( $this, 'printGaspFormParts_Action' ), 2 );
		add_filter( 'preprocess_comment',			array( $this, 'doCommentChecking' ), 1, 1 );

		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'comments_filter_status' ), array( $this, 'getCommentStatus' ), 1 );
		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'comments_filter_status_explanation' ), array( $this, 'getCommentStatusExplanation' ), 1 );
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

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		if ( !$oFO->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$this->doGaspCommentCheck( $aCommentData['comment_post_ID'] );

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"
		if ( $this->sCommentStatus == 'reject' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->doRedirect( $oWp->getHomeUrl(), array(), true, false );
		}

		return $aCommentData;
	}

	/**
	 * Performs the actual GASP comment checking
	 *
	 * @param $nPostId
	 */
	protected function doGaspCommentCheck( $nPostId ) {

		if ( !$this->getIfDoGaspCheck() ) {
			return;
		}

		// Check that we haven't already marked the comment through another scan
		if ( !empty( $this->sCommentStatus ) || !$this->getIsOption( 'enable_comments_gasp_protection', 'Y' ) ) {
			return;
		}

		$bIsSpam = true;
		$sStatKey = '';
		$sExplanation = '';

		$oDp = $this->loadDataProcessor();
		$sFieldCheckboxName = $oDp->FetchPost( 'cb_nombre' );
		$sFieldHoney = $oDp->FetchPost( 'sugar_sweet_email' );
		$sFieldCommentToken = $oDp->FetchPost( 'comment_token' );

		// we have the cb name, is it set?
		if( !$sFieldCheckboxName || !$oDp->FetchPost( $sFieldCheckboxName ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('checkbox') );
			$sStatKey = 'checkbox';
		}
		// honeypot check
		else if ( !empty( $sFieldHoney ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('honeypot') );
			$sStatKey = 'honeypot';
		}
		// check the unique comment token is present
		else if ( empty( $sFieldCommentToken ) || !$this->checkCommentToken( $sFieldCommentToken, $nPostId ) ) {
			$sExplanation = sprintf( _wpsf__('Failed GASP Bot Filter Test (%s)' ), _wpsf__('comment token failure') );
			$sStatKey = 'token';
		}
		else {
			$bIsSpam = false;
		}

		if ( $bIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.gasp.%s', $sStatKey ) );
			$this->sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );

			// We now black mark this IP
			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
		}
	}

	/**
	 * @return void
	 */
	public function printGaspFormHook_Action() {
		if ( !$this->getIfDoGaspCheck() ) {
			return;
		}

		$this->deleteOldPostCommentTokens();
		$this->insertUniquePostCommentToken();
		echo $this->getGaspCommentsHookHtml();
	}

	/**
	 * Tells us whether, for this particular comment post, if we should do GASP comments checking.
	 *
	 * @return boolean
	 */
	protected function getIfDoGaspCheck() {

		if ( !$this->getIsOption( 'enable_comments_gasp_protection', 'Y' ) ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			return false;
		}

		// Compatibility with shoutbox WP Wall Plugin
		// http://wordpress.org/plugins/wp-wall/
		if ( function_exists( 'WPWall_Init' ) ) {
			$oDp = $this->loadDataProcessor();
			if ( !is_null( $oDp->FetchPost( 'submit_wall_post' ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return string
	 */
	protected function getUniqueFormId() {
		if ( !isset( $this->sUniqueFormId ) ) {
			$oDp = $this->loadDataProcessor();
			$this->sUniqueFormId = $oDp->GenerateRandomLetter().$oDp->GenerateRandomString( rand(7, 23), 7 );
		}
		return $this->sUniqueFormId;
	}

	/**
	 * @return void
	 */
	public function printGaspFormParts_Action() {
		if ( !$this->getIfDoGaspCheck() ) {
			return;
		}
		echo $this->getGaspCommentsHtml();
	}
	
	/**
	 * @return string
	 */
	protected function getGaspCommentsHookHtml() {
		$sReturn = '<p id="'.$this->getUniqueFormId().'"></p>'; // we use this unique <p> to hook onto using javascript
		$sReturn .= '<input type="hidden" id="_sugar_sweet_email" name="sugar_sweet_email" value="" />';
		$sReturn .= sprintf( '<input type="hidden" id="_comment_token" name="comment_token" value="%s" />', $this->getUniqueCommentToken() );
		return $sReturn;
	}
	
	protected function getGaspCommentsHtml() {

		$sId			= $this->getUniqueFormId();
		$sConfirm		= stripslashes( $this->getOption('custom_message_checkbox') );
		$sAlert			= stripslashes( $this->getOption('custom_message_alert') );
		$sCommentWait	= stripslashes( $this->getOption('custom_message_comment_wait') );
		$nCooldown		= $this->getOption('comments_cooldown_interval');
		$nExpire		= $this->getOption('comments_token_expire_interval');

		$sJsCommentWait = '"'.str_replace( '%s', '"+nRemaining+"', $sCommentWait ).'"';
		$sCommentWait = str_replace( '%s', $nCooldown, $sCommentWait );

		$sCommentReload = $this->getOption('custom_message_comment_reload');

		$sReturn = "
			<script type=\"text/javascript\">
				
				function cb_click$sId() {
					cb_name$sId.value=cb$sId.name;
				}
				function check$sId() {
					if( cb$sId.checked != true ) {
						alert( \"$sAlert\" ); return false;
					}
					return true;
				}
				function reenableButton$sId() {
					nTimerCounter{$sId}++;
					nRemaining = $nCooldown - nTimerCounter$sId;
					subbutton$sId.value	= $sJsCommentWait;
					if ( nTimerCounter$sId >= $nCooldown ) {
						subbutton$sId.value = origButtonValue$sId;
						subbutton$sId.disabled = false;
						clearInterval( sCountdownTimer$sId );
					}
				}
				function redisableButton$sId() {
					subbutton$sId.value		= \"$sCommentReload\";
					subbutton$sId.disabled	= true;
				}
				
				var $sId				= document.getElementById('$sId');

				var cb$sId				= document.createElement('input');
				cb$sId.type				= 'checkbox';
				cb$sId.id				= 'checkbox$sId';
				cb$sId.name				= 'checkbox$sId';
				cb$sId.style.width		= '25px';
				cb$sId.onclick			= cb_click$sId;
			
				var label$sId			= document.createElement( 'label' );
				var labelspan$sId		= document.createElement( 'span' );
				label$sId.htmlFor		= 'checkbox$sId';
				labelspan$sId.innerHTML	= \"$sConfirm\";

				var cb_name$sId			= document.createElement('input');
				cb_name$sId.type		= 'hidden';
				cb_name$sId.name		= 'cb_nombre';

				$sId.appendChild( label$sId );
				label$sId.appendChild( cb$sId );
				label$sId.appendChild( labelspan$sId );
				$sId.appendChild( cb_name$sId );

				var frm$sId					= cb$sId.form;
				frm$sId.onsubmit			= check$sId;

				".(
					( $nCooldown > 0 || $nExpire > 0 ) ?
					"
					var subbuttonList$sId = frm$sId.querySelectorAll( 'input[type=\"submit\"]' );
					
					if ( typeof( subbuttonList$sId ) != \"undefined\" ) {
						subbutton$sId = subbuttonList{$sId}[0];
						if ( typeof( subbutton$sId ) != \"undefined\" ) {
						
						".(
							( $nCooldown > 0 )?
							"
							subbutton$sId.disabled		= true;
							origButtonValue$sId			= subbutton$sId.value;
							subbutton$sId.value			= \"$sCommentWait\";
							nTimerCounter$sId			= 0;
							sCountdownTimer$sId			= setInterval( reenableButton$sId, 1000 );
							"
							:''
						).(
							( $nExpire > 0 )? "sTimeoutTimer$sId			= setTimeout( redisableButton$sId, ".(1000 * $nExpire - 1000)." );" : ''
						)."
						}
					}
					":''
				)."
			</script>
		";
		return $sReturn;
	}

	/**
	 * @param string $sCommentToken
	 * @param $sPostId
	 * @return bool
	 */
	protected function checkCommentToken( $sCommentToken, $sPostId ) {

		$sToken = esc_sql( $sCommentToken ); //just in-case someones tries to get all funky up in it
		
		// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
		$sQuery = "
			SELECT *
				FROM `%s`
			WHERE
				`unique_token`		= '%s'
				AND `post_id`		= '%s'
				AND `ip`			= '%s'
				AND `deleted_at`	= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			$sToken,
			$sPostId,
			$this->loadDataProcessor()->getVisitorIpAddress( true )
		);
		$mResult = $this->selectCustom( $sQuery );

		if ( empty( $mResult ) || !is_array($mResult) || count($mResult) != 1 ) {
			return false;
		}
		else {
			// Only 1 chance is given per token, so we delete it
			$this->deleteUniquePostCommentToken( $sToken, $sPostId );
			
			// Did sufficient time pass, or has it expired?
			$aRecord = $mResult[0];
			$nInterval = $this->time() - $aRecord['created_at'];
			if ( $nInterval < $this->getOption( 'comments_cooldown_interval' )
					|| ( $this->getOption( 'comments_token_expire_interval' ) > 0 && $nInterval > $this->getOption('comments_token_expire_interval') )
				) {
				return false;
			}
			return true;
		}
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		// Set up comments ID table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`post_id` INT(11) NOT NULL DEFAULT '0',
			`unique_token` VARCHAR(32) NOT NULL DEFAULT '',
			`ip` VARCHAR(40) NOT NULL DEFAULT '0',
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
		return $this->getOption( 'spambot_comments_filter_table_columns' );
	}

	/**
	 * @param string $sUniqueToken
	 * @param string $sPostId
	 *
	 * @return bool|int
	 */
	protected function deleteUniquePostCommentToken( $sUniqueToken, $sPostId ) {
		$aWhere = array(
			'unique_token'  => $sUniqueToken,
			'post_id'       => $sPostId
		);
		return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTableName(), $aWhere );
	}

	/**
	 * @param int|null $sPostId
	 *
	 * @return bool|int
	 */
	protected function deleteOldPostCommentTokens( $sPostId = null ) {
		$aWhere = array(
			'ip'        => $this->loadDataProcessor()->getVisitorIpAddress( true ),
			'post_id'   => empty( $sPostId ) ? $this->loadWpFunctionsProcessor()->getCurrentPostId() : $sPostId
		);
		return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTableName(), $aWhere );
	}

	/**
	 * @return bool|int
	 */
	protected function insertUniquePostCommentToken() {
		$aData = array(
			'post_id'       => $this->loadWpFunctionsProcessor()->getCurrentPostId(),
			'unique_token'  => $this->getUniqueCommentToken(),
			'ip'            => $this->loadDataProcessor()->getVisitorIpAddress( true ),
			'created_at'    => $this->time()
		);
		return $this->insertData( $aData );
	}

	/**
	 * @alias $this->getController()->getUniqueRequestId();
	 * @return string
	 */
	protected function getUniqueCommentToken() {
		return $this->getController()->getUniqueRequestId();
	}

	/**
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		$this->sCommentStatusExplanation =
			'[* '.sprintf(
				_wpsf__('%s plugin marked this comment as "%s".').' '._wpsf__( 'Reason: %s' ),
				$this->getController()->getHumanName(),
				$this->sCommentStatus,
				$sExplanation
			)." *]\n";
	}
	
	/**
	 * This is hooked into a cron in the base class and overrides the parent method.
	 * It'll delete everything older than 24hrs.
	 */
	public function cleanupDatabase() {
		if ( !$this->getTableExists() ) {
			return;
		}
		$nTimeStamp = $this->time() - DAY_IN_SECONDS;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}
endif;
