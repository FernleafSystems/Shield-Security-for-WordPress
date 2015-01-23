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

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Plugins') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_AuditTrail_Plugins extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_plugins', 'Y' ) ) {
				add_action( 'deactivated_plugin', array( $this, 'auditDeactivatedPlugin' ) );
				add_action( 'activated_plugin', array( $this, 'auditActivatedPlugin' ) );
				add_action( 'check_admin_referer', array( $this, 'auditEditedPluginFile' ), 10, 2 );
			}
		}

		/**
		 * @param string $sPlugin
		 */
		public function auditActivatedPlugin( $sPlugin ) {
			if ( empty( $sPlugin ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'plugins',
				'plugin_activated',
				1,
				sprintf( _wpsf__( 'Plugin "%s" was activated.' ), $sPlugin )
			);
		}

		/**
		 * @param string $sPlugin
		 */
		public function auditDeactivatedPlugin( $sPlugin ) {
			if ( empty( $sPlugin ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'plugins',
				'plugin_deactivated',
				1,
				sprintf( _wpsf__( 'Plugin "%s" was deactivated.' ), $sPlugin )
			);
		}

		/**
		 * @param string $sAction
		 * @param boolean $bResult
		 */
		public function auditEditedPluginFile( $sAction, $bResult ) {

			$sStub = 'edit-plugin_';
			if ( strpos( $sAction, $sStub ) !== 0 ) {
				return;
			}

			$sFileName = str_replace( $sStub, '', $sAction );

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'plugins',
				'file_edited',
				2,
				sprintf( _wpsf__( 'An attempt was made to edit the plugin file "%s" directly through the WordPress editor.' ), $sFileName )
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