<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Track', false ) ):

	class ICWP_WPSF_Processor_LoginProtect_Track {

		const Factor_Google_Authenticator = 'ga';
		const Factor_Yubikey = 'yubi';
		const Factor_Email = 'email';

		/**
		 * @var array
		 */
		private $aFactorsTracked;

		/**
		 * @var array
		 */
		private $aFactorsToTrack;

		/**
		 * @param string $sFactor
		 * @return $this
		 */
		public function addFactorToTrack( $sFactor ) {
			$aFactorsToTrack = $this->getAuthFactorsToTrack();
			$aFactorsToTrack[ $sFactor ] = true;
			$this->aFactorsToTrack = $aFactorsToTrack;
			return $this;
		}

		/**
		 * @param string $sFactor
		 * @return $this
		 */
		public function addSuccessfulFactor( $sFactor ) {
			return $this->setFactorState( $sFactor, true );
		}

		/**
		 * @param string $sFactor
		 * @return $this
		 */
		public function addUnSuccessfulFactor( $sFactor ) {
			return $this->setFactorState( $sFactor, false );
		}

		/**
		 * @return array
		 */
		public function getAuthFactorsTracked() {
			if ( !isset( $this->aFactorsTracked ) ) {
				$this->aFactorsTracked = array();
			}
			return $this->aFactorsTracked;
		}

		/**
		 * @return array
		 */
		public function getAuthFactorsToTrack() {
			if ( !isset( $this->aFactorsToTrack ) ) {
				$this->aFactorsToTrack = array();
			}
			return $this->aFactorsToTrack;
		}

		/**
		 * @return int
		 */
		public function getCountAuthFactorsTrackedTotal() {
			return count( $this->getAuthFactorsTracked() );
		}

		/**
		 * Works by using array_filter() with no callback, so only those values in the
		 * array that don't evaluate as false are returned. #SuperOmgElegant :)
		 *
		 * @return int
		 */
		public function getCountFactorsSuccessful() {
			return count( array_filter( $this->getAuthFactorsTracked() ) );
		}

		/**
		 * @return int
		 */
		public function getCountFactorsUnsuccessful() {
			return ( $this->getCountAuthFactorsTrackedTotal() - $this->getCountFactorsSuccessful() );
		}

		/**
		 * @return int
		 */
		public function getCountFactorsRemainingToTrack() {
			return count( $this->getAuthFactorsToTrack() );
		}

		/**
		 * @return bool
		 */
		public function hasFactorsRemainingToTrack() {
			return ( $this->getCountFactorsRemainingToTrack() > 0 );
		}

		/**
		 * @return bool
		 */
		public function hasSuccessfulFactorAuth() {
			return ( $this->getCountFactorsSuccessful() > 0 );
		}

		/**
		 * @return bool
		 */
		public function hasUnSuccessfulFactorAuth() {
			return ( $this->getCountFactorsUnsuccessful() > 0 );
		}

		/**
		 * @return bool
		 */
		public function isFinalFactorRemainingToTrack() {
			return ( $this->getCountFactorsRemainingToTrack() === 1 );
		}

		/**
		 * Also remove remaining factors to track
		 * @param string $sFactor
		 * @param bool $bState
		 * @return $this
		 */
		protected function setFactorState( $sFactor, $bState ) {
			$aFactors = $this->getAuthFactorsTracked();
			$aFactors[ $sFactor ] = $bState;
			$this->aFactorsTracked = $aFactors;
			unset( $this->aFactorsToTrack[ $sFactor ] );
			return $this;
		}
	}
endif;