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

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Themes') ):

	class ICWP_WPSF_Processor_AuditTrail_Themes extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_themes', 'Y' ) ) {
				add_action( 'switch_theme', array( $this, 'auditSwitchTheme' ) );
				add_action( 'check_admin_referer', array( $this, 'auditEditedThemeFile' ), 10, 2 );
//				add_action( 'upgrader_process_complete', array( $this, 'auditInstalledTheme' ) );
			}
		}

		/**
		 * @param string $sThemeName
		 */
		public function auditSwitchTheme( $sThemeName ) {
			if ( empty( $sThemeName ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'themes',
				'theme_activated',
				1,
				sprintf( _wpsf__( 'Theme "%s" was activated.' ), $sThemeName )
			);
		}

		/**
		 * @param string $sAction
		 * @param boolean $bResult
		 */
		public function auditEditedThemeFile( $sAction, $bResult ) {

			$sStub = 'edit-theme_';
			if ( strpos( $sAction, $sStub ) !== 0 ) {
				return;
			}

			$sFileName = str_replace( $sStub, '', $sAction );

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'themes',
				'file_edited',
				2,
				sprintf( _wpsf__( 'An attempt was made to edit the theme file "%s" directly through the WordPress editor.' ), $sFileName )
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