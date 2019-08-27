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
					'title'   => __( 'File Editing via WP', 'wp-simple-firewall' ),
					'message' => __( 'Direct editing of plugin/theme files is permitted.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToOption( 'disable_file_editing' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
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
				'name'    => __( 'File Editing via WP', 'wp-simple-firewall' ),
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
	 * @return Shield\Modules\Lockdown\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Lockdown\Options();
	}

	/**
	 * @return Shield\Modules\Lockdown\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Lockdown\Strings();
	}
}