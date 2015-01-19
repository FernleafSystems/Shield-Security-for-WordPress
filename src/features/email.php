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

require_once( 'base.php' );

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Email', false ) ):

	class ICWP_WPSF_FeatureHandler_Email extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		protected function getProcessorClassName() {
			return 'ICWP_WPSF_Processor_Email';
		}

		/**
		 * @return mixed
		 */
		public function getIsMainFeatureEnabled() {
			return true;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_email_options' :
					$sTitle = _wpsf__( 'Email Options' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
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

				case 'block_send_email_address' :
					$sName = _wpsf__( 'Report Email' );
					$sSummary = _wpsf__( 'Where to send email reports' );
					$sDescription = _wpsf__( 'If this is empty, it will default to the blog admin email address.' );
					break;

				case 'send_email_throttle_limit' :
					$sName = _wpsf__( 'Email Throttle Limit' );
					$sSummary = _wpsf__( 'Limit Emails Per Second' );
					$sDescription = _wpsf__( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}

		/**
		 * This is the point where you would want to do any options verification
		 */
		protected function doPrePluginOptionsSave() {
			$sEmail = $this->getOpt( 'block_send_email_address');
			if ( empty( $sEmail ) || !is_email( $sEmail ) ) {
				$sEmail = get_option('admin_email');
			}
			if ( is_email( $sEmail ) ) {
				$this->setOpt( 'block_send_email_address', $sEmail );
			}

			$sLimit = $this->getOpt( 'send_email_throttle_limit' );
			if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
				$sLimit = 0;
			}
			$this->setOpt( 'send_email_throttle_limit', $sLimit );
		}

	}

endif;