<?php

if ( class_exists( 'ICWP_WPSF_Processor_Lockdown' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_Lockdown extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Lockdown $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->getOptIs( 'disable_file_editing', 'Y' ) ) {
			if ( !defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
			add_filter( 'user_has_cap', array( $this, 'disableFileEditing' ), 0, 3 );
		}

		$sWpVersionMask = $this->getOption( 'mask_wordpress_version' );
		if ( !empty( $sWpVersionMask ) ) {
			global $wp_version;
			$wp_version = $sWpVersionMask;
// 			add_filter( 'bloginfo', array( $this, 'maskWordpressVersion' ), 1, 2 );
// 			add_filter( 'bloginfo_url', array( $this, 'maskWordpressVersion' ), 1, 2 );
		}

		if ( false && $this->getOption( 'action_reset_auth_salts' ) == 'Y' ) {
			add_action( 'init', array( $this, 'resetAuthKeysSalts' ), 1 );
		}

		if ( $oFO->getOptIs( 'force_ssl_admin', 'Y' ) && function_exists( 'force_ssl_admin' ) ) {
			if ( !defined( 'FORCE_SSL_ADMIN' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
			force_ssl_admin( true );
		}

		if ( $oFO->getOptIs( 'hide_wordpress_generator_tag', 'Y' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
		}

		if ( $oFO->getOptIs( 'block_author_discovery', 'Y' ) ) {
			// jump in right before add_action( 'template_redirect', 'redirect_canonical' );
			add_action( 'wp', array( $this, 'interceptCanonicalRedirects' ), 9 );
		}

		if ( $oFO->getOptIs( 'disable_xmlrpc', 'Y' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false', 1000 );
			add_filter( 'xmlrpc_methods', '__return_empty_array', 1000 );
		}

		if ( $oFO->getIfRestApiDisabled() ) {
			// 99 so that we jump in just before the always-on WordPress cookie auth.
			add_filter( 'rest_authentication_errors', array( $this, 'disableAnonymousRestApi' ), 99 );
		}
	}

	/**
	 * Understand that if $mCurrentStatus is null, no check has been made. If true, something has
	 * authenticated the request, and if WP_Error, then an error is already present
	 * @param WP_Error|true|null $mCurrentStatus
	 * @return WP_Error
	 */
	public function disableAnonymousRestApi( $mCurrentStatus ) {
		$bAlreadyAuthenticated = ( $mCurrentStatus === true );
		if ( !$bAlreadyAuthenticated && !is_wp_error( $mCurrentStatus ) && !$this->loadWpUsers()->isUserLoggedIn() ) {
			$mCurrentStatus = new WP_Error(
				'shield_block_anon_restapi',
				sprintf( _wpsf__( 'Anonymous access to the WordPress Rest API has been restricted by %s.' ), $this->getController()
																												  ->getHumanName() ),
				array( 'status' => rest_authorization_required_code() ) );
		}
		return $mCurrentStatus;
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$sSlug = $this->getFeature()->getFeatureSlug();
		$aData[ $sSlug ][ 'options' ][ 'mask_wordpress_version' ]
			= empty( $aData[ $sSlug ][ 'options' ][ 'mask_wordpress_version' ] ) ? 0 : 1;
		return $aData;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disableFileEditing( $aAllCaps, $cap, $aArgs ) {

		$aEditCapabilities = array( 'edit_themes', 'edit_plugins', 'edit_files' );
		$sRequestedCapability = $aArgs[ 0 ];

		if ( !in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			return $aAllCaps;
		}
		$aAllCaps[ $sRequestedCapability ] = false;
		return $aAllCaps;
	}

	/**
	 * @param $sOutput
	 * @param $sShow
	 * @return string
	 */
	public function maskWordpressVersion( $sOutput, $sShow ) {
// 		if ( $sShow === 'version' ) {
// 			$sOutput = $this->aOptions['mask_wordpress_version'];
// 		}
// 		return $sOutput;
	}

	/**
	 */
	public function resetAuthKeysSalts() {
		$oWpFs = $this->loadFS();

		// Get the new Salts
		$sSaltsUrl = 'https://api.wordpress.org/secret-key/1.1/salt/';
		$sSalts = $oWpFs->getUrlContent( $sSaltsUrl );

		$sWpConfigContent = $oWpFs->getContent_WpConfig();
		if ( is_null( $sWpConfigContent ) ) {
			return;
		}

		$aKeys = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT'
		);

		$aContent = explode( PHP_EOL, $sWpConfigContent );
		$fKeyFound = false;
		$nStartLine = 0;
		foreach ( $aContent as $nLineNumber => $sLine ) {
			foreach ( $aKeys as $nPosition => $sKey ) {
				if ( strpos( $sLine, $sKey ) === false ) {
					continue;
				}
				if ( $nStartLine == 0 ) {
					$nStartLine = $nLineNumber;
				}
				else {
					unset( $aContent[ $nLineNumber ] );
				}
				$fKeyFound = true;
			}
		}
		$aContent[ $nStartLine ] = $sSalts;
		$oWpFs->putContent_WpConfig( implode( PHP_EOL, $aContent ) );
	}

	/**
	 * @uses wp_die()
	 */
	public function interceptCanonicalRedirects() {

		if ( $this->getIsOption( 'block_author_discovery', 'Y' ) && !$this->loadWpUsers()->isUserLoggedIn() ) {
			$sAuthor = $this->loadDataProcessor()->FetchGet( 'author', '' );
			if ( !empty( $sAuthor ) ) {
				$this->loadWp()->wpDie( sprintf(
					_wpsf__( 'The "author" query parameter has been blocked by %s to protect against user login name fishing.' )
					.sprintf( '<br /><a href="%s" target="_blank">%s</a>',
						'http://icwp.io/7l',
						_wpsf__( 'Learn More.' )
					),
					$this->getController()->getHumanName()
				) );
			}
		}
	}
}