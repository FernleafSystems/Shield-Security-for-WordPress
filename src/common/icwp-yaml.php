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
 *
 */

if ( !class_exists( 'ICWP_WPSF_YamlProcessor_V2', false ) ):

	class ICWP_WPSF_YamlProcessor_V2 {

		protected static $oYaml;

		/**
		 * @param string $sYamlString
		 * @return array|null
		 */
		public function parseYamlString( $sYamlString ) {
			$aParsedResult = $this->parseSymfony( $sYamlString );
			if ( is_null( $aParsedResult ) ) {
				$aParsedResult = $this->parseSpyc( $sYamlString );
			}
			return $aParsedResult;
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
		 * @param string $sYamlString
		 * @return array|null
		 */
		protected function parseSpyc( $sYamlString ) {
			$aData = null;
			if ( $this->loadSpycYamlParser() ) {
				$aData = Spyc::YAMLLoadString( $sYamlString );
			}
			return $aData;
		}

		/**
		 */
		protected function loadSpycYamlParser() {
			if ( !class_exists( 'Spyc', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'yaml/Spyc.php' );
			}
			return class_exists( 'Spyc', false );
		}
		/**
		 */
		protected function loadSymfonyYamlParser() {
			if ( !class_exists( 'sfYaml', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'yaml/symfony/sfYaml.php' );
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

if ( !class_exists( 'ICWP_WPSF_YamlProcessor', false ) ):

	class ICWP_WPSF_YamlProcessor extends ICWP_WPSF_YamlProcessor_V2 {

		/**
		 * @var ICWP_WPSF_YamlProcessor
		 */
		protected static $oInstance = NULL;

		/**
		 * @return ICWP_WPSF_YamlProcessor
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;