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

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Cooldown', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', array( $this, 'checkLoginInterval' ), 10, 2 );

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {
			add_filter( 'registration_errors', array( $this, 'checkLoginInterval' ), 10, 2 );
		}
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 *
	 * @param null|WP_User|WP_Error $oUserOrError
	 * @param string $sUsername
	 * @return WP_User|WP_Error
	 */
	public function checkLoginInterval( $oUserOrError, $sUsername ) {
		// No login attempt was made and we do nothing
		if ( empty( $sUsername ) ) {
			return $oUserOrError;
		}

		// If we're outside the interval, let the login process proceed as per normal and
		// update our last login time.
		$bWithinCooldownPeriod = $this->getIsWithinCooldownPeriod();
		if ( !$bWithinCooldownPeriod ) {
			$this->updateLastLoginTime();
			$this->doStatIncrement( 'login.cooldown.success' );
			return $oUserOrError;
		}

		// At this point someone has attempted to login within the previous login wait interval
		// So we remove WordPress's authentication filter and our own user check authentication
		// And finally return a WP_Error which will be reflected back to the user.
		$this->doStatIncrement( 'login.cooldown.fail' );
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );  // wp-includes/user.php

		$oWp = $this->loadWpFunctionsProcessor();
		$sErrorString = _wpsf__( "Login Cooldown in effect." ).' '. sprintf( _wpsf__( "You must wait %s seconds before attempting to %s again." ),
			$this->getLoginCooldownInterval() - $this->getSecondsSinceLastLoginTime(),
			$oWp->getIsLoginRequest() ? _wpsf__('login') : _wpsf__('register')
		);

		if ( !is_wp_error( $oUserOrError ) ) {
			$oUserOrError = new WP_Error();
		}
		$oUserOrError->add( 'wpsf_logininterval', $sErrorString );
		return $oUserOrError;
	}

	/**
	 * @return int
	 */
	protected function getLoginCooldownInterval() {
		return $this->getOption( 'login_limit_interval' );
	}

	/**
	 * @return int
	 */
	protected function getLastLoginTime() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		$sFilePath = $oFO->getLastLoginTimeFilePath();
		$oWpFs = $this->loadFileSystemProcessor();
		$nModifiedFileTime = ( $oWpFs->exists( $sFilePath ) ) ? filemtime( $sFilePath ) : 0;
		return max( $nModifiedFileTime, $this->getOption( 'last_login_time' ) );
	}

	/**
	 */
	protected function updateLastLoginTime() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oFO->setOpt( 'last_login_time', $this->time() );
		$this->loadFileSystemProcessor()->touch( $oFO->getLastLoginTimeFilePath(), $this->time() );
	}

	/**
	 * @return bool
	 */
	protected function getIsWithinCooldownPeriod() {
		// Is there an interval set?
		$nRequiredLoginInterval = $this->getLoginCooldownInterval();
		if ( empty( $nRequiredLoginInterval ) || $nRequiredLoginInterval <= 0 ) {
			return false;
		}

		$sCurrentInterval = $this->getSecondsSinceLastLoginTime();
		return ( $sCurrentInterval < $nRequiredLoginInterval );
	}

	/**
	 * @return int
	 */
	protected function getSecondsSinceLastLoginTime() {
		return ( $this->time() - $this->getLastLoginTime() );
	}
}
endif;