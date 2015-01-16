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

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Wordpress') ):

	class ICWP_WPSF_Processor_AuditTrail_Wordpress extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
				add_action( '_core_updated_successfully', array( $this, 'auditCoreUpdated' ) );
				add_action( 'update_option_permalink_structure', array( $this, 'auditPermalinkStructure' ), 10, 2 );
			}
		}

		/**
		 * @param string $sNewCoreVersion
		 * @return bool
		 */
		public function auditCoreUpdated( $sNewCoreVersion ) {
			global $wp_version;

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'wordpress',
				'core_updated',
				1,
				sprintf( _wpsf__( 'WordPress Core was updated from "v%s" to "v%s".' ), $wp_version, $sNewCoreVersion )
			);
		}

		/**
		 * @param string $sOld
		 * @param string $sNew
		 * @return bool
		 */
		public function auditPermalinkStructure( $sOld, $sNew ) {
			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'wordpress',
				'permalinks_structure',
				1,
				sprintf( _wpsf__( 'WordPress Permalinks Structure was updated from "%s" to "%s".' ), $sOld, $sNew )
			);
		}

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			return ICWP_WPSF_AuditTrail_Entries::GetInstance();
		}
	}

endif;