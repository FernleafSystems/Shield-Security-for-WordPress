<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Base_V3', false ) ):

	abstract class ICWP_WPSF_FeatureHandler_Base_V3 extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_Plugin_Controller
		 */
		protected $oPluginController;

		/**
		 * @var ICWP_WPSF_OptionsVO
		 */
		protected $oOptions;

		/**
		 * @var string
		 */
		const CollateSeparator = '--SEP--';
		/**
		 * @var string
		 */
		const PluginVersionKey = 'current_plugin_version';

		/**
		 * @var boolean
		 */
		protected $bPluginDeleting = false;

		/**
		 * @var string
		 */
		protected $sOptionsStoreKey;

		/**
		 * @var string
		 */
		protected $sFeatureName;

		/**
		 * @var string
		 */
		protected $sFeatureSlug;

		/**
		 * @var boolean
		 */
		protected static $bForceOffFileExists;

		/**
		 * @var ICWP_WPSF_FeatureHandler_Email
		 */
		protected static $oEmailHandler;

		/**
		 * @var ICWP_WPSF_Processor_Base
		 */
		protected $oFeatureProcessor;

		/**
		 * @var boolean
		 */
		protected $bOverrideState;

		/**
		 * @param ICWP_WPSF_Plugin_Controller $oPluginController
		 * @param array $aFeatureProperties
		 * @throws Exception
		 */
		public function __construct( $oPluginController, $aFeatureProperties = array() ) {
			if ( empty( $oPluginController ) ) {
				throw new Exception();
			}
			$this->oPluginController = $oPluginController;

			if ( isset( $aFeatureProperties['storage_key'] ) ) {
				$this->sOptionsStoreKey = $aFeatureProperties['storage_key'];
			}

			if ( isset( $aFeatureProperties['slug'] ) ) {
				$this->sFeatureSlug = $aFeatureProperties['slug'];
			}

			$nRunPriority = isset( $aFeatureProperties['load_priority'] ) ? $aFeatureProperties['load_priority'] : 100;
			// Handle any upgrades as necessary (only go near this if it's the admin area)
			add_action( 'plugins_loaded', array( $this, 'onWpPluginsLoaded' ), $nRunPriority );
			add_action( 'init', array( $this, 'onWpInit' ), 1 );
			add_action( $this->doPluginPrefix( 'form_submit' ), array( $this, 'handleFormSubmit' ) );
			add_filter( $this->doPluginPrefix( 'filter_plugin_submenu_items' ), array( $this, 'filter_addPluginSubMenuItem' ) );
			add_filter( $this->doPluginPrefix( 'get_feature_summary_data' ), array( $this, 'filter_getFeatureSummaryData' ) );
			add_action( $this->doPluginPrefix( 'plugin_shutdown' ), array( $this, 'action_doFeatureShutdown' ) );
			add_action( $this->doPluginPrefix( 'delete_plugin' ), array( $this, 'deletePluginOptions' )  );
			add_filter( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array( $this, 'aggregateOptionsValues' ) );
			add_filter( $this->doPluginPrefix( 'override_off' ), array( $this, 'fDoCheckForForceOffFile' ) );

			$this->doPostConstruction();
		}

		protected function doPostConstruction() { }

		/**
		 * A action added to WordPress 'plugins_loaded' hook
		 */
		public function onWpPluginsLoaded() {
			if ( $this->getIsMainFeatureEnabled() ) {
				$this->doExecuteProcessor();
			}
		}

		protected function doExecuteProcessor() {
			$oProcessor = $this->getProcessor();
			if ( is_object( $oProcessor ) && $oProcessor instanceof ICWP_WPSF_Processor_Base ) {
				$oProcessor->run();
			}
		}

		/**
		 * A action added to WordPress 'init' hook
		 */
		public function onWpInit() {
			$this->updateHandler();
		}

		/**
		 * Override this and adapt per feature
		 * @return ICWP_WPSF_Processor_Base
		 */
		protected function loadFeatureProcessor() {
			if ( !isset( $this->oFeatureProcessor ) ) {
				require_once( $this->getController()->getPath_SourceFile( sprintf( 'processors/%s.php', $this->getFeatureSlug() ) ) );
				$sClassName = $this->getProcessorClassName();
				if ( !class_exists( $sClassName, false ) ) {
					return null;
				}
				$this->oFeatureProcessor = new $sClassName( $this );
			}
			return $this->oFeatureProcessor;
		}

		/**
		 * Override this and adapt per feature
		 * @return string
		 */
		abstract protected function getProcessorClassName();

		/**
		 * @return ICWP_WPSF_OptionsVO
		 */
		public function getOptionsVo() {
			if ( !isset( $this->oOptions ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'options-vo.php' );
				$this->oOptions = new ICWP_WPSF_OptionsVO( $this->getFeatureSlug() );
				$this->oOptions->setRebuildFromFile( $this->getController()->getIsRebuildOptionsFromFile() );
				$this->oOptions->setOptionsStorageKey( $this->getOptionsStorageKey() );
			}
			return $this->oOptions;
		}

		/**
		 * @return bool
		 */
		public function getIsUpgrading() {
			return $this->getVersion() != $this->getController()->getVersion();
		}

		/**
		 * Hooked to the plugin's main plugin_shutdown action
		 */
		public function action_doFeatureShutdown() {
			if ( ! $this->getIsPluginDeleting() ) {
				$this->savePluginOptions();
			}
		}

		/**
		 * @return bool
		 */
		public function getIsPluginDeleting() {
			return $this->bPluginDeleting;
		}

		/**
		 * @return string
		 */
		protected function getOptionsStorageKey() {
			if ( !isset( $this->sOptionsStoreKey ) ) {
				// not ideal as it doesn't take into account custom storage keys as when passed into the constructor
				$this->sOptionsStoreKey = $this->getOptionsVo()->getFeatureProperty( 'storage_key' );
			}

			return $this->prefixOptionKey( $this->sOptionsStoreKey ).'_options' ;
		}

		/**
		 * @return ICWP_WPSF_Processor_Base
		 */
		public function getProcessor() {
			return $this->loadFeatureProcessor();
		}

		/**
		 * @return ICWP_WPSF_FeatureHandler_Email
		 */
		public function getEmailHandler() {
			if ( is_null( self::$oEmailHandler ) ) {
				self::$oEmailHandler = $this->getController()->loadFeatureHandler( array( 'slug' => 'email' ) );
			}
			return self::$oEmailHandler;
		}

		/**
		 * @return ICWP_WPSF_Processor_Email
		 */
		public function getEmailProcessor() {
			return $this->getEmailHandler()->getProcessor();
		}

		/**
		 * @param bool $bEnable
		 *
		 * @return bool
		 */
		public function setIsMainFeatureEnabled( $bEnable ) {
			return $this->setOpt( 'enable_'.$this->getFeatureSlug(), $bEnable ? 'Y' : 'N' );
		}

		/**
		 * @return mixed
		 */
		public function getIsMainFeatureEnabled() {
			if ( $this->getIfOverrideOff() ) {
				return false;
			}

			$bEnabled = $this->getOptIs( 'enable_'.$this->getFeatureSlug(), 'Y' ) || $this->getOptIs( 'enable_'.$this->getFeatureSlug(), true, true );
			// we have the option to auto-enable a feature
			$bEnabled = $bEnabled || ( $this->getOptionsVo()->getFeatureProperty( 'auto_enabled' ) === true );
			return $bEnabled;
		}

		/**
		 * @param $bOverrideOff
		 *
		 * @return boolean
		 */
		public function fDoCheckForForceOffFile( $bOverrideOff ) {
			if ( $bOverrideOff ) {
				return $bOverrideOff;
			}
			if ( !isset( self::$bForceOffFileExists ) ) {
				self::$bForceOffFileExists = $this->loadFileSystemProcessor()
					->fileExistsInDir( 'forceOff', $this->getController()->getRootDir(), false );
			}
			return self::$bForceOffFileExists;
		}

		/**
		 * Returns true if you're overriding OFF.  We don't do override ON any more (as of 3.5.1)
		 */
		public function getIfOverrideOff() {
			if ( !is_null( $this->bOverrideState ) ) {
				return $this->bOverrideState;
			}
			$this->bOverrideState = apply_filters( $this->doPluginPrefix( 'override_off' ), false );
			return $this->bOverrideState;
		}

		/**
		 * @return string
		 */
		protected function getMainFeatureName() {
			if ( !isset( $this->sFeatureName ) ) {
				$this->sFeatureName = $this->getOptionsVo()->getFeatureProperty( 'name' );
			}
			return $this->sFeatureName;
		}

		/**
		 * @return string
		 */
		public function getFeatureSlug() {
			if ( !isset( $this->sFeatureSlug ) ) {
				$this->sFeatureSlug = $this->getOptionsVo()->getFeatureProperty( 'slug' );
			}
			return $this->sFeatureSlug;
		}

		/**
		 * With trailing slash
		 * @param string $sSourceFile
		 * @return string
		 */
		public function getResourcesDir( $sSourceFile = '' ) {
			return $this->getController()->getRootDir().'resources'.ICWP_DS.ltrim( $sSourceFile, ICWP_DS );
		}

		/**
		 * @param array $aItems
		 * @return array
		 */
		public function filter_addPluginSubMenuItem( $aItems ) {
			$sMenuTitleName = $this->getOptionsVo()->getFeatureProperty( 'menu_title' );
			if ( is_null( $sMenuTitleName ) ) {
				$sMenuTitleName = $this->getMainFeatureName();
			}
			if ( $this->getIfShowFeatureMenuItem() && !empty( $sMenuTitleName ) ) {

				$sHumanName = $this->getController()->getHumanName();

				$sMenuPageTitle = $sHumanName.' - '.$sMenuTitleName;
				$aItems[ $sMenuPageTitle ] = array(
					$sMenuTitleName,
					$this->doPluginPrefix( $this->getFeatureSlug() ),
					array( $this, 'displayFeatureConfigPage' )
				);

				$aAdditionalItems = $this->getOptionsVo()->getAdditionalMenuItems();
				if ( !empty( $aAdditionalItems ) && is_array( $aAdditionalItems ) ) {

					foreach( $aAdditionalItems as $aMenuItem ) {

						if ( empty( $aMenuItem['callback'] ) || !method_exists( $this, $aMenuItem['callback'] ) ) {
							continue;
						}

						$sMenuPageTitle = $sHumanName.' - '.$aMenuItem['title'];
						$aItems[ $sMenuPageTitle ] = array(
							$aMenuItem['title'],
							$this->doPluginPrefix( $aMenuItem['slug'] ),
							array( $this, $aMenuItem['callback'] )
						);
					}
				}
			}
			return $aItems;
		}

		/**
		 * @return array
		 */
		protected function getAdditionalMenuItem() {
			return array();
		}

		/**
		 * @param array $aSummaryData
		 * @return array
		 */
		public function filter_getFeatureSummaryData( $aSummaryData ) {
			if ( !$this->getIfShowFeatureMenuItem() ) {
				return $aSummaryData;
			}

			$aSummaryData[] = array(
				$this->getIsMainFeatureEnabled(),
				$this->getMainFeatureName(),
				$this->doPluginPrefix( $this->getFeatureSlug() )
			);

			return $aSummaryData;
		}

		/**
		 * @return bool
		 */
		public function hasPluginManageRights() {
			if ( !current_user_can( $this->getController()->getBasePermissions() ) ) {
				return false;
			}

			$oWpFunc = $this->loadWpFunctionsProcessor();
			if ( is_admin() && !$oWpFunc->isMultisite() ) {
				return true;
			}
			else if ( is_network_admin() && $oWpFunc->isMultisite() ) {
				return true;
			}
			return false;
		}

		/**
		 * @return boolean
		 */
		public function getIfShowFeatureMenuItem() {
			return $this->getOptionsVo()->getFeatureProperty( 'show_feature_menu_item' );
		}

		/**
		 * @return boolean
		 */
		public function getIfUseSessions() {
			return $this->getOptionsVo()->getFeatureProperty( 'use_sessions' );
		}

		/**
		 * @param string $sOptionKey
		 * @param mixed $mDefault
		 * @return mixed
		 */
		public function getOpt( $sOptionKey, $mDefault = false ) {
			return $this->getOptionsVo()->getOpt( $sOptionKey, $mDefault );
		}

		/**
		 * @param string $sOptionKey
		 * @param mixed $mValueToTest
		 * @param boolean $bStrict
		 *
		 * @return bool
		 */
		public function getOptIs( $sOptionKey, $mValueToTest, $bStrict = false ) {
			$mOptionValue = $this->getOptionsVo()->getOpt( $sOptionKey );
			return $bStrict? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
		}

		/**
		 * Retrieves the full array of options->values
		 *
		 * @return array
		 */
		public function getOptions() {
			return $this->buildOptions();
		}

		/**
		 * @return string
		 */
		public function getVersion() {
			$sVersion = $this->getOpt( self::PluginVersionKey );
			return empty( $sVersion )? $this->getController()->getVersion() : $sVersion;
		}

		/**
		 * Sets the value for the given option key
		 *
		 * @param string $sOptionKey
		 * @param mixed $mValue
		 * @return boolean
		 */
		public function setOpt( $sOptionKey, $mValue ) {
			return $this->getOptionsVo()->setOpt( $sOptionKey, $mValue );
		}

		/**
		 * @param array $aOptions
		 */
		public function setOptions( $aOptions ) {
			foreach( $aOptions as $sKey => $mValue ) {
				$this->setOpt( $sKey, $mValue );
			}
		}

		/**
		 * Saves the options to the WordPress Options store.
		 * It will also update the stored plugin options version.
		 *
		 * @return bool
		 */
		public function savePluginOptions() {
			$this->doPrePluginOptionsSave();
			$this->updateOptionsVersion();
			return $this->getOptionsVo()->doOptionsSave();
		}

		/**
		 * @param array $aAggregatedOptions
		 * @return array
		 */
		public function aggregateOptionsValues( $aAggregatedOptions ) {
			return array_merge( $aAggregatedOptions, $this->getOptionsVo()->getAllOptionsValues() );
		}

		/**
		 * Will initiate the plugin options structure for use by the UI builder.
		 *
		 * It doesn't set any values, just populates the array created in buildOptions()
		 * with values stored.
		 *
		 * It has to handle the conversion of stored values to data to be displayed to the user.
		 */
		public function buildOptions() {

			$aOptions = $this->getOptionsVo()->getLegacyOptionsConfigData();
			foreach ( $aOptions as $nSectionKey => $aOptionsSection ) {

				if ( empty( $aOptionsSection ) || !isset( $aOptionsSection['section_options'] ) ) {
					continue;
				}

				foreach ( $aOptionsSection['section_options'] as $nKey => $aOptionParams ) {

					$sOptionKey = $aOptionParams['key'];
					$sOptionDefault = $aOptionParams['default'];
					$sOptionType = $aOptionParams['type'];

					if ( $this->getOpt( $sOptionKey ) === false ) {
						$this->setOpt( $sOptionKey, $sOptionDefault );
					}
					$mCurrentOptionVal = $this->getOpt( $sOptionKey );

					if ( $sOptionType == 'password' && !empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else if ( $sOptionType == 'array' ) {

						if ( empty( $mCurrentOptionVal ) ) {
							$mCurrentOptionVal = '';
						}
						else {
							$mCurrentOptionVal = implode( "\n", $mCurrentOptionVal );
						}
					}
					else if ( $sOptionType == 'ip_addresses' ) {

						if ( empty( $mCurrentOptionVal ) ) {
							$mCurrentOptionVal = '';
						}
						else {
							$mCurrentOptionVal = implode( "\n", $this->convertIpListForDisplay( $mCurrentOptionVal ) );
						}
					}
					else if ( $sOptionType == 'yubikey_unique_keys' ) {

						if ( empty( $mCurrentOptionVal ) ) {
							$mCurrentOptionVal = '';
						}
						else {
							$aDisplay = array();
							foreach( $mCurrentOptionVal as $aParts ) {
								$aDisplay[] = key($aParts) .', '. reset($aParts);
							}
							$mCurrentOptionVal = implode( "\n", $aDisplay );
						}
					}
					else if ( $sOptionType == 'comma_separated_lists' ) {

						if ( empty( $mCurrentOptionVal ) ) {
							$mCurrentOptionVal = '';
						}
						else {
							$aNewValues = array();
							foreach( $mCurrentOptionVal as $sPage => $aParams ) {
								$aNewValues[] = $sPage.', '. implode( ", ", $aParams );
							}
							$mCurrentOptionVal = implode( "\n", $aNewValues );
						}
					}
					$aOptionParams['value'] = $mCurrentOptionVal;

					// Build strings
					$aParamsWithStrings = $this->loadStrings_Options( $aOptionParams );
					$aOptionsSection['section_options'][$nKey] = $aParamsWithStrings;
				}

				$aOptions[$nSectionKey] = $this->loadStrings_SectionTitles( $aOptionsSection );
			}

			return $aOptions;
		}

		/**
		 * @param $aOptionsParams
		 */
		protected function loadStrings_Options( $aOptionsParams ) {
			return $aOptionsParams;
		}

		/**
		 * @param $aOptionsParams
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {
			return $aOptionsParams;
		}

		/**
		 * This is the point where you would want to do any options verification
		 */
		protected function doPrePluginOptionsSave() { }

		/**
		 */
		protected function updateOptionsVersion() {
			if ( $this->getIsUpgrading() ) {
				$this->setOpt( self::PluginVersionKey, $this->getController()->getVersion() );
				$this->getOptionsVo()->cleanTransientStorage();
			}
		}

		/**
		 * Deletes all the options including direct save.
		 */
		public function deletePluginOptions() {
			if ( apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {
				$this->getOptionsVo()->doOptionsDelete();
				$this->bPluginDeleting = true;
			}
		}

		/**
		 * @param array $aIpList
		 *
		 * @return array
		 */
		protected function convertIpListForDisplay( $aIpList = array() ) {

			$aDisplay = array();
			if ( empty( $aIpList ) || empty( $aIpList['ips'] ) ) {
				return $aDisplay;
			}

			foreach( $aIpList['ips'] as $sAddress ) {
				// offset=1 in the case that it's a range and the first number is negative on 32-bit systems
				$mPos = strpos( $sAddress, '-', 1 );

				if ( $mPos === false ) { //plain IP address
					$sDisplayText = is_long( $sAddress ) ? long2ip( $sAddress ) : $sAddress;
				}
				else {
					//we remove the first character in case this is '-'
					$aParts = array( substr( $sAddress, 0, 1 ), substr( $sAddress, 1 ) );
					list( $nStart, $nEnd ) = explode( '-', $aParts[1], 2 );
					$sDisplayText = long2ip( $aParts[0].$nStart ) .'-'. long2ip( $nEnd );
				}
				$sLabel = $aIpList['meta'][ md5($sAddress) ];
				$sLabel = trim( $sLabel, '()' );
				$aDisplay[] = $sDisplayText . ' ('.$sLabel.')';
			}
			return $aDisplay;
		}

		/**
		 * @return string
		 */
		protected function collateAllFormInputsForAllOptions() {

			$aOptions = $this->buildOptions();

			$aToJoin = array();
			foreach ( $aOptions as $aOptionsSection ) {

				if ( empty( $aOptionsSection ) ) {
					continue;
				}
				foreach ( $aOptionsSection['section_options'] as $aOption ) {
					$aToJoin[] = $aOption['type'].':'.$aOption['key'];
				}
			}
			return implode( self::CollateSeparator, $aToJoin );
		}

		/**
		 */
		public function handleFormSubmit() {
			$bVerified = $this->verifyFormSubmit();

			if ( !$bVerified ) {
				return false;
			}

			$this->doSaveStandardOptions();
			$this->doExtraSubmitProcessing();
			return true;
		}

		protected function verifyFormSubmit() {
			if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {
//				TODO: manage how we react to prohibited submissions
				return false;
			}

			// Now verify this is really a valid submission.
			return check_admin_referer( $this->getController()->getPluginPrefix() );
		}

		/**
		 * @return bool
		 */
		protected function doSaveStandardOptions() {
			$oDp = $this->loadDataProcessor();
			$sAllOptions = $oDp->FetchPost( $this->prefixOptionKey( 'all_options_input' ) );

			if ( empty( $sAllOptions ) ) {
				return true;
			}
			return $this->updatePluginOptionsFromSubmit( $sAllOptions ); //it also saves
		}

		protected function doExtraSubmitProcessing() { }

		/**
		 * Should be used sparingly - it allows immediate on-demand saving of plugin options that by-passes checking from
		 * the admin access restriction feature.
		 */
		protected function doSaveByPassAdminProtection() {
			add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), '__return_true' );
			$this->savePluginOptions();
			remove_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), '__return_true' );
		}

		/**
		 * @param string $sAllOptionsInput - comma separated list of all the input keys to be processed from the $_POST
		 * @return void|boolean
		 */
		public function updatePluginOptionsFromSubmit( $sAllOptionsInput ) {
			if ( empty( $sAllOptionsInput ) ) {
				return true;
			}
			$oDp = $this->loadDataProcessor();

			$aAllInputOptions = explode( self::CollateSeparator, $sAllOptionsInput );
			foreach ( $aAllInputOptions as $sInputKey ) {
				$aInput = explode( ':', $sInputKey );
				list( $sOptionType, $sOptionKey ) = $aInput;

				$sOptionValue = $oDp->FetchPost( $this->prefixOptionKey( $sOptionKey ) );
				if ( is_null( $sOptionValue ) ) {

					if ( $sOptionType == 'text' || $sOptionType == 'email' ) { //if it was a text box, and it's null, don't update anything
						continue;
					}
					else if ( $sOptionType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
						$sOptionValue = 'N';
					}
					else if ( $sOptionType == 'integer' ) { //if it was a integer, and it's null, it means '0'
						$sOptionValue = 0;
					}
				}
				else { //handle any pre-processing we need to.

					if ( $sOptionType == 'text' || $sOptionType == 'email' ) {
						$sOptionValue = trim( $sOptionValue );
					}
					if ( $sOptionType == 'integer' ) {
						$sOptionValue = intval( $sOptionValue );
					}
					else if ( $sOptionType == 'password' && $this->hasEncryptOption() ) { //md5 any password fields
						$sTempValue = trim( $sOptionValue );
						if ( empty( $sTempValue ) ) {
							continue;
						}
						$sOptionValue = md5( $sTempValue );
					}
					else if ( $sOptionType == 'array' ) { //arrays are textareas, where each is separated by newline
						$sOptionValue = array_filter( explode( "\n", $sOptionValue ), 'trim' );
					}
					else if ( $sOptionType == 'ip_addresses' ) { //ip addresses are textareas, where each is separated by newline
						$sOptionValue = $oDp->extractIpAddresses( $sOptionValue );
					}
					else if ( $sOptionType == 'yubikey_unique_keys' ) { //ip addresses are textareas, where each is separated by newline and are 12 chars long
						$sOptionValue = $oDp->CleanYubikeyUniqueKeys( $sOptionValue );
					}
					else if ( $sOptionType == 'email' && function_exists( 'is_email' ) && !is_email( $sOptionValue ) ) {
						$sOptionValue = '';
					}
					else if ( $sOptionType == 'comma_separated_lists' ) {
						$sOptionValue = $oDp->extractCommaSeparatedList( $sOptionValue );
					}
					else if ( $sOptionType == 'multiple_select' ) {
					}
				}
				$this->setOpt( $sOptionKey, $sOptionValue );
			}
			return $this->savePluginOptions();
		}

		/**
		 * Should be over-ridden by each new class to handle upgrades.
		 *
		 * Called upon construction and after plugin options are initialized.
		 */
		protected function updateHandler() {
			if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
				$sKey = $this->doPluginPrefix( $this->getFeatureSlug().'_processor', '_' );
				$this->loadWpFunctionsProcessor()->deleteOption( $sKey );
			}
		}

		/**
		 * @return boolean
		 */
		public function hasEncryptOption() {
			return function_exists( 'md5' );
			//	return extension_loaded( 'mcrypt' );
		}

		/**
		 * Prefixes an option key only if it's needed
		 *
		 * @param $sKey
		 * @return string
		 */
		public function prefixOptionKey( $sKey ) {
			return $this->doPluginPrefix( $sKey, '_' );
		}

		/**
		 * Will prefix and return any string with the unique plugin prefix.
		 *
		 * @param string $sSuffix
		 * @param string $sGlue
		 * @return string
		 */
		public function doPluginPrefix( $sSuffix = '', $sGlue = '-' ) {
			return $this->getController()->doPluginPrefix( $sSuffix, $sGlue );
		}

		/**
		 * @param string
		 * @return string
		 */
		public function getOptionStoragePrefix() {
			return $this->getController()->getOptionStoragePrefix();
		}

		/**
		 * @param string $sExistingListKey
		 * @param string $sFilterName
		 * @return array|false
		 */
		protected function processIpFilter( $sExistingListKey, $sFilterName ) {
			$aFilterIps = apply_filters( $sFilterName, array() );
			if ( empty( $aFilterIps ) ) {
				return false;
			}

			$aNewIps = array();
			foreach( $aFilterIps as $mKey => $sValue ) {
				if ( is_string( $mKey ) ) { //it's the IP
					$sIP = $mKey;
					$sLabel = $sValue;
				}
				else { //it's not an associative array, so the value is the IP
					$sIP = $sValue;
					$sLabel = '';
				}
				$aNewIps[ $sIP ] = $sLabel;
			}

			// now add and store the new IPs
			$aExistingIpList = $this->getOpt( $sExistingListKey );
			if ( !is_array( $aExistingIpList ) ) {
				$aExistingIpList = array();
			}

			$oDp = $this->loadDataProcessor();
			$nNewAddedCount = 0;
			$aNewList = $oDp->addNewRawIps( $aExistingIpList, $aNewIps, $nNewAddedCount );
			if ( $nNewAddedCount > 0 ) {
				$this->setOpt( $sExistingListKey, $aNewList );
			}
			return true;
		}

		/**
		 */
		public function displayFeatureConfigPage() {
			$this->display();
		}

		/**
		 * @return bool
		 */
		public function getIsCurrentPageConfig() {
			$oWpFunctions = $this->loadWpFunctionsProcessor();
			return $oWpFunctions->getCurrentWpAdminPage() == $this->doPluginPrefix( $this->getFeatureSlug() );
		}

		/**
		 * @return array
		 */
		protected function getBaseDisplayData() {
			$oCon = $this->getController();
			return array(
				'var_prefix'		=> $oCon->getOptionStoragePrefix(),
				'sPluginName'		=> $oCon->getHumanName(),
				'sFeatureName'		=> $this->getMainFeatureName(),
				'fShowAds'			=> $this->getIsShowMarketing(),
				'nonce_field'		=> $oCon->getPluginPrefix(),
				'sFeatureSlug'		=> $this->doPluginPrefix( $this->getFeatureSlug() ),
				'form_action'		=> 'admin.php?page='.$this->doPluginPrefix( $this->getFeatureSlug() ),
				'nOptionsPerRow'	=> 1,
				'aPluginLabels'		=> $oCon->getPluginLabels(),

				'aAllOptions'		=> $this->buildOptions(),
				'aHiddenOptions'	=> $this->getOptionsVo()->getHiddenOptions(),
				'all_options_input'	=> $this->collateAllFormInputsForAllOptions()
			);
		}

		/**
		 * @return boolean
		 */
		protected function getIsShowMarketing() {
			return apply_filters( $this->doPluginPrefix( 'show_marketing' ), true );
		}

		/**
		 * @param array $aData
		 * @param string $sSubView
		 * @return bool
		 */
		protected function display( $aData = array(), $sSubView = '' ) {

			// Get Base Data
			$aData = apply_filters( $this->doPluginPrefix( $this->getFeatureSlug().'display_data' ), array_merge( $this->getBaseDisplayData(), $aData ) );
			$bPermissionToView = apply_filters( $this->doPluginPrefix( 'has_permission_to_view' ), true );

			if ( !$bPermissionToView ) {
				$sSubView = 'subfeature-access_restricted';
			}

			if ( empty( $sSubView ) ) {
				$oWpFs = $this->loadFileSystemProcessor();
				$sFeatureInclude = 'feature-'.$this->getFeatureSlug();
				if ( $oWpFs->exists( $this->getController()->getPath_ViewsFile( $sFeatureInclude ) ) ) {
					$sSubView = $sFeatureInclude;
				}
				else {
					$sSubView = 'feature-default';
				}
			}
			$aData[ 'sFeatureInclude' ] = $sSubView;

			$sFile = $this->getController()->getPath_ViewsFile( 'config_index' );
			if ( !is_file( $sFile ) ) {
				echo "View not found: ".$sFile;
				return false;
			}

			if ( count( $aData ) > 0 ) {
				extract( $aData, EXTR_PREFIX_ALL, $this->getController()->getParentSlug() ); //slug being 'icwp'
			}

			ob_start();
			include( $sFile );
			$sContents = ob_get_contents();
			ob_end_clean();

			echo $sContents;
			return true;
		}

		/**
		 * @param string $sSnippet
		 * @return string
		 */
		public function getViewSnippet( $sSnippet = '' ) {
			return $this->getController()->getPath_ViewsSnippet( $sSnippet );
		}

		/**
		 * @param $sStatKey
		 */
		public function doStatIncrement( $sStatKey ) {
			$this->loadStatsProcessor();
			ICWP_Stats_WPSF::DoStatIncrement( $sStatKey );
		}

		/**
		 * @return ICWP_WPSF_Plugin_Controller
		 */
		public function getController() {
			return $this->oPluginController;
		}
	}

endif;

abstract class ICWP_WPSF_FeatureHandler_Base extends ICWP_WPSF_FeatureHandler_Base_V3 { }