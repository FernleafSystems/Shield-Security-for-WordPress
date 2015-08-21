<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Ips', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		public function getTransgressionLimit() {
			return $this->getOpt( 'transgression_limit' );
		}

		/**
		 * @return int
		 */
		public function getAutoExpireTime() {
			return constant( strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
		}

		/**
		 * @return bool
		 */
		public function getIsAutoBlackListFeatureEnabled() {
			return ( $this->getTransgressionLimit() > 0 );
		}

		/**
		 * @return string
		 */
		public function getIpListsTableName() {
			return $this->doPluginPrefix( $this->getOpt( 'ip_lists_table_name' ), '_' );
		}

		public function doPrePluginOptionsSave() {
			$sSetting = $this->getOpt( 'auto_expire' );
			if ( !in_array( $sSetting, array( 'minute', 'hour', 'day', 'week' ) ) ) {
				$this->getOptionsVo()->resetOptToDefault( 'auto_expire' );
			}

			$nLimit = $this->getTransgressionLimit();
			if ( !is_int( $nLimit ) || $nLimit < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'transgression_limit' );
			}
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_ips' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'IP Manager' ) ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_auto_black_list' :
					$sTitle = _wpsf__( 'Automatic IP Black List' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Automatic IP Black List' ) ) )
					);
					$sTitleShort = _wpsf__( 'Auto Black List' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['section_title_short'] = $sTitleShort;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {
			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_ips' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'transgression_limit' :
					$sName = _wpsf__( 'Transgression Limit' );
					$sSummary = _wpsf__( 'Visitor IP address will be Black Listed after X bad actions on your site' );
					$sDescription = sprintf( _wpsf__( 'Each time a visitor trips the defenses of the %s plugin a black mark is set against their IP address.' ), $this->getController()->getHumanName() )
						.'<br />'. _wpsf__( 'When the number these transgressions exceeds specified limit, they are automatically blocked from accessing the site.' )
						.'<br />'. sprintf( _wpsf__( 'Set this to "0" to turn off the %s feature.' ), _wpsf__( 'Automatic IP Black List' ) );
					break;

				case 'auto_expire' :
					$sName = _wpsf__( 'Auto Block Expiration' );
					$sSummary = _wpsf__( 'A 1 X a black listed IP will be removed from the black list' );
					$sDescription = _wpsf__( 'Permanent and lengthy IP Black Lists are harmful to performance.' )
						.'<br />'. _wpsf__( 'You should allow IP addresses on the black list to be eventually removed over time.' )
						.'<br />'. _wpsf__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;