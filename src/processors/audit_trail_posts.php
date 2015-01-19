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

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Posts') ):

	class ICWP_WPSF_Processor_AuditTrail_Posts extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_wordpress', 'Y' ) ) {
				add_action( 'deleted_post', array( $this, 'auditDeletedPost' ) );
				add_action( 'transition_post_status', array( $this, 'auditPostStatus' ), 30 , 3 );
			}
		}

		/**
		 * @param string $nPostId
		 * @return bool
		 */
		public function auditDeletedPost( $nPostId ) {

			$oPost = get_post( $nPostId );
			if ( ! ( $oPost instanceof WP_Post ) || ( $this->getIsIgnoredPostType( $oPost ) ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'posts',
				'post_deleted',
				2,
				sprintf( _wpsf__( 'WordPress Post entitled "%s" was permanently deleted from trash.' ), $oPost->post_title )
			);

		}


		/**
		 * @param string $sNewStatus
		 * @param string $sOldStatus
		 * @param WP_Post $oPost
		 * @return bool
		 */
		public function auditPostStatus( $sNewStatus, $sOldStatus, $oPost ) {

			if ( ! ( $oPost instanceof WP_Post ) || ( $this->getIsIgnoredPostType( $oPost ) ) || in_array( $sNewStatus, array( 'auto-draft', 'inherit' ) ) ) {
				return;
			}

			if ( $sNewStatus == 'trash' ) {
				$sEvent = 'post_trashed';
				$sHumanEvent = _wpsf__( 'moved to trash' );
			}
			else if ( $sOldStatus == 'trash' && $sNewStatus != 'trash' ) {
				$sEvent = 'post_recovered';
				$sHumanEvent = _wpsf__( 'recovered from trash' );
			}
			else if ( in_array( $sNewStatus, array( 'publish', 'private' ) ) ) {
				$sEvent = 'post_published';
				$sHumanEvent = _wpsf__( 'published' );
			}
			else if ( in_array( $sOldStatus, array( 'publish', 'private' ) ) && $sNewStatus == 'draft' ) {
				$sEvent = 'post_unpublished';
				$sHumanEvent = _wpsf__( 'unpublished' );
			}
			else {
				$sEvent = 'post_updated';
				$sHumanEvent = _wpsf__( 'updated' );
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'posts',
				$sEvent,
				1,
				sprintf( _wpsf__( 'Post entitled "%s" was %s.' ), $oPost->post_title, $sHumanEvent )
			);

		}

		/**
		 * @param WP_Post $oPost
		 * @return bool
		 */
		protected function getIsIgnoredPostType( $oPost ) {
			return
				( $oPost->post_status == 'auto-draft' )
				||
				in_array(
					$oPost->post_type,
					array(
						'revision',
						'nav_menu_item',
						'attachment'
					)
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