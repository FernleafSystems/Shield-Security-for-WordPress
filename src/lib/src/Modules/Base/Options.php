<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\ModConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Options {

	use ModConsumer;

	/**
	 * @var array
	 */
	protected $aOld;

	/**
	 * @var bool
	 */
	protected $bNeedSave = false;

	/**
	 * @var string[]
	 */
	protected $aOptionsKeys;

	/**
	 * @deprecated 19.1
	 */
	public function getAllOptionsValues() :array {
		$opts = self::con()->opts;
		try {
			if ( \method_exists( $opts, 'values' ) ) {
				$values = $opts->values();
			}
			else {
				$values = self::con()->opts->getFor( $this->mod() );
			}
			if ( $values === null ) {
				throw new \Exception( 'No shared-stored options available' );
			}
		}
		catch ( \Exception $e ) {
			$values = [];
			self::con()->opts->setFor( $this->mod(), $values );
		}
		return $values;
	}

	/**
	 * Returns an array of all the transferable options and their values
	 * @deprecated 19.1
	 */
	public function getTransferableOptions() :array {
		$transferable = [];
		foreach ( $this->mod()->cfg->options as $option ) {
			if ( $option[ 'transferable' ] ?? true ) {
				$transferable[ $option[ 'key' ] ] = $this->getOpt( $option[ 'key' ] );
			}
		}
		return $transferable;
	}

	/**
	 * @return mixed|null
	 * @deprecated 19.1
	 */
	public function getDef( string $key ) {
		$config = self::con()->cfg->configuration;
		return empty( $config ) ? ( $this->mod()->cfg->definitions[ $key ] ?? null ) : $config->def( $key );
	}

	/**
	 * @return array[]
	 * @deprecated 19.1
	 */
	public function getHiddenOptions() :array {
		$optionsData = [];

		foreach ( $this->mod()->cfg->sections as $rawSection ) {

			// if hidden isn't specified we skip
			if ( !isset( $rawSection[ 'hidden' ] ) || !$rawSection[ 'hidden' ] ) {
				continue;
			}
			foreach ( $this->mod()->cfg->options as $rawOption ) {

				if ( $rawOption[ 'section' ] != $rawSection[ 'slug' ] ) {
					continue;
				}
				$optionsData[ $rawOption[ 'key' ] ] = $this->getOpt( $rawOption[ 'key' ] );
			}
		}
		return $optionsData;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getNeedSave() :bool {
		return $this->bNeedSave;
	}

	/**
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getOpt( string $key, $mDefault = false ) {
		if ( \method_exists( self::con()->opts, 'optGet' ) ) {
			$value = self::con()->opts->optGet( $key );
		}
		else {
			$value = $this->getAllOptionsValues()[ $key ] ?? null;

			if ( $value === null ) {
				$this->resetOptToDefault( $key );
			}

			$cap = $this->optCap( $key );
			if ( empty( $cap ) || self::con()->caps->hasCap( $cap ) ) {
				$value = $this->getAllOptionsValues()[ $key ] ?? $mDefault;
			}
			else {
				$value = $this->getOptDefault( $key, $mDefault );
			}
		}
		return $value;
	}

	/**
	 * @param mixed $mDefault
	 * @return mixed|null
	 * @deprecated 19.1
	 */
	public function getOptDefault( string $key, $mDefault = null ) {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optDefault' ) ? $opts->optDefault( $key )
			: ( $this->getOptDefinition( $key )[ 'default' ] ?? $mDefault );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptDefinition( string $key ) :array {
		return self::con()->cfg->configuration->options[ 'key' ] ?? ( $this->mod()->cfg->options[ $key ] ?? [] );
	}

	/**
	 * @deprecated 19.1
	 */
	public function optCap( string $key ) :?string {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optCap' ) ? $opts->optCap( $key ) : ( $this->getOptDefinition( $key )[ 'cap' ] ?? null );
	}

	/**
	 * @param mixed $value
	 * @deprecated 19.1
	 */
	public function isOpt( string $key, $value ) :bool {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optIs' ) ? $opts->optIs( $key, $value ) : $this->getOpt( $key ) == $value;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptionType( string $key ) :?string {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optType' ) ? $opts->optType( $key ) : ( $this->getOptDefinition( $key )[ 'type' ] ?? null );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptionsKeys() :array {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = \array_merge(
				\array_keys( $this->mod()->cfg->options ),
				$this->getVirtualCommonOptions()
			);
		}
		return $this->aOptionsKeys;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptProperty( string $key, string $prop ) {
		return $this->getOptDefinition( $key )[ $prop ] ?? null;
	}

	/**
	 * @deprecated 19.1
	 */
	public function cfg() :ModConfigVO {
		return $this->mod()->cfg;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isOptChanged( string $key ) :bool {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optChanged' ) ? $opts->optChanged( $key ) : ( \is_array( $this->aOld ) && isset( $this->aOld[ $key ] ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function optExists( string $key ) :bool {
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optExists' ) ? $opts->optExists( $key ) : !empty( $this->getOptDefinition( $key ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function resetOptToDefault( string $key ) {
		$opts = self::con()->opts;
		\method_exists( $opts, 'optReset' ) ? $opts->optReset( $key ) : ( $this->setOpt( $key, $this->getOptDefault( $key ) ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function setNeedSave( bool $need ) {
		$this->bNeedSave = $need;
	}

	/**
	 * @param mixed $newValue
	 * @return $this
	 * @deprecated 19.1
	 */
	public function setOpt( string $key, $newValue ) :self {
		if ( \method_exists( self::con()->opts, 'optSet' ) ) {
			self::con()->opts->optSet( $key, $newValue );
		}
		else {
			// NOTE: can't use getOpt() for current value as it'll create infinite loop
			$mCurrent = $this->getAllOptionsValues()[ $key ] ?? null;
			if ( \serialize( $mCurrent ) !== \serialize( $newValue ) ) {
				$this->setOldOptValue( $key, $mCurrent )
					 ->setOptValue( $key, $newValue );
			}
		}

		return $this;
	}

	/**
	 * @param mixed $newValue
	 * @throws \Exception
	 * @deprecated 19.1
	 */
	protected function preSetOptChecks( string $key, $newValue ) {
	}

	/**
	 * @deprecated 19.1
	 */
	public function preSave() :void {
	}

	/**
	 * Use this to directly set the option value without the risk of any recursion.
	 * @param mixed $value
	 * @return $this
	 * @deprecated 19.1
	 */
	protected function setOptValue( string $key, $value ) {
		$values = $this->getAllOptionsValues();
		$values[ $key ] = $value;
		return $this->setOptionsValues( $values );
	}

	/**
	 * @param mixed $mPotentialValue
	 * @deprecated 19.1
	 */
	private function verifyCanSet( string $key, $mPotentialValue ) :bool {
		return true;
	}

	/**
	 * @param mixed $value
	 * @return $this
	 * @deprecated 19.1
	 */
	private function setOldOptValue( string $key, $value ) {
		if ( !\is_array( $this->aOld ) ) {
			$this->aOld = [];
		}
		if ( !isset( $this->aOld[ $key ] ) ) {
			$this->aOld[ $key ] = $value;
		}
		return $this;
	}

	protected function getVirtualCommonOptions() :array {
		return [
			'xfer_excluded',
		];
	}

	/**
	 * @return string[]
	 */
	public function getXferExcluded() :array {
		$optsCon = self::con()->opts;
		return \method_exists( $optsCon, 'getXferExcluded' ) ? $optsCon->getXferExcluded() :
			( \is_array( $this->getOpt( 'xfer_excluded' ) ) ? $this->getOpt( 'xfer_excluded' ) : [] );
	}

	/**
	 * @deprecated 19.1
	 */
	public function resetChangedOpts() {
		$this->aOld = [];
	}

	/**
	 * @return $this
	 * @deprecated 19.1
	 */
	public function setOptionsValues( array $values = [] ) {
		self::con()->opts->setFor( $this->mod(), \array_intersect_key( $values, \array_flip( $this->getOptionsKeys() ) ) );
		return $this;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getEvents() :array {
		return \is_array( $this->getDef( 'events' ) ) ? $this->getDef( 'events' ) : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function ensureOptValueType( string $key, $value ) {
		return $value;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSection( string $section ) :?array {
		return $this->getSections()[ $section ] ?? null;
	}

	/**
	 * @return array[]
	 * @deprecated 19.1
	 */
	public function getSections() :array {
		$sections = [];
		foreach ( $this->mod()->cfg->sections as $section ) {
			if ( empty( $section[ 'hidden' ] ) ) {
				$sections[ $section[ 'slug' ] ] = $section;
			}
		}
		return $sections;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 * @deprecated 19.1
	 */
	public function getOldValue( string $key ) {
		return $this->isOptChanged( $key ) ? $this->aOld[ $key ] : null;
	}

	/**
	 * @param string $key
	 * @param        $value
	 * @deprecated 19.1
	 */
	public function isValidOptionValueType( string $key, $value ) :bool {
		return true;
	}
}