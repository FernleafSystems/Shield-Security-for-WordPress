<?php
if ( class_exists( 'ICWP_WPSF_OptionsVO', false ) ) {
	return;
}

class ICWP_WPSF_OptionsVO extends ICWP_WPSF_Foundation {

	/**
	 * @var array
	 */
	protected $aOptionsValues;
	/**
	 * @var array
	 */
	protected $aRawOptionsConfigData;
	/**
	 * @var boolean
	 */
	protected $bNeedSave;
	/**
	 * @var boolean
	 */
	protected $bIsPremium;
	/**
	 * @var boolean
	 */
	protected $bRebuildFromFile = false;
	/**
	 * @var string
	 */
	protected $aOptionsKeys;
	/**
	 * @var string
	 */
	protected $sOptionsStorageKey;
	/**
	 *  by default we load from saved
	 * @var string
	 */
	protected $bLoadFromSaved = true;
	/**
	 * @var string
	 */
	protected $sOptionsEncoding;
	/**
	 * @var string
	 */
	protected $sOptionsName;

	/**
	 * @param string $sOptionsName
	 */
	public function __construct( $sOptionsName ) {
		$this->sOptionsName = $sOptionsName;
	}

	/**
	 * @return bool
	 */
	public function cleanTransientStorage() {
		return $this->loadWpFunctions()->deleteTransient( $this->getSpecTransientStorageKey() );
	}

	/**
	 * @param bool $bDeleteFirst Used primarily with plugin reset
	 * @return bool
	 */
	public function doOptionsSave( $bDeleteFirst = false ) {
		if ( !$this->getNeedSave() ) {
			return true;
		}
		$this->cleanOptions();
		if ( !$this->isPremium() ) {
			$this->resetPremiumOptsToDefault();
		}
		$this->setNeedSave( false );
		if ( $bDeleteFirst ) {
			$this->loadWpFunctions()->deleteOption( $this->getOptionsStorageKey() );
		}
		return $this->loadWpFunctions()->updateOption( $this->getOptionsStorageKey(), $this->getAllOptionsValues() );
	}

	/**
	 * @return bool
	 */
	public function doOptionsDelete() {
		$oWp = $this->loadWpFunctions();
		$oWp->deleteTransient( $this->getSpecTransientStorageKey() );
		return $oWp->deleteOption( $this->getOptionsStorageKey() );
	}

	/**
	 * @return array
	 */
	public function getAllOptionsValues() {
		return $this->getStoredOptions();
	}

	/**
	 * Returns an array of all the transferable options and their values
	 * @return array
	 */
	public function getTransferableOptions() {

		$aOptions = $this->getAllOptionsValues();
		$aRawOptions = $this->getRawData_AllOptions();
		$aTransferable = array();
		foreach ( $aRawOptions as $nKey => $aOptionData ) {
			if ( !isset( $aOptionData[ 'transferable' ] ) || $aOptionData[ 'transferable' ] === true ) {
				$aTransferable[ $aOptionData[ 'key' ] ] = $aOptions[ $aOptionData[ 'key' ] ];
			}
		}
		return $aTransferable;
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 * @return array
	 */
	public function getOptionsMaskSensitive() {

		$aOptions = $this->getAllOptionsValues();
		foreach ( $this->getOptionsKeys() as $sKey ) {
			if ( !isset( $aOptions[ $sKey ] ) ) {
				$aOptions[ $sKey ] = $this->getOptDefault( $sKey );
			}
		}
		foreach ( $this->getRawData_AllOptions() as $nKey => $aOptionData ) {
			if ( isset( $aOptionData[ 'sensitive' ] ) && $aOptionData[ 'sensitive' ] === true ) {
				unset( $aOptions[ $aOptionData[ 'key' ] ] );
			}
		}
		return $aOptions;
	}

	/**
	 * @param $sProperty
	 * @return null|mixed
	 */
	public function getFeatureProperty( $sProperty ) {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'properties' ] ) && isset( $aRawConfig[ 'properties' ][ $sProperty ] ) ) ? $aRawConfig[ 'properties' ][ $sProperty ] : null;
	}

	/**
	 * @param string
	 * @return null|array
	 */
	public function getFeatureDefinition( $sDefinition ) {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'definitions' ] ) && isset( $aRawConfig[ 'definitions' ][ $sDefinition ] ) ) ? $aRawConfig[ 'definitions' ][ $sDefinition ] : null;
	}

	/**
	 * @param string $sReq
	 * @return null|mixed
	 */
	public function getFeatureRequirement( $sReq ) {
		$aReqs = $this->getRawData_Requirements();
		return ( is_array( $aReqs ) && isset( $aReqs[ $sReq ] ) ) ? $aReqs[ $sReq ] : null;
	}

	/**
	 * @return array
	 */
	public function getAdminNotices() {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'admin_notices' ] ) && is_array( $aRawConfig[ 'admin_notices' ] ) ) ? $aRawConfig[ 'admin_notices' ] : array();
	}

	/**
	 * @return string
	 */
	public function getFeatureTagline() {
		return $this->getFeatureProperty( 'tagline' );
	}

	/**
	 * @return boolean
	 */
	public function getIfLoadOptionsFromStorage() {
		return $this->bLoadFromSaved;
	}

	/**
	 * Determines whether the given option key is a valid option
	 * @param string
	 * @return boolean
	 */
	public function getIsValidOptionKey( $sOptionKey ) {
		return in_array( $sOptionKey, $this->getOptionsKeys() );
	}

	/**
	 * @return array
	 */
	public function getHiddenOptions() {

		$aRawData = $this->getRawData_FullFeatureConfig();
		$aOptionsData = array();

		foreach ( $aRawData[ 'sections' ] as $nPosition => $aRawSection ) {

			// if hidden isn't specified we skip
			if ( !isset( $aRawSection[ 'hidden' ] ) || !$aRawSection[ 'hidden' ] ) {
				continue;
			}
			foreach ( $this->getRawData_AllOptions() as $aRawOption ) {

				if ( $aRawOption[ 'section' ] != $aRawSection[ 'slug' ] ) {
					continue;
				}
				$aOptionsData[ $aRawOption[ 'key' ] ] = $this->getOpt( $aRawOption[ 'key' ] );
			}
		}
		return $aOptionsData;
	}

	/**
	 * @return array
	 */
	public function getOptionsForPluginUse() {

		$aOptionsData = array();

		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {

			if ( isset( $aRawSection[ 'hidden' ] ) && $aRawSection[ 'hidden' ] ) {
				continue;
			}

			$aRawSection = array_merge(
				array(
					'primary'       => false,
					'options'       => $this->getOptionsForSection( $aRawSection[ 'slug' ] ),
					'help_video_id' => ''
				),
				$aRawSection
			);

			if ( !empty( $aRawSection[ 'options' ] ) ) {
				$aOptionsData[] = $aRawSection;
			}
		}

		return $aOptionsData;
	}

	/**
	 * @param string $sSectionSlug
	 * @return array[]
	 */
	protected function getOptionsForSection( $sSectionSlug ) {

		$aAllOptions = array();
		foreach ( $this->getRawData_AllOptions() as $aOptionDef ) {

			if ( ( $aOptionDef[ 'section' ] != $sSectionSlug ) || ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) ) {
				continue;
			}

			if ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) {
				continue;
			}

			$aOptionDef = array_merge(
				array(
					'link_info'     => '',
					'link_blog'     => '',
					'help_video_id' => '',
					'value_options' => array()
				),
				$aOptionDef
			);
			$aOptionDef[ 'value' ] = $this->getOpt( $aOptionDef[ 'key' ] );

			if ( in_array( $aOptionDef[ 'type' ], array( 'select', 'multiple_select' ) ) ) {
				$aNewValueOptions = array();
				foreach ( $aOptionDef[ 'value_options' ] as $aValueOptions ) {
					$aNewValueOptions[ $aValueOptions[ 'value_key' ] ] = $aValueOptions[ 'text' ];
				}
				$aOptionDef[ 'value_options' ] = $aNewValueOptions;
			}

			$aAllOptions[] = $aOptionDef;
		}
		return $aAllOptions;
	}

	/**
	 * @return array
	 */
	public function getAdditionalMenuItems() {
		return $this->getRawData_MenuItems();
	}

	/**
	 * @return string
	 */
	public function getNeedSave() {
		return $this->bNeedSave;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	public function getOpt( $sOptionKey, $mDefault = false ) {
		$aOptionsValues = $this->getAllOptionsValues();
		if ( !isset( $aOptionsValues[ $sOptionKey ] ) ) {
			$this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey, $mDefault ) );
		}
		return $this->aOptionsValues[ $sOptionKey ];
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function getOptDefault( $sOptionKey, $mDefault = null ) {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( $aOption[ 'key' ] == $sOptionKey ) {
				if ( isset( $aOption[ 'value' ] ) ) {
					return $aOption[ 'value' ];
				}
				else if ( isset( $aOption[ 'default' ] ) ) {
					return $aOption[ 'default' ];
				}
			}
		}
		return $mDefault;
	}

	/**
	 * @param         $sKey
	 * @param mixed   $mValueToTest
	 * @param boolean $bStrict
	 * @return bool
	 */
	public function getOptIs( $sKey, $mValueToTest, $bStrict = false ) {
		$mOptionValue = $this->getOpt( $sKey );
		return $bStrict ? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * @param string $sKey
	 * @return string|null
	 */
	public function getOptionType( $sKey ) {
		$aDef = $this->getRawData_SingleOption( $sKey );
		if ( !empty( $aDef ) && isset( $aDef[ 'type' ] ) ) {
			return $aDef[ 'type' ];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getOptionsEncoding() {
		return empty( $this->sOptionsEncoding ) ? 'json' : $this->sOptionsEncoding;
	}

	/**
	 * @return array
	 */
	public function getOptionsKeys() {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = array();
			foreach ( $this->getRawData_AllOptions() as $aOption ) {
				$this->aOptionsKeys[] = $aOption[ 'key' ];
			}
			$this->aOptionsKeys = array_merge( $this->aOptionsKeys, $this->getCommonStandardOptions() );
		}
		return $this->aOptionsKeys;
	}

	/**
	 * @return string
	 */
	public function getOptionsName() {
		return $this->sOptionsName;
	}

	/**
	 * @return string
	 */
	public function getOptionsStorageKey() {
		return $this->sOptionsStorageKey;
	}

	/**
	 * @param string $sOptKey
	 * @param string $sProperty
	 * @return mixed|null
	 */
	public function getOptProperty( $sOptKey, $sProperty ) {
		$aOpt = $this->getRawData_SingleOption( $sOptKey );
		return ( is_array( $aOpt ) && isset( $aOpt[ $sProperty ] ) ) ? $aOpt[ $sProperty ] : null;
	}

	/**
	 * @return array
	 */
	public function getStoredOptions() {
		try {
			return $this->loadOptionsValuesFromStorage();
		}
		catch ( Exception $oE ) {
			return array();
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getRawData_FullFeatureConfig() {
		if ( empty( $this->aRawOptionsConfigData ) ) {
			$this->aRawOptionsConfigData = $this->readConfiguration();
		}
		return $this->aRawOptionsConfigData;
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_AllOptions() {
		$aRaw = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRaw[ 'options' ] ) && is_array( $aRaw[ 'options' ] ) ) ? $aRaw[ 'options' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_OptionsSections() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'sections' ] ) ? $aAllRawOptions[ 'sections' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_Requirements() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'requirements' ] ) ? $aAllRawOptions[ 'requirements' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_MenuItems() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'menu_items' ] ) ? $aAllRawOptions[ 'menu_items' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @param string $sOptionKey
	 * @return array
	 */
	public function getRawData_SingleOption( $sOptionKey ) {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( isset( $aOption[ 'key' ] ) && ( $sOptionKey == $aOption[ 'key' ] ) ) {
				return $aOption;
			}
		}
		return null;
	}

	/**
	 * @return boolean
	 */
	public function getRebuildFromFile() {
		return $this->bRebuildFromFile;
	}

	/**
	 * @return bool
	 */
	public function isAccessRestricted() {
		$bAccessRestricted = $this->getFeatureProperty( 'access_restricted' );
		return is_null( $bAccessRestricted ) ? true : (bool)$bAccessRestricted;
	}

	/**
	 * @return bool
	 */
	public function isModulePremium() {
		return (bool)$this->getFeatureProperty( 'premium' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool true if premium is set and true, false otherwise.
	 */
	public function isOptPremium( $sOptionKey ) {
		return (bool)$this->getOptProperty( $sOptionKey, 'premium' );
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return (bool)$this->bIsPremium;
	}

	/**
	 * @param string $sOptionKey
	 * @return boolean
	 */
	public function resetOptToDefault( $sOptionKey ) {
		return $this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey ) );
	}

	/**
	 * Will traverse each premium option and set it to the default.
	 */
	public function resetPremiumOptsToDefault() {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( isset( $aOption[ 'premium' ] ) && $aOption[ 'premium' ] ) {
				$this->resetOptToDefault( $aOption[ 'key' ] );
			}
		}
	}

	/**
	 * @param $bIsPremium
	 * @return $this
	 */
	public function setIsPremium( $bIsPremium ) {
		$this->bIsPremium = $bIsPremium;
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function setOptionsStorageKey( $sKey ) {
		$this->sOptionsStorageKey = $sKey;
		return $this;
	}

	/**
	 * @param boolean $bLoadFromSaved
	 * @return $this
	 */
	public function setIfLoadOptionsFromStorage( $bLoadFromSaved ) {
		$this->bLoadFromSaved = $bLoadFromSaved;
		return $this;
	}

	/**
	 * @param boolean $bNeed
	 */
	public function setNeedSave( $bNeed ) {
		$this->bNeedSave = $bNeed;
	}

	/**
	 * @param string $sOptionsEncoding
	 * @return $this
	 */
	public function setOptionsEncoding( $sOptionsEncoding ) {
		$this->sOptionsEncoding = $sOptionsEncoding;
		return $this;
	}

	/**
	 * @param boolean $bRebuild
	 * @return $this
	 */
	public function setRebuildFromFile( $bRebuild ) {
		$this->bRebuildFromFile = $bRebuild;
		return $this;
	}

	/**
	 * @param array $aOptions
	 */
	public function setMultipleOptions( $aOptions ) {
		if ( is_array( $aOptions ) ) {
			foreach ( $aOptions as $sKey => $mValue ) {
				$this->setOpt( $sKey, $mValue );
			}
		}
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mValue
	 * @return mixed
	 */
	public function setOpt( $sOptionKey, $mValue ) {

		// We can't use getOpt() to find the current value since we'll create an infinite loop
		$aOptionsValues = $this->getAllOptionsValues();
		$mCurrent = isset( $aOptionsValues[ $sOptionKey ] ) ? $aOptionsValues[ $sOptionKey ] : null;

		if ( serialize( $mCurrent ) !== serialize( $mValue ) ) {
			$this->setNeedSave( true );

			//Load the config and do some pre-set verification where possible. This will slowly grow.
			$aOption = $this->getRawData_SingleOption( $sOptionKey );
			if ( !empty( $aOption[ 'type' ] ) ) {
				if ( $aOption[ 'type' ] == 'boolean' && !is_bool( $mValue ) ) {
					return $this->resetOptToDefault( $sOptionKey );
				}
			}
			$this->aOptionsValues[ $sOptionKey ] = $mValue;
		}
		return true;
	}

	/**
	 * @param string $sOptionKey
	 * @return mixed
	 */
	public function unsetOpt( $sOptionKey ) {

		unset( $this->aOptionsValues[ $sOptionKey ] );
		$this->setNeedSave( true );
		return true;
	}

	/** PRIVATE STUFF */

	/**
	 * @return array
	 */
	protected function getCommonStandardOptions() {
		return array( 'current_plugin_version', 'help_video_options' );
	}

	/**
	 */
	private function cleanOptions() {
		if ( !empty( $this->aOptionsValues ) && is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = array_intersect_key(
				$this->getAllOptionsValues(),
				array_flip( $this->getOptionsKeys() )
			);
		}
	}

	/**
	 * @param bool $bReload
	 * @return array|mixed
	 * @throws Exception
	 */
	private function loadOptionsValuesFromStorage( $bReload = false ) {

		if ( $bReload || empty( $this->aOptionsValues ) ) {

			if ( $this->getIfLoadOptionsFromStorage() ) {

				$sStorageKey = $this->getOptionsStorageKey();
				if ( empty( $sStorageKey ) ) {
					throw new Exception( 'Options Storage Key Is Empty' );
				}
				$this->aOptionsValues = $this->loadWpFunctions()->getOption( $sStorageKey, array() );
			}
		}
		if ( !is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = array();
			$this->setNeedSave( true );
		}
		return $this->aOptionsValues;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readConfiguration() {
		$oWp = $this->loadWpFunctions();

		$sTransientKey = $this->getSpecTransientStorageKey();
		$aConfig = $oWp->getTransient( $sTransientKey );

		if ( $this->getRebuildFromFile() || empty( $aConfig ) ) {

			try {
				$aConfig = $this->readConfigurationJson();
			}
			catch ( Exception $oE ) {
				trigger_error( $oE->getMessage() );
				$aConfig = array();
			}
			$oWp->setTransient( $sTransientKey, $aConfig, DAY_IN_SECONDS );
		}
		return $aConfig;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readConfigurationJson() {
		$aConfig = json_decode( $this->readConfigurationFileContents(), true );
		if ( empty( $aConfig ) ) {
			throw new Exception( 'Reading JSON configuration from file failed.' );
		}
		return $aConfig;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function readConfigurationFileContents() {
		if ( !$this->getConfigFileExists() ) {
			throw new Exception( sprintf( 'Configuration file "%s" does not exist.', $this->getConfigFilePath() ) );
		}
		return $this->loadDataProcessor()->readFileContentsUsingInclude( $this->getConfigFilePath() );
	}

	/**
	 * @return string
	 */
	private function getSpecTransientStorageKey() {
		return 'icwp_'.md5( $this->getConfigFilePath() );
	}

	/**
	 * @return bool
	 */
	private function getConfigFileExists() {
		$sPath = $this->getConfigFilePath();
		return !empty( $sPath ) && $this->loadFS()->isFile( $sPath );
	}

	/**
	 * @return string
	 */
	private function getConfigFilePath() {
		return realpath( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR
						 .sprintf( 'config'.DIRECTORY_SEPARATOR.'feature-%s.%s', $this->getOptionsName(), 'php' )
		);
	}
}