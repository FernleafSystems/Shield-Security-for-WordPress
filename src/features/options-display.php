<?php
if ( !class_exists( 'ICWP_WPSF_OptionsDisplay', false ) ):

	class ICWP_WPSF_OptionsDisplay extends ICWP_WPSF_Foundation {

		/**
		 * @var array
		 */
		protected $aRenderVars;

		/**
		 * @var string
		 */
		protected $sTemplatePath;

		/**
		 * @var string
		 */
		protected $sAutoloaderPath;

		/**
		 * @var string
		 */
		protected $sTemplate;

		/**
		 * @var Twig_Environment
		 */
		protected $oTwigEnv;

		/**
		 * @var Twig_Loader_Filesystem
		 */
		protected $oTwigLoader;

		/**
		 */
		public function __construct() { }

		/**
		 * @return string
		 */
		public function render() {
			$oTwig = $this->getTwigEnvironment();
			return $oTwig->render( $this->getTemplate() );
		}

		/**
		 */
		public function display() {
			$this->getTwigEnvironment()->display( $this->getTemplate(), $this->getRenderVars() );
		}

		/**
		 */
		protected function autoload() {
			if ( !class_exists( 'Twig_Autoloader', false ) ) {
				require_once( $this->sAutoloaderPath );
				Twig_Autoloader::register();
			}
		}

		/**
		 * @return Twig_Environment
		 */
		protected function getTwigEnvironment() {
			if ( !isset( $this->oTwigEnv )  ) {
				$this->autoload();
				$this->oTwigEnv = new Twig_Environment( $this->getTwigLoader(),
					array(
						'debug' => true
					)
				);
			}
			return $this->oTwigEnv;
		}

		/**
		 * @return Twig_Loader_Filesystem
		 */
		protected function getTwigLoader() {
			if ( !isset( $this->oTwigLoader )  ) {
				$this->autoload();
				$this->oTwigLoader = new Twig_Loader_Filesystem( $this->getTemplatePath() );
			}
			return $this->oTwigLoader;
		}

		/**
		 * @return string
		 */
		public function getTemplate() {
			return $this->sTemplate;
		}

		/**
		 * @return string
		 */
		public function getTemplatePath() {
			return $this->sTemplatePath;
		}

		/**
		 * @return string
		 */
		public function getRenderVars() {
			return $this->aRenderVars;
		}


		/**
		 * @param array $aVars
		 * @return $this
		 */
		public function setRenderVars( $aVars ) {
			$this->aRenderVars = $aVars;
			return $this;
		}

		/**
		 * @param string $sPath
		 * @return $this
		 */
		public function setAutoloaderPath( $sPath ) {
			$this->sAutoloaderPath = $sPath;
			return $this;
		}

		/**
		 * @param string $sPath
		 * @return $this
		 */
		public function setTemplate( $sPath ) {
			$this->sTemplate = $sPath;
			return $this;
		}

		/**
		 * @param string $sPath
		 * @return $this
		 */
		public function setTemplatePath( $sPath ) {
			$this->sTemplatePath = $sPath;
			return $this;
		}
	}

endif;