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

if ( !class_exists( 'ICWP_WPSF_YamlProcessor_V1', false ) ):

	class ICWP_WPSF_YamlProcessor_V1 {

		/**
		 * @param string $sYamlString
		 * @return array
		 */
		public function parseYamlString( $sYamlString ) {
			if ( ! $this->loadYamlParser() ) {
				return null;
			}
			return Spyc::YAMLLoadString( $sYamlString );
		}

		/**
		 */
		protected function loadYamlParser() {
			if ( !class_exists( 'Spyc' ) ) {
				require_once( 'yaml/Spyc.php' );
			}
			return class_exists( 'Spyc' );
		}
	}
endif;

if ( !class_exists( 'ICWP_WPSF_YamlProcessor', false ) ):

	class ICWP_WPSF_YamlProcessor extends ICWP_WPSF_YamlProcessor_V1 {

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