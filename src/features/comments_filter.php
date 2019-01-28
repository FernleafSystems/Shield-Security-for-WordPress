<?php

class ICWP_WPSF_FeatureHandler_CommentsFilter extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var array
	 */
	private $aCommentData;

	/**
	 */
	protected function setupCustomHooks() {
		add_filter( 'preprocess_comment', array( $this, 'gatherRawCommentData' ), 1 );
	}

	/**
	 * @param array $aRawCommentData
	 * @return array
	 */
	public function gatherRawCommentData( $aRawCommentData ) {
		$this->aCommentData = $aRawCommentData;
		return $aRawCommentData;
	}

	/**
	 * @return array
	 */
	public function getCommentData() {
		return ( isset( $this->aCommentData ) && is_array( $this->aCommentData ) ) ? $this->aCommentData : array();
	}

	/**
	 * @param string $sKey
	 * @return array|mixed
	 */
	public function getCommentItem( $sKey ) {
		$aD = $this->getCommentData();
		return isset( $aD[ $sKey ] ) ? $aD[ $sKey ] : null;
	}

	/**
	 * @return boolean
	 */
	public function getIfDoCommentsCheck() {
		$oWpComments = $this->loadWpComments();

		// 1st are comments enabled on this post?
		$oPost = $this->loadWp()->getPostById( $this->getCommentItem( 'comment_post_ID' ) );
		return ( $oPost instanceof WP_Post ) && $oWpComments->isCommentsOpen( $oPost )
			   && ( !$oWpComments->getIfAllowCommentsByPreviouslyApproved() || !$oWpComments->isAuthorApproved( $this->getCommentItem( 'comment_author_email' ) ) );
	}

	/**
	 * @return boolean
	 */
	public function getIfCheckCommentToken() {
		return ( $this->getTokenExpireInterval() > 0 || $this->getTokenCooldown() > 0 );
	}

	/**
	 * @return int
	 */
	public function getTokenCooldown() {
		return (int)$this->getOpt( 'comments_cooldown_interval' );
	}

	/**
	 * @return int
	 */
	public function getTokenExpireInterval() {
		return (int)$this->getOpt( 'comments_token_expire_interval' );
	}

	/**
	 * @return string
	 */
	public function getGoogleRecaptchaStyle() {
		$sStyle = $this->getOpt( 'google_recaptcha_style_comments' );
		if ( $sStyle == 'default' ) {
			$sStyle = parent::getGoogleRecaptchaStyle();
		}
		return $sStyle;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {
			case 'custom_message_checkbox':
				$sText = _wpsf__( "I'm not a spammer." );
				break;
			case 'custom_message_alert':
				$sText = _wpsf__( "Please check the box to confirm you're not a spammer." );
				break;
			case 'custom_message_comment_wait':
				$sText = _wpsf__( "Please wait %s seconds before posting your comment." );
				break;
			case 'custom_message_comment_reload':
				$sText = _wpsf__( "Please reload this page to post a comment." );
				break;
			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	protected function doExtraSubmitProcessing() {
		if ( $this->getTokenExpireInterval() != 0 && $this->getTokenCooldown() > $this->getTokenExpireInterval() ) {
			$this->getOptionsVo()->resetOptToDefault( 'comments_cooldown_interval' );
			$this->getOptionsVo()->resetOptToDefault( 'comments_token_expire_interval' );
		}

		$aCommentsFilters = $this->getOpt( 'enable_comments_human_spam_filter_items' );
		if ( empty( $aCommentsFilters ) || !is_array( $aCommentsFilters ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'enable_comments_human_spam_filter_items' );
		}
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'SPAM Blocking' ),
				'sub'   => _wpsf__( 'Block Bot & Human Comment SPAM' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aThis[ 'key_opts' ][ 'bot' ] = array(
				'name'    => _wpsf__( 'Bot SPAM' ),
				'enabled' => $this->isEnabledGaspCheck() || $this->isGoogleRecaptchaEnabled(),
				'summary' => ( $this->isEnabledGaspCheck() || $this->isGoogleRecaptchaEnabled() ) ?
					_wpsf__( 'Bot SPAM comments are blocked' )
					: _wpsf__( 'There is no protection against Bot SPAM comments' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_bot_comment_spam_protection_filter' ),
			);
			$aThis[ 'key_opts' ][ 'human' ] = array(
				'name'    => _wpsf__( 'Human SPAM' ),
				'enabled' => $this->isEnabledHumanCheck(),
				'summary' => $this->isEnabledHumanCheck() ?
					_wpsf__( 'Comments posted by humans are checked for SPAM' )
					: _wpsf__( "Comments posted by humans aren't checked for SPAM" ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_human_spam_filter' ),
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oPlugin */
		$oPlugin = $this->getCon()->getModule( 'plugin' );
		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_spam_comments_protection_filter' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), _wpsf__( 'Comments SPAM Protection' ) );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The Comments Filter can block 100% of automated spam bots and also offer the option to analyse human-generated spam.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Comments Filter' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_bot_comment_spam_protection_filter' :
				$sTitle = sprintf( _wpsf__( '%s Comment SPAM Protection' ), _wpsf__( 'Automatic Bot' ) );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Blocks 100% of all automated bot-generated comment SPAM.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				$sTitleShort = _wpsf__( 'Bot SPAM' );
				break;

			case 'section_recaptcha' :
				$sTitle = 'Google reCAPTCHA';
				$sTitleShort = 'reCAPTCHA';
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Adds Google reCAPTCHA to the Comment Forms.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Keep this turned on.' ) ),
					sprintf( '%s - %s (%s)', _wpsf__( 'Important' ),
						_wpsf__( "You'll need to supply your Google reCAPTCHA keys." ),
						sprintf( '<a href="%s" target="_blank">%s</a>', $oPlugin->getUrl_DirectLinkToSection( 'section_third_party_google' ), _wpsf__( "Enter Google reCAPTCHA keys" ) )
					),
				);
				break;

			case 'section_human_spam_filter' :
				$sTitle = sprintf( _wpsf__( '%s Comment SPAM Protection Filter' ), _wpsf__( 'Human' ) );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
					_wpsf__( 'This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis.' )
				);
				$sTitleShort = _wpsf__( 'Human SPAM' );
				break;

			default:
				list( $sTitle, $sTitleShort, $aSummary ) = $this->loadStrings_SectionTitlesDefaults( $aOptionsParams );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @return bool
	 */
	public function isGoogleRecaptchaEnabled() {
		return $this->isModOptEnabled() &&
			   ( $this->isOpt( 'enable_google_recaptcha_comments', 'Y' ) && $this->isGoogleRecaptchaReady() );
	}

	/**
	 * @return bool
	 */
	public function isEnabledGaspCheck() {
		return $this->isModOptEnabled() && $this->isOpt( 'enable_comments_gasp_protection', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledHumanCheck() {
		return $this->isModOptEnabled() && $this->isOpt( 'enable_comments_human_spam_filter', 'Y' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setEnabledGasp( $bEnabled = true ) {
		return $this->setOpt( 'enable_comments_gasp_protection', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_comments_filter' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = _wpsf__( 'Enable (or Disable) The Comment SPAM Protection Feature' );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), _wpsf__( 'Comment SPAM Protection' ) );
				break;

			case 'enable_comments_human_spam_filter' :
				$sName = _wpsf__( 'Human SPAM Filter' );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), _wpsf__( 'Human SPAM Filter' ) );
				$sDescription = _wpsf__( 'Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below.' );
				break;

			case 'enable_comments_human_spam_filter_items' :
				$sName = _wpsf__( 'Comment Filter Items' );
				$sSummary = _wpsf__( 'Select The Items To Scan For SPAM' );
				$sDescription = _wpsf__( 'When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content.' ).' '.sprintf( _wpsf__( 'Recommended: %s' ), _wpsf__( 'All' ) );
				break;

			case 'comments_default_action_human_spam' :
				$sName = _wpsf__( 'Default SPAM Action' );
				$sSummary = _wpsf__( 'How To Categorise Comments When Identified To Be SPAM' );
				$sDescription = sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__( 'a human commenter' ).'</span>' );
				break;

			case 'enable_comments_gasp_protection' :
				$sName = _wpsf__( 'SPAM Bot Protection' );
				$sSummary = _wpsf__( 'Block 100% Comment SPAM From Automated Bots' );
				$sDescription = _wpsf__( 'Highly effective detection for the most common types of comment SPAM.' )
								.'<br/>'.sprintf( '%s: %s', _wpsf__( 'Bonus' ), _wpsf__( "Unlike Akismet, your data is never sent off-site to 3rd party processing servers." ) );
				break;

			case 'comments_default_action_spam_bot' :
				$sName = _wpsf__( 'Default SPAM Action' );
				$sSummary = _wpsf__( 'How To Categorise Comments When Identified To Be SPAM' );
				$sDescription = sprintf( _wpsf__( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.' ), '<span style"text-decoration:underline;">'._wpsf__( 'an automatic bot' ).'</span>' );
				break;

			case 'comments_cooldown_interval' :
				$sName = _wpsf__( 'Comments Cooldown' );
				$sSummary = _wpsf__( 'Limit posting comments to X seconds after the page has loaded' );
				$sDescription = _wpsf__( "By forcing a comments cooldown period, you restrict a Spambot's ability to post multiple times to your posts." );
				break;

			case 'comments_token_expire_interval' :
				$sName = _wpsf__( 'Comment Token Expire' );
				$sSummary = _wpsf__( 'A visitor has X seconds within which to post a comment' );
				$sDescription = _wpsf__( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors." );
				break;

			case 'custom_message_checkbox' :
				$sName = _wpsf__( 'GASP Checkbox Message' );
				$sSummary = _wpsf__( 'If you want a custom checkbox message, please provide this here' );
				$sDescription = _wpsf__( "You can customise the message beside the checkbox." )
								.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__( "Please check the box to confirm you're not a spammer" ) );
				break;

			case 'enable_google_recaptcha_comments' :
				$sName = 'Google reCAPTCHA';
				$sSummary = _wpsf__( 'Enable Google reCAPTCHA For Comments' );
				$sDescription = _wpsf__( 'Use Google reCAPTCHA on the comments form to prevent bot-spam comments.' );
				break;

			case 'google_recaptcha_style_comments' :
				$sName = _wpsf__( 'reCAPTCHA Style' );
				$sSummary = _wpsf__( 'How Google reCAPTCHA Will Be Displayed' );
				$sDescription = _wpsf__( 'You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha' );
				break;

			case 'custom_message_alert' :
				$sName = _wpsf__( 'GASP Alert Message' );
				$sSummary = _wpsf__( 'If you want a custom alert message, please provide this here' );
				$sDescription = _wpsf__( "This alert message is displayed when a visitor attempts to submit a comment without checking the box." )
								.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__( "Please check the box to confirm you're not a spammer" ) );
				break;

			case 'custom_message_comment_wait' :
				$sName = _wpsf__( 'GASP Wait Message' );
				$sSummary = _wpsf__( 'If you want a custom submit-button wait message, please provide this here.' );
				$sDescription = _wpsf__( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these." )
								.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__( 'Please wait %s seconds before posting your comment' ) );
				break;

			case 'custom_message_comment_reload' :
				$sName = _wpsf__( 'GASP Reload Message' );
				$sSummary = _wpsf__( 'If you want a custom message when the comment token has expired, please provide this here.' );
				$sDescription = _wpsf__( 'This message is displayed on the submit-button when the comment token is expired' )
								.'<br />'.sprintf( _wpsf__( 'Default Message: %s' ), _wpsf__( "Please reload this page to post a comment" ) );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}