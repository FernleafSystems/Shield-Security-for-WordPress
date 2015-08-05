<?php

if ( !class_exists('ICWP_LockdownProcessor_V1') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_LockdownProcessor_V1 extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {

			if ( $this->getIsOption( 'disable_file_editing', 'Y' ) ) {
				if ( !defined('DISALLOW_FILE_EDIT') ) {
					define( 'DISALLOW_FILE_EDIT', true );
				}
				add_filter( 'user_has_cap', array( $this, 'disableFileEditing' ), 0, 3 );
			}

			$sWpVersionMask = $this->getOption('mask_wordpress_version');
			if ( !empty( $sWpVersionMask ) ) {
				global $wp_version;
				$wp_version = $sWpVersionMask;
// 			add_filter( 'bloginfo', array( $this, 'maskWordpressVersion' ), 1, 2 );
// 			add_filter( 'bloginfo_url', array( $this, 'maskWordpressVersion' ), 1, 2 );
			}

			if ( false && $this->getOption('action_reset_auth_salts') == 'Y' ) {
				add_action( 'init', array( $this, 'resetAuthKeysSalts' ), 1 );
			}

			if ( $this->getIsOption( 'force_ssl_login', 'Y' ) && function_exists('force_ssl_login') ) {
				if ( !defined('FORCE_SSL_LOGIN') ) {
					define( 'FORCE_SSL_LOGIN', true );
				}
				force_ssl_login( true );
			}

			if ( $this->getIsOption( 'force_ssl_admin', 'Y' ) && function_exists('force_ssl_admin') ) {
				if ( !defined('FORCE_SSL_ADMIN') ) {
					define( 'FORCE_SSL_ADMIN', true );
				}
				force_ssl_admin( true );
			}

			if ( $this->getIsOption( 'hide_wordpress_generator_tag', 'Y' ) ) {
				remove_action( 'wp_head', 'wp_generator' );
			}

			if ( $this->getIsOption( 'block_author_discovery', 'Y' ) ) {
				// jump in right before add_action( 'template_redirect', 'redirect_canonical' );
				add_action( 'wp', array( $this, 'interceptCanonicalRedirects' ), 9 );
			}

		}

		/**
		 * @param array $aAllCaps
		 * @param $cap
		 * @param array $aArgs
		 * @return array
		 */
		public function disableFileEditing( $aAllCaps, $cap, $aArgs ) {

			$aEditCapabilities = array( 'edit_themes', 'edit_plugins', 'edit_files' );
			$sRequestedCapability = $aArgs[0];

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
			$oWpFs = $this->loadFileSystemProcessor();

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
			foreach( $aContent as $nLineNumber => $sLine ) {
				foreach( $aKeys as $nPosition => $sKey ) {
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
			$aContent[$nStartLine] = $sSalts;
			$oWpFs->putContent_WpConfig( implode( PHP_EOL, $aContent ) );
		}

		/**
		 * @uses wp_die()
		 */
		public function interceptCanonicalRedirects() {
			$oDp = $this->loadDataProcessor();

			if ( $this->getIsOption( 'block_author_discovery', 'Y' ) && !is_user_logged_in() ) {
				$sAuthor = $oDp->FetchGet( 'author', '' );
				if ( !empty( $sAuthor ) ) {
					wp_die( sprintf(
						_wpsf__( 'The "author" query parameter has been blocked by %s to protect against user login name fishing.' ),
						$this->getController()->getHumanName()
					));
				}
			}
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Lockdown') ):
	class ICWP_WPSF_Processor_Lockdown extends ICWP_LockdownProcessor_V1 { }
endif;