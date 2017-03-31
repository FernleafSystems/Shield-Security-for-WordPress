<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Track', false ) ):

	class ICWP_WPSF_Processor_LoginProtect_Track {

		/**
		 * @var int
		 */
		private $nAuthFactorsSuccessful;

		/**
		 * @var int
		 */
		private $nAuthFactorsUnsuccessful;

		/**
		 * @return int
		 */
		public function getAuthFactorsSuccessful() {
			if ( !isset( $this->nAuthFactorsSuccessful ) ) {
				$this->nAuthFactorsSuccessful = 0;
			}
			return $this->nAuthFactorsSuccessful;
		}

		/**
		 * @return bool
		 */
		public function hasSuccessfulAuth() {
			if ( !isset( $this->nAuthFactorsSuccessful ) ) {
				$this->nAuthFactorsSuccessful = 0;
			}
			return ( $this->getAuthFactorsSuccessful() > 0 );
		}

		/**
		 * @return int
		 */
		public function getAuthFactorsUnsuccessful() {
			if ( !isset( $this->nAuthFactorsUnsuccessful ) ) {
				$this->nAuthFactorsUnsuccessful = 0;
			}
			return $this->nAuthFactorsUnsuccessful;
		}

		/**
		 * @return $this
		 */
		public function incrementAuthFactorsSuccessful() {
			return $this->setAuthFactorsSuccessful( $this->getAuthFactorsSuccessful() + 1 );
		}

		/**
		 * @return $this
		 */
		public function incrementAuthFactorsUnSuccessful() {
			return $this->setAuthFactorsUnsuccessful( $this->getAuthFactorsUnsuccessful() + 1 );
		}

		/**
		 * @param int $nAuthFactorsSuccessful
		 * @return $this
		 */
		public function setAuthFactorsSuccessful( $nAuthFactorsSuccessful ) {
			$this->nAuthFactorsSuccessful = $nAuthFactorsSuccessful;
			return $this;
		}

		/**
		 * @param int $nAuthFactorsUnsuccessful
		 * @return $this
		 */
		public function setAuthFactorsUnsuccessful( $nAuthFactorsUnsuccessful ) {
			$this->nAuthFactorsUnsuccessful = $nAuthFactorsUnsuccessful;
			return $this;
		}
	}
endif;