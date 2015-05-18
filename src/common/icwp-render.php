<?php
if ( !class_exists( 'ICWP_WPSF_Render', false ) ):

	class ICWP_WPSF_Render extends ICWP_WPSF_Foundation {

		const TEMPLATE_ENGINE_TWIG = 0;
		const TEMPLATE_ENGINE_PHP = 1;

		/**
		 * @var ICWP_WPSF_Render
		 */
		protected static $oInstance = NULL;

		private function __construct() {}

		/**
		 * @return ICWP_WPSF_Render
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}

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
		 * @var int
		 */
		protected $nTemplateEngine;

		/**
		 * @var Twig_Environment
		 */
		protected $oTwigEnv;

		/**
		 * @var Twig_Loader_Filesystem
		 */
		protected $oTwigLoader;

		/**
		 * @return string
		 */
		public function render() {

			switch( $this->getTemplateEngine() ) {

				case self::TEMPLATE_ENGINE_TWIG :
					$sOutput = $this->renderTwig();
					break;

				default:
					$sOutput = $this->renderPhp();
					break;
			}
			return $sOutput;
		}

		/**
		 * @return string
		 */
		private function renderPhp() {
			if ( count( $this->getRenderVars() ) > 0 ) {
				extract( $this->getRenderVars() );
			}

			ob_start();
			include( $this->getTemplatePath().ltrim( $this->getTemplate(), ICWP_DS ) );
			$sContents = ob_get_contents();
			ob_end_clean();
			return $sContents;
		}

		/**
		 * @return string
		 */
		private function renderTwig() {
			$oTwig = $this->getTwigEnvironment();
			return $oTwig->render( $this->getTemplate(), $this->getRenderVars() );
		}

		/**
		 */
		public function display() {
			echo $this->render();
			return $this;
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
						'debug' => true,
						'strict_variables' => true,
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
			$sTemplate = $this->sTemplate;
			$sStub = $this->getEngineStub();
			if ( !preg_match( sprintf( '#\.%s$#', $sStub ), $sTemplate ) ) {
				$sTemplate = $sTemplate.'.'.$sStub;
			}
			return $sTemplate;
		}

		/**
		 * @return int
		 */
		public function getTemplateEngine() {
			if ( !isset( $this->nTemplateEngine )
				 || !in_array( $this->nTemplateEngine, array( self::TEMPLATE_ENGINE_TWIG, self::TEMPLATE_ENGINE_PHP ) ) ) {
				$this->nTemplateEngine = self::TEMPLATE_ENGINE_PHP;
			}
			return $this->nTemplateEngine;
		}

		/**
		 * @return string
		 */
		public function getTemplatePath() {
			$sPath = rtrim( $this->sTemplatePath, ICWP_DS );
			$sStub = $this->getEngineStub();
			if ( !preg_match( sprintf( '#%s$#', $sStub ), $sPath ) ) {
				$sPath = $sPath.ICWP_DS.$sStub;
			}
			return $sPath.ICWP_DS;
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
//			if ( !preg_match( '#\.twig$#', $sPath ) ) {
//				$sPath = $sPath . '.twig';
//			}
			$this->sTemplate = $sPath;
			return $this;
		}

		/**
		 * @param int $nEngine
		 * @return $this
		 */
		public function setTemplateEngine( $nEngine ) {
			$this->nTemplateEngine = $nEngine;
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

		/**
		 * @return string
		 */
		private function getEngineStub() {
			$sStub = 'php'; // default
			if ( $this->getTemplateEngine() == self::TEMPLATE_ENGINE_TWIG ) {
				$sStub = 'twig';
			}
			return $sStub;
		}
	}

endif;