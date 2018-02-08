<?php
if ( !class_exists( 'ICWP_WPSF_YamlProcessor', false ) ):

	class ICWP_WPSF_YamlProcessor {

		/**
		 * @var ICWP_WPSF_YamlProcessor
		 */
		protected static $oInstance = NULL;

		/**
		 * @var sfYaml
		 */
		protected static $oYaml;

		/**
		 * @return ICWP_WPSF_YamlProcessor
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}

		/**
		 * @param string $sYamlString
		 * @return array|null
		 */
		public function parseYamlString( $sYamlString ) {
			return $this->parseSymfony( $sYamlString );
		}

		/**
		 * @param array $aDataToDump
		 * @return string
		 */
		public function dumpArrayToYaml( $aDataToDump ) {
			$oParser = $this->getSymfonyYamlParser();
			return $oParser->dump( $aDataToDump );
		}

		/**
		 * @param string $sYamlString
		 * @return array|null
		 */
		protected function parseSymfony( $sYamlString ) {

			$aData = null;
			$oParser = $this->getSymfonyYamlParser();
			if ( $oParser != false ) {
				try {
					$aData = $oParser->load( $sYamlString );
				}
				catch( Exception $oE ) {
					$aData = null;
				}
			}
			return $aData;
		}

		/**
		 */
		protected function loadSymfonyYamlParser() {
			if ( !class_exists( 'sfYaml', false ) ) {
				require_once( dirname(__FILE__).'/yaml/symfony/sfYaml.php' );
			}
			return class_exists( 'sfYaml', false );
		}

		/**
		 * @return bool|sfYaml
		 */
		protected function getSymfonyYamlParser() {
			if ( !isset( self::$oYaml ) ) {
				if ( $this->loadSymfonyYamlParser() ) {
					self::$oYaml = new sfYaml();
				}
				else {
					self::$oYaml = false;
				}
			}
			return self::$oYaml;
		}
	}
endif;