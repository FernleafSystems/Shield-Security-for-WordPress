<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam
 * @deprecated
 */
class ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * The unique comment token assigned to this page
	 * @var string
	 */
	protected $sFormId;

	/**
	 * @param ICWP_WPSF_FeatureHandler_CommentsFilter $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_CommentsFilter $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'spambot_comments_filter_table_name' ) );
	}

	/**
	 */
	public function run() {
		if ( $this->isReadyToRun() && !Services::Request()->isPost() ) {
			// Add GASP checking to the comment form.
			add_action( 'comment_form', [ $this, 'printGaspFormItems' ], 1 );
		}
	}

	public function printGaspFormItems() {
		$oToken = $this->initCommentFormToken();
		echo $this->getGaspCommentsHookHtml( $oToken );
		echo $this->getGaspCommentsHtml();
	}

	/**
	 * @return string
	 */
	protected function initCommentFormToken() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		$nTs = Services::Request()->ts();
		$nPostId = Services::WpPost()->getCurrentPostId();

		$sToken = $this->getToken( $nTs, $nPostId );
		Services::WpGeneral()->setTransient(
			$this->prefix( 'comtok-'.md5( sprintf( '%s-%s-%s', $nPostId, $nTs, $this->ip() ) ) ),
			$sToken,
			$oFO->getTokenExpireInterval()
		);

		return $sToken;
	}

	/**
	 * @param int    $nTs
	 * @param string $nPostId
	 * @return string
	 */
	protected function getToken( $nTs, $nPostId ) {
		$oMod = $this->getCon()->getModule_Plugin();
		return hash_hmac( 'sha1', $nPostId.$this->ip().$nTs, $oMod->getPluginInstallationId() );
	}

	/**
	 * @param string $sToken
	 * @return string
	 */
	protected function getGaspCommentsHookHtml( $sToken ) {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();
		$aHtml = [
			'<p id="'.$this->getUniqueFormId().'"></p>', // we use this unique <p> to hook onto using javascript
			'<input type="hidden" id="_sugar_sweet_email" name="sugar_sweet_email" value="" />',
			sprintf( '<input type="hidden" id="_botts" name="botts" value="%s" />', Services::Request()->ts() ),
			sprintf( '<input type="hidden" id="_comment_token" name="comment_token" value="%s" />', $sToken )
		];
		return implode( '', $aHtml );
	}

	/**
	 * @return string
	 */
	protected function getGaspCommentsHtml() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		$sId = $this->getUniqueFormId();
		$sConfirm = $oFO->getTextOpt( 'custom_message_checkbox' );
		$sAlert = $oFO->getTextOpt( 'custom_message_alert' );
		$sCommentWait = $oFO->getTextOpt( 'custom_message_comment_wait' );
		$sCommentReload = $oFO->getTextOpt( 'custom_message_comment_reload' );

		$nCooldown = $oFO->getTokenCooldown();
		$nExpire = $oFO->getTokenExpireInterval();

		$sJsCommentWait = '"'.str_replace( '%s', '"+nRemaining+"', $sCommentWait ).'"';
		$sCommentWait = str_replace( '%s', $nCooldown, $sCommentWait ); // don't use sprintf for errors.

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
				( $nCooldown > 0 ) ?
					"
							subbutton$sId.disabled		= true;
							origButtonValue$sId			= subbutton$sId.value;
							subbutton$sId.value			= \"$sCommentWait\";
							nTimerCounter$sId			= 0;
							sCountdownTimer$sId			= setInterval( reenableButton$sId, 1000 );
							"
					: ''
				).(
				( $nExpire > 0 ) ? "sTimeoutTimer$sId			= setTimeout( redisableButton$sId, ".( 1000*$nExpire - 1000 )." );" : ''
				)."
						}
					}
					" : ''
			)."
			</script>
		";
		return $sReturn;
	}

	/**
	 * @return string
	 */
	protected function getUniqueFormId() {
		if ( !isset( $this->sFormId ) ) {
			$oDp = Services::Data();
			$sId = $oDp->generateRandomLetter().$oDp->generateRandomString( rand( 7, 23 ), 7 );
			$this->sFormId = preg_replace(
				'#[^a-zA-Z0-9]#', '',
				apply_filters( 'icwp_shield_cf_gasp_uniqid', $sId ) );
		}
		return $this->sFormId;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id int(11) NOT NULL DEFAULT 0,
			unique_token VARCHAR(32) NOT NULL DEFAULT '',
			ip varchar(40) NOT NULL DEFAULT '0',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'spambot_comments_filter_table_columns' );
		return is_array( $aDef ) ? $aDef : [];
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();
		return $oFO->getTokenExpireInterval();
	}

	/**
	 * @return Comments\Handler
	 */
	protected function createDbHandler() {
		return new Comments\Handler();
	}

	/**
	 * @return bool|int
	 */
	public function cleanupDatabase() {
		return false;
	}
}