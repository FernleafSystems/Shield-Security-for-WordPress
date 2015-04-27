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

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect_V1 extends ICWP_WPSF_Processor_Base {
		/**
		 * Override to set what this processor does when it's "run"
		 */
		public function run() {
			add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );
		}

		/**
		 * Addresses this vulnerability: http://klikki.fi/adv/wordpress2.html
		 *
		 * @param string $sCommentContent
		 * @return string
		 */
		public function secXss64kb( $sCommentContent ) {
			// Comments shouldn't be any longer than 64KB
			if ( strlen( $sCommentContent ) >= ( 64 * 1024 - 1 ) ) {
				$sCommentContent = 'WordPress Simple Firewall escaped HTML for this comment due to its size: '. esc_html( $sCommentContent );
			}
			return $sCommentContent;
		}
	}

endif;

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ):
	class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_HackProtect_V1 { }
endif;