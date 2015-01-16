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

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Gasp', false ) ):

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		// Add GASP checking to the login form.
		add_action( 'login_form',				array( $this, 'printGaspLoginCheck_Action' ) );
		add_action( 'woocommerce_login_form',	array( $this, 'printGaspLoginCheck_Action' ) );
		add_filter( 'login_form_middle',		array( $this, 'printGaspLoginCheck_Filter' ) );
		add_filter( 'authenticate',				array( $this, 'checkLoginForGasp_Filter' ), 22, 3);
	}

	/**
	 */
	public function printGaspLoginCheck_Action() {
		echo $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	public function printGaspLoginCheck_Filter() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @param $oUser
	 * @param $sUsername
	 * @param $sPassword
	 * @return WP_Error
	 */
	public function checkLoginForGasp_Filter( $oUser, $sUsername, $sPassword ) {

		if ( empty( $sUsername ) || is_wp_error( $oUser ) ) {
			return $oUser;
		}
		if ( $this->doGaspChecks( $sUsername ) ) {
			return $oUser;
		}
		//This doesn't actually ever get returned because we die() within doGaspChecks()
		return new WP_Error('wpsf_gaspfail', _wpsf__('G.A.S.P. Checking Failed.') );
	}

	public function getGaspLoginHtml() {
	
		$sLabel = _wpsf__("I'm a human.");
		$sAlert = _wpsf__("Please check the box to show us you're a human.");
	
		$sUniqElem = 'icwp_wpsf_login_p'.uniqid();
		
		$sStyles = '
			<style>
				#'.$sUniqElem.' {
					clear:both;
					border: 1px solid #888;
					padding: 6px 8px 4px 10px;
					margin: 0 0px 12px !important;
					border-radius: 2px;
					background-color: #f9f9f9;
				}
				#'.$sUniqElem.' input {
					margin-right: 5px;
				}
			</style>
		';
	
		$sHtml =
			$sStyles.
			'<p id="'.$sUniqElem.'"></p>
			<script type="text/javascript">
				var icwp_wpsf_login_p		= document.getElementById("'.$sUniqElem.'");
				var icwp_wpsf_login_cb		= document.createElement("input");
				var icwp_wpsf_login_text	= document.createTextNode(" '.$sLabel.'");
				icwp_wpsf_login_cb.type		= "checkbox";
				icwp_wpsf_login_cb.id		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_cb.name		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_p.appendChild( icwp_wpsf_login_cb );
				icwp_wpsf_login_p.appendChild( icwp_wpsf_login_text );
				var frm = icwp_wpsf_login_cb.form;
				frm.onsubmit = icwp_wpsf_login_it;
				function icwp_wpsf_login_it(){
					if(icwp_wpsf_login_cb.checked != true){
						alert("'.$sAlert.'");
						return false;
					}
					return true;
				}
			</script>
			<noscript>'._wpsf__('You MUST enable Javascript to be able to login').'</noscript>
			<input type="hidden" id="icwp_wpsf_login_email" name="icwp_wpsf_login_email" value="" />
		';

		return $sHtml;
	}

	/**
	 * @return string
	 */
	public function getGaspCheckboxName() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		return $oFO->doPluginPrefix( $oFO->getGaspKey() );
	}

	/**
	 * @param $sUsername
	 * @return bool
	 */
	public function doGaspChecks( $sUsername ) {
		$oDp = $this->loadDataProcessor();
		$sGaspCheckBox = $oDp->FetchPost( $this->getGaspCheckboxName() );
		$sHoney = $oDp->FetchPost( 'icwp_wpsf_login_email' );

		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but GASP checkbox was not present.'), $sUsername ).' '._wpsf__('Probably a BOT.');
			$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_block_gasp_checkbox' );
			$this->doStatIncrement( 'login.gasp.checkbox.fail' );
			wp_die( _wpsf__( "You must check that box to say you're not a bot." ) );
			return false;
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but they were caught by the GASP honeypot.'), $sUsername ).' '._wpsf__('Probably a BOT.');
			$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_block_gasp_honeypot' );
			$this->doStatIncrement( 'login.gasp.honeypot.fail' );
			wp_die( _wpsf__('You appear to be a bot - terminating login attempt.') );
			return false;
		}
		return true;
	}
}
endif;