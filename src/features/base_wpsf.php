<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_BaseWpsf', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_BaseWpsf extends ICWP_WPSF_FeatureHandler_Base {
		/**
		 * @return array
		 */
		protected function getBaseDisplayData() {
			$aData = parent::getBaseDisplayData();
			$aData['strings'] = array_merge(
				$aData['strings'],
				array(
					'go_to_settings' => _wpsf__( 'Settings' ),
					'on' => _wpsf__( 'On' ),
					'off' => _wpsf__( 'Off' ),
					'more_info' => _wpsf__( 'More Info' ),
					'blog' => _wpsf__( 'Blog' ),
					'plugin_activated_features_summary' => _wpsf__( 'Plugin Activated Features Summary:' ),
					'save_all_settings' => _wpsf__( 'Save All Settings' ),

					'aar_what_should_you_enter' => _wpsf__( 'What should you enter here?' ),
					'aar_must_supply_key_first' => _wpsf__( 'At some point you entered a Security Admin Access Key - to manage this plugin, you must supply it here first.' ),
					'aar_to_manage_must_enter_key' => _wpsf__( 'To manage this plugin you must enter the access key.' ),
					'aar_enter_access_key' => _wpsf__( 'Enter Access Key' ),
					'aar_submit_access_key' => _wpsf__( 'Submit Access Key' )
				)
			);
			$aData[ 'bShowStateSummary' ] = true;
			return $aData;
		}

		protected function getTranslatedString( $sKey, $sDefault ) {
			$aStrings = array(
				'nonce_failed_empty' => _wpsf__( 'Nonce security checking failed - the nonce value was empty.' ),
				'nonce_failed_supplied' => _wpsf__( 'Nonce security checking failed - the nonce supplied was "%s".' ),
			);
			return ( isset( $aStrings[ $sKey ] ) ? $aStrings[ $sKey ] : $sDefault );
		}
	}
endif;