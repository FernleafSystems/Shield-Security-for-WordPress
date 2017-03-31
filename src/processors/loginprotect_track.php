<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Track', false ) ):

	class ICWP_WPSF_Processor_LoginProtect_Track {

		/**
		 * @var array
		 */
		private $aFactors;

		/**
		 * @param string $sKey
		 * @return $this
		 */
		public function addSuccessfulFactor( $sKey ) {
			$aFactors = $this->getAuthFactors();
			$aFactors[ $sKey ] = true;
			$this->aFactors = $aFactors;
			return $this;
		}

		/**
		 * @param string $sKey
		 * @return $this
		 */
		public function addUnSuccessfulFactor( $sKey ) {
			$aFactors = $this->getAuthFactors();
			$aFactors[ $sKey ] = false;
			$this->aFactors = $aFactors;
			return $this;
		}

		/**
		 * @return array
		 */
		public function getAuthFactors() {
			if ( !isset( $this->aFactors ) ) {
				$this->aFactors = array();
			}
			return $this->aFactors;
		}

		/**
		 * @return int
		 */
		public function getAuthFactorsTotal() {
			return count( $this->getAuthFactors() );
		}

		/**
		 * Works by using array_filter() with no callback, so only those values in the
		 * array that don't evaluate as false are returned. #SuperOmgElegant :)
		 *
		 * @return int
		 */
		public function getAuthFactorsSuccessful() {
			return count( array_filter( $this->getAuthFactors() ) );
		}

		/**
		 * @return bool
		 */
		public function hasSuccessfulAuth() {
			return ( $this->getAuthFactorsSuccessful() > 0 );
		}

		/**
		 * @return int
		 */
		public function getAuthFactorsUnsuccessful() {
			return ( $this->getAuthFactorsTotal() - $this->getAuthFactorsSuccessful() );
		}
	}
endif;