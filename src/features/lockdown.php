<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Lockdown extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string[]
	 */
	private function getRestApiAnonymousExclusions() {
		$aExcl = $this->getOpt( 'api_namespace_exclusions' );
		if ( !is_array( $aExcl ) ) {
			$aExcl = [];
		}
		return array_merge( $this->getDef( 'default_restapi_exclusions' ), $aExcl );
	}

	/**
	 * @param string $sNamespace
	 * @return bool
	 */
	public function isPermittedAnonRestApiNamespace( $sNamespace ) {
		return in_array( $sNamespace, $this->getRestApiAnonymousExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isOptFileEditingDisabled() {
		return $this->isOpt( 'disable_file_editing', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isRestApiAnonymousAccessDisabled() {
		return $this->isOpt( 'disable_anonymous_restapi', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcDisabled() {
		return $this->isOpt( 'disable_xmlrpc', 'Y' );
	}

	protected function doExtraSubmitProcessing() {
		$sMask = $this->getOpt( 'mask_wordpress_version' );
		if ( !empty( $sMask ) ) {
			$this->setOpt( 'mask_wordpress_version', preg_replace( '/[^a-z0-9_.-]/i', '', $sMask ) );
		}
		$this->cleanApiExclusions();
	}

	/**
	 * @return $this
	 */
	private function cleanApiExclusions() {
		$aExt = $this->cleanStringArray( $this->getRestApiAnonymousExclusions(), '#[^a-z0-9_-]#i' );
		return $this->setOpt( 'api_namespace_exclusions', $aExt );
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$aNotices = [
			'title'    => __( 'WP Lockdown', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //edit plugins
			$bEditingDisabled = $this->isOptFileEditingDisabled() || !current_user_can( 'edit_plugins' );
			if ( !$bEditingDisabled ) { //assumes current user is admin
				$aNotices[ 'messages' ][ 'disallow_file_edit' ] = [
					'title'   => 'Code Editor',
					'message' => __( 'Direct editing of plugin/theme files is permitted.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToOption( 'disable_file_editing' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'WP Plugin file editing should be disabled.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'lockdown' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'WordPress Lockdown', 'wp-simple-firewall' ),
				'sub'   => __( 'Restrict WP Functionality e.g. XMLRPC & REST API', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bEditingDisabled = $this->isOptFileEditingDisabled() || !current_user_can( 'edit_plugins' );
			$aThis[ 'key_opts' ][ 'editing' ] = [
				'name'    => __( 'WP File Editing', 'wp-simple-firewall' ),
				'enabled' => $bEditingDisabled,
				'summary' => $bEditingDisabled ?
					__( 'File editing is disabled', 'wp-simple-firewall' )
					: __( "File editing is permitted through WP admin", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'disable_file_editing' ),
			];

			$bXml = $this->isXmlrpcDisabled();
			$aThis[ 'key_opts' ][ 'xml' ] = [
				'name'    => __( 'XML-RPC', 'wp-simple-firewall' ),
				'enabled' => $bXml,
				'summary' => $bXml ?
					__( 'XML-RPC is disabled', 'wp-simple-firewall' )
					: __( "XML-RPC is not blocked", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'disable_xmlrpc' ),
			];

			$bApi = $this->isRestApiAnonymousAccessDisabled();
			$aThis[ 'key_opts' ][ 'api' ] = [
				'name'    => __( 'REST API', 'wp-simple-firewall' ),
				'enabled' => $bApi,
				'summary' => $bApi ?
					__( 'Anonymous REST API is disabled', 'wp-simple-firewall' )
					: __( "Anonymous REST API is allowed", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'disable_anonymous_restapi' ),
			];
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

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_wordpress_lockdown' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Lockdown', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_apixml' :
				$sTitle = __( 'API & XML-RPC', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown certain core WordPress system features.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This depends on your usage and needs for certain WordPress functions and features.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'API & XML-RPC', 'wp-simple-firewall' );
				break;

			case 'section_permission_access_options' :
				$sTitle = __( 'Permissions and Access Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control of certain WordPress permissions.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Only enable SSL if you have a valid certificate installed.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Permissions', 'wp-simple-firewall' );
				break;

			case 'section_wordpress_obscurity_options' :
				$sTitle = __( 'WordPress Obscurity Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Obscures certain WordPress settings from public view.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Obscurity is not true security and so these settings are down to your personal tastes.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Obscurity', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_lockdown' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'disable_xmlrpc' :
				$sName = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), 'XML-RPC' );
				$sSummary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), 'XML-RPC' );
				$sDescription = sprintf( __( 'Checking this option will completely turn off the whole %s system.', 'wp-simple-firewall' ), 'XML-RPC' );
				break;

			case 'disable_anonymous_restapi' :
				$sName = __( 'Anonymous Rest API', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), __( 'Anonymous Rest API', 'wp-simple-firewall' ) );
				$sDescription = __( 'You can choose to completely disable anonymous access to the REST API.', 'wp-simple-firewall' );
				break;

			case 'api_namespace_exclusions' :
				$sName = __( 'Rest API Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Anonymous REST API Exclusions', 'wp-simple-firewall' );
				$sDescription = __( 'Any namespaces provided here will be excluded from the Anonymous API restriction.', 'wp-simple-firewall' );
				break;

			case 'disable_file_editing' :
				$sName = __( 'Disable File Editing', 'wp-simple-firewall' );
				$sSummary = __( 'Disable Ability To Edit Files From Within WordPress', 'wp-simple-firewall' );
				$sDescription = __( 'Removes the option to directly edit any files from within the WordPress admin area.', 'wp-simple-firewall' )
								.'<br />'.__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.', 'wp-simple-firewall' );
				break;

			case 'force_ssl_admin' :
				$sName = __( 'Force SSL Admin', 'wp-simple-firewall' );
				$sSummary = __( 'Forces WordPress Admin Dashboard To Be Delivered Over SSL', 'wp-simple-firewall' );
				$sDescription = __( 'Please only enable this option if you have a valid SSL certificate installed.', 'wp-simple-firewall' )
								.'<br />'.__( 'Equivalent to setting "FORCE_SSL_ADMIN" to TRUE.', 'wp-simple-firewall' );
				break;

			case 'mask_wordpress_version' :
				$sName = __( 'Mask WordPress Version', 'wp-simple-firewall' );
				$sSummary = __( 'Prevents Public Display Of Your WordPress Version', 'wp-simple-firewall' );
				$sDescription = __( 'Enter how you would like your WordPress version displayed publicly. Leave blank to disable this feature.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This may interfere with WordPress plugins that rely on the $wp_version variable.', 'wp-simple-firewall' ) );
				break;

			case 'hide_wordpress_generator_tag' :
				$sName = __( 'WP Generator Tag', 'wp-simple-firewall' );
				$sSummary = __( 'Remove WP Generator Meta Tag', 'wp-simple-firewall' );
				$sDescription = __( 'Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version.', 'wp-simple-firewall' );
				break;

			case 'block_author_discovery' :
				$sName = __( 'Block Username Fishing', 'wp-simple-firewall' );
				$sSummary = __( 'Block the ability to discover WordPress usernames based on author IDs', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'When enabled, any URL requests containing "%s" will be killed.', 'wp-simple-firewall' ), 'author=' )
								.'<br />'.sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option may interfere with expected operations of your site.', 'wp-simple-firewall' ) );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	protected function getCanDoAuthSalts() {
		$oWpFs = Services::WpFs();

		if ( !$oWpFs->getCanWpRemoteGet() ) {
			return false;
		}

		if ( !$oWpFs->getCanDiskWrite() ) {
			return false;
		}

		$sWpConfigPath = $oWpFs->exists( ABSPATH.'wp-config.php' ) ? ABSPATH.'wp-config.php' : ABSPATH.'../wp-config.php';

		if ( !$oWpFs->exists( $sWpConfigPath ) ) {
			return false;
		}
		$mResult = $oWpFs->getCanReadWriteFile( $sWpConfigPath );
		return !empty( $mResult );
	}

	/**
	 * @return Shield\Modules\Lockdown\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Lockdown\Strings();
	}
}