<?php

if ( !class_exists( 'ICWP_WPSF_Processor_AdminAccessRestriction', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_Base {

		/**
		 * @var string
		 */
		protected $sOptionRegexPattern;

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();
			$oWp = $this->loadWpFunctionsProcessor();

			add_filter( $oFO->doPluginPrefix( 'has_permission_to_submit' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
			add_filter( $oFO->doPluginPrefix( 'has_permission_to_view' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
			if ( ! $oFO->getIsUpgrading() && ! $oWp->getIsLoginRequest() ) {
				add_filter( 'pre_update_option', array( $this, 'blockOptionsSaves' ), 1, 3 );
			}
		}

		/**
		 * Right before a plugin option is due to update it will check that we have permissions to do so and if not, will
		 * revert the option to save to the previous one.
		 *
		 * @param mixed $mNewOptionValue
		 * @param string $sOption
		 * @param mixed $mOldValue
		 * @return mixed
		 */
		public function blockOptionsSaves( $mNewOptionValue, $sOption, $mOldValue ) {
			if ( !preg_match( $this->getOptionRegexPattern(), $sOption ) ) {
				return $mNewOptionValue;
			}

			$fHasPermissionToChangeOptions = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true );
			if ( !$fHasPermissionToChangeOptions ) {
//				$sAuditMessage = sprintf( _wpsf__('Attempt to save/update option "%s" was blocked.'), $sOption );
//			    $this->addToAuditEntry( $sAuditMessage, 3, 'admin_access_option_block' );
				return $mOldValue;
			}

			return $mNewOptionValue;
		}

		/**
		 * @return string
		 */
		protected function getOptionRegexPattern() {
			if ( !isset( $this->sOptionRegexPattern ) ) {
				$this->sOptionRegexPattern = '/^'. $this->getFeatureOptions()->getOptionStoragePrefix() . '.*_options$/';
			}
			return $this->sOptionRegexPattern;
		}
	}

endif;
