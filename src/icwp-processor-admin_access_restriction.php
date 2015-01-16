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

require_once( 'icwp-processor-base.php' );

if ( !class_exists('ICWP_WPSF_Processor_AdminAccessRestriction') ):

	class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_Base {

		/**
		 * @var string
		 */
		protected $sOptionRegexPattern;

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();
			add_filter( $oFO->doPluginPrefix( 'has_permission_to_submit' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
			add_filter( $oFO->doPluginPrefix( 'has_permission_to_view' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );

			$oWp = $this->loadWpFunctionsProcessor();
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
