<?php

if ( class_exists( 'ICWP_WPSF_WpFunctions_Themes', false ) ) {
	return;
}

class ICWP_WPSF_WpFunctions_Themes extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpFunctions_Themes
	 */
	protected static $oInstance = null;

	private function __construct() {}

	/**
	 * @return ICWP_WPSF_WpFunctions_Themes
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sThemeStylesheet
	 * @return bool
	 */
	public function activate( $sThemeStylesheet ) {
		if ( empty( $sThemeStylesheet ) ) {
			return false;
		}

		$oTheme = $this->getTheme( $sThemeStylesheet );
		if ( !$oTheme->exists() ) {
			return false;
		}

		switch_theme( $oTheme->get_stylesheet() );

		// Now test currently active theme
		$oCurrentTheme = $this->getCurrent();

		return ( !is_null( $oCurrentTheme ) && ( $sThemeStylesheet == $oCurrentTheme->get_stylesheet() ) );
	}

	/**
	 * @param string $sStylesheet
	 * @return bool|WP_Error
	 */
	public function delete( $sStylesheet ) {
		if ( empty( $sStylesheet ) ) {
			return false;
		}
		if ( !function_exists( 'delete_theme' ) ) {
			require_once( ABSPATH.'wp-admin/includes/theme.php' );
		}
		return function_exists( 'delete_theme' ) ? delete_theme( $sStylesheet ) : false;
	}

	/**
	 * @param string $sUrlToInstall
	 * @param bool   $bOverwrite
	 * @return bool
	 */
	public function install( $sUrlToInstall, $bOverwrite = true ) {
		$this->loadWpUpgrades();

		$aResult = array(
			'successful'  => true,
			'plugin_info' => '',
			'errors'      => array()
		);

		$oUpgraderSkin = new ICWP_Upgrader_Skin();
		$oUpgrader = new ICWP_Theme_Upgrader( $oUpgraderSkin );
		$oUpgrader->setOverwriteMode( $bOverwrite );
		ob_start();
		$sInstallResult = $oUpgrader->install( $sUrlToInstall );
		ob_end_clean();
		if ( is_wp_error( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
			$aResult[ 'successful' ] = false;
			$aResult[ 'errors' ] = $oUpgraderSkin->m_aErrors[ 0 ]->get_error_messages();
		}
		else {
			$aResult[ 'theme_info' ] = $oUpgrader->theme_info();
		}

		$aResult[ 'feedback' ] = $oUpgraderSkin->getFeedback();
		return $aResult;
	}

	/**
	 * @param string $sFile
	 * @return array
	 */
	public function update( $sFile ) {
		$this->loadWpUpgrades();

		$aResult = array(
			'successful' => 1,
			'errors'     => array()
		);

		$oUpgraderSkin = new ICWP_Bulk_Theme_Upgrader_Skin();
		$oUpgrader = new Theme_Upgrader( $oUpgraderSkin );
		ob_start();
		$oUpgrader->bulk_upgrade( array( $sFile ) );
		ob_end_clean();

		if ( isset( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
			if ( is_wp_error( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
				$aResult[ 'successful' ] = 0;
				$aResult[ 'errors' ] = $oUpgraderSkin->m_aErrors[ 0 ]->get_error_messages();
			}
		}
		$aResult[ 'feedback' ] = $oUpgraderSkin->getFeedback();
		return $aResult;
	}

	/**
	 * @return string|WP_Theme
	 */
	public function getCurrentThemeName() {
		return $this->loadWp()->getWordpressIsAtLeastVersion( '3.4.0' ) ? $this->getCurrent()->get( 'Name' ) : get_current_theme();
	}

	/**
	 * @return null|WP_Theme
	 */
	public function getCurrent() {
		return $this->getTheme();
	}

	/**
	 * @param string $sStylesheet
	 * @return bool
	 */
	public function getExists( $sStylesheet ) {
		$oTheme = $this->getTheme( $sStylesheet );
		return ( !is_null( $oTheme ) && ( $oTheme instanceof WP_Theme ) && $oTheme->exists() );
	}

	/**
	 * @param string $sStylesheet
	 * @return null|WP_Theme
	 */
	public function getTheme( $sStylesheet = null ) {
		if ( $this->loadWp()->getWordpressIsAtLeastVersion( '3.4.0' ) ) {
			if ( !function_exists( 'wp_get_theme' ) ) {
				require_once( ABSPATH.'wp-admin/includes/theme.php' );
			}
			return function_exists( 'wp_get_theme' ) ? wp_get_theme( $sStylesheet ) : null;
		}
		$aThemes = $this->getThemes();
		return array_key_exists( $sStylesheet, $aThemes ) ? $aThemes[ $sStylesheet ] : null;
	}

	/**
	 * Abstracts the WordPress wp_get_themes()
	 * @return array|WP_Theme[]
	 */
	public function getThemes() {
		if ( !function_exists( 'wp_get_themes' ) ) {
			require_once( ABSPATH.'wp-admin/includes/theme.php' );
		}
		return function_exists( 'wp_get_themes' ) ? wp_get_themes() : get_themes();
	}

	/**
	 * @param bool $bForceUpdateCheck
	 * @return stdClass
	 */
	public function getUpdates( $bForceUpdateCheck = false ) {
		if ( $bForceUpdateCheck ) {
			$this->clearUpdates();
			$this->checkForUpdates();
		}
		return $this->loadWp()->getTransient( 'update_themes' );
	}

	/**
	 * @return null|WP_Theme
	 */
	public function getCurrentParent() {
		$oTheme = $this->getCurrent();
		return $this->isActiveThemeAChild() ? $this->getTheme( $oTheme->get_template() ) : null;
	}

	/**
	 * @param string $sBase
	 * @return object|WP_Error
	 */
	public function getExtendedData( $sBase ) {
		include_once( ABSPATH.'wp-admin/includes/theme.php' );

		$oApi = themes_api( 'theme_information', array(
			'slug'   => $sBase,
			'fields' => array(
				'sections' => false,
			),
		) );
		return $oApi;
	}

	/**
	 * @return bool
	 */
	public function isActiveThemeAChild() {
		$oTheme = $this->getCurrent();
		return ( $oTheme->get_stylesheet() != $oTheme->get_template() );
	}

	/**
	 * @param string $sBaseName
	 * @return bool
	 */
	public function isWpOrg( $sBaseName ) {
		$bIsWpOrg = false;
		$oInfo = $this->getExtendedData( $sBaseName );
		if ( !empty( $oInfo ) && !is_wp_error( $oInfo ) && isset( $oInfo->download_link ) ) {
			$bIsWpOrg = strpos( $oInfo->download_link, 'https://downloads.wordpress.org' ) === 0;
		}
		return $bIsWpOrg;
	}

	/**
	 * @return boolean|null
	 */
	protected function checkForUpdates() {

		if ( class_exists( 'WPRC_Installer' ) && method_exists( 'WPRC_Installer', 'wprc_update_themes' ) ) {
			WPRC_Installer::wprc_update_themes();
			return true;
		}
		else if ( function_exists( 'wp_update_themes' ) ) {
			return ( wp_update_themes() !== false );
		}
		return null;
	}

	/**
	 */
	protected function clearUpdates() {
		$sKey = 'update_themes';
		$oResponse = $this->loadWp()->getTransient( $sKey );
		if ( !is_object( $oResponse ) ) {
			$oResponse = new stdClass();
		}
		$oResponse->last_checked = 0;
		$this->loadWp()->setTransient( $sKey, $oResponse );
	}

	/**
	 * @return array
	 */
	public function wpmsGetSiteAllowedThemes() {
		return ( function_exists( 'get_site_allowed_themes' ) ? get_site_allowed_themes() : array() );
	}
}