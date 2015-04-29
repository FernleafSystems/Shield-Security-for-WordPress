<?php

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
			if ( strlen( $sCommentContent ) >= ( 64 * 1024 ) ) {
				$sCommentContent = 'WordPress Simple Firewall escaped HTML for this comment due to its size: '. esc_html( $sCommentContent );
			}
			return $sCommentContent;
		}
	}

endif;

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ):
	class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_HackProtect_V1 { }
endif;