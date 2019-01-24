<?php

class ICWP_WPSF_Render extends ICWP_WPSF_Foundation {

	const TEMPLATE_ENGINE_TWIG = 0;
	const TEMPLATE_ENGINE_PHP = 1;
	const TEMPLATE_ENGINE_HTML = 2;

	/**
	 * @var ICWP_WPSF_Render
	 */
	protected static $oInstance = null;

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
	protected $sTemplateRootMain;

	/**
	 * @var string
	 */
	protected $aTemplateRoots;

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
	 * @throws Exception
	 */
	public function render() {

		switch ( $this->getTemplateEngine() ) {

			case self::TEMPLATE_ENGINE_TWIG :
				$sOutput = $this->renderTwig();
				break;

			case self::TEMPLATE_ENGINE_HTML :
				$sOutput = $this->renderHtml();
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
	private function renderHtml() {
		ob_start();
		@include( $this->getTemplateRootMain().ltrim( $this->getTemplate(), DIRECTORY_SEPARATOR ) );
		return ob_get_clean();
	}

	/**
	 * @return string
	 */
	private function renderPhp() {
		if ( count( $this->getRenderVars() ) > 0 ) {
			extract( $this->getRenderVars() );
		}

		$sTemplate = $this->getTemplateRootMain().ltrim( $this->getTemplate(), DIRECTORY_SEPARATOR );
		if ( $this->loadFS()->isFile( $sTemplate ) ) {
			ob_start();
			include( $sTemplate );
			$sContents = ob_get_clean();
		}
		else {
			$sContents = 'Error: Template file not found: '.$sTemplate;
		}

		return $sContents;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function renderTwig() {
		return $this->getTwigEnvironment()->render( $this->getTemplate(), $this->getRenderVars() );
	}

	/**
	 */
	public function display() {
		echo $this->render();
		return $this;
	}

	/**
	 * @return $this
	 */
	public function clearRenderVars() {
		return $this->setRenderVars( array() );
	}

	/**
	 * @return Twig_Environment
	 */
	protected function getTwigEnvironment() {
		return new Twig_Environment( $this->getTwigLoader(),
			array(
				'debug'            => true,
				'strict_variables' => true,
			)
		);
	}

	/**
	 * @return Twig_Loader_Filesystem
	 */
	protected function getTwigLoader() {
		if ( !isset( $this->oTwigLoader ) ) {
			$this->oTwigLoader = new Twig_Loader_Filesystem( $this->getTemplateRootMain() );
		}
		return $this->oTwigLoader;
	}

	/**
	 * @return string
	 */
	public function getTemplate() {
		$this->sTemplate = $this->loadDP()
								->addExtensionToFilePath( $this->sTemplate, $this->getEngineStub() );
		return $this->sTemplate;
	}

	/**
	 * @return int
	 */
	public function getTemplateEngine() {
		if ( !isset( $this->nTemplateEngine )
			 || !in_array( $this->nTemplateEngine, array(
				self::TEMPLATE_ENGINE_TWIG,
				self::TEMPLATE_ENGINE_PHP,
				self::TEMPLATE_ENGINE_HTML
			) ) ) {
			$this->nTemplateEngine = self::TEMPLATE_ENGINE_PHP;
		}
		return $this->nTemplateEngine;
	}

	/**
	 * @param string $sTemplate
	 * @return string
	 */
	public function getTemplateExists( $sTemplate = '' ) {
		$sFullPath = $this->getTemplateFullPath( $sTemplate );
		return $this->loadFS()->exists( $sFullPath );
	}

	/**
	 * @param string $sTemplate
	 * @return string
	 */
	public function getTemplateFullPath( $sTemplate = '' ) {
		if ( empty( $sTemplate ) ) {
			$sTemplate = $this->getTemplate();
		}
		$sTemplate = $this->loadDP()->addExtensionToFilePath( $sTemplate, $this->getEngineStub() );
		return path_join( $this->getTemplateRootMain(), $sTemplate );
	}

	/**
	 * @return string
	 */
	public function getTemplateRootMain() {
		$sPath = rtrim( $this->sTemplateRootMain, DIRECTORY_SEPARATOR );
		$sStub = $this->getEngineStub();
		if ( !preg_match( sprintf( '#%s$#', $sStub ), $sPath ) ) {
			$sPath = $sPath.DIRECTORY_SEPARATOR.$sStub;
		}
		return $sPath.DIRECTORY_SEPARATOR;
	}

	/**
	 * For use with Twig
	 * @return array
	 */
	public function getTemplateRoots() {
		if ( !is_array( $this->aTemplateRoots ) ) {
			$this->aTemplateRoots = array();
		}
		array_unshift( $this->aTemplateRoots, $this->getTemplateRootMain() );
		return array_unique( array_filter( $this->aTemplateRoots ) );
	}

	/**
	 * @return array
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
	 * @return $this
	 */
	public function setTemplateEngineHtml() {
		return $this->setTemplateEngine( self::TEMPLATE_ENGINE_HTML );
	}

	/**
	 * @return $this
	 */
	public function setTemplateEnginePhp() {
		return $this->setTemplateEngine( self::TEMPLATE_ENGINE_PHP );
	}

	/**
	 * @return $this
	 */
	public function setTemplateEngineTwig() {
		return $this->setTemplateEngine( self::TEMPLATE_ENGINE_TWIG );
	}

	/**
	 * @param int $nEngine
	 * @return $this
	 */
	protected function setTemplateEngine( $nEngine ) {
		$this->nTemplateEngine = $nEngine;
		return $this;
	}

	/**
	 * @param string $sPath
	 * @return $this
	 */
	public function addTemplateRoot( $sPath ) {
		$aRoots = $this->getTemplateRoots();
		$aRoots[] = $sPath;
		$this->aTemplateRoots = $aRoots;
		return $this;
	}

	/**
	 * @param string $sPath
	 * @return $this
	 */
	public function setTemplateRoot( $sPath ) {
		$this->sTemplateRootMain = $sPath;
		return $this;
	}

	/**
	 * @return string
	 */
	private function getEngineStub() {
		switch ( $this->getTemplateEngine() ) {

			case self::TEMPLATE_ENGINE_TWIG:
				$sStub = 'twig';
				break;

			case self::TEMPLATE_ENGINE_HTML:
				$sStub = 'html';
				break;

			case self::TEMPLATE_ENGINE_PHP:
				$sStub = 'php';
				break;

			default:
				$sStub = 'php';
				break;
		}
		return $sStub;
	}
}