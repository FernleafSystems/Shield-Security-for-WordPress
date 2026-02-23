<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\ClassDependencyGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryNotFoundException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class RenderService {

	use PluginControllerConsumer;

	public const STUB = 'twig';

	protected array $renderVars = [];

	protected array $roots = [];

	protected string $template = '';

	private $twigEnvVariables = [];

	public function display() :void {
		echo $this->render();
	}

	public function render() :string {
		return $this->renderTwig();
	}

	private function renderTwig() :string {
		try {
			if ( empty( $this->template ) ) {
				throw new \Exception( 'No template provided.' );
			}

			$env = $this->getTwigEnvironment();
			/**
			 * foreach ( $this->enumExtensions() as $enumExtension ) {
			 * $env->addExtension( new $enumExtension() );
			 * }*/
			do_action( 'shield/services/pre_render_twig', $env );
			return $env->render( Paths::AddExt( $this->template, self::STUB ), $this->renderVars );
		}
		catch ( \Exception $e ) {
			return 'Could not render Twig with following Exception: '.$e->getMessage();
		}
	}

	/**
	 * @throws LibraryNotFoundException
	 */
	private function getTwigEnvironment() :Environment {
		( new ClassDependencyGuard() )->ensureAvailable( '\Twig\Environment', 'Twig' );
		return new Environment(
			$this->getTwigFileSystemLoader(),
			\array_merge( [
				'debug'            => true,
				'strict_variables' => true,
			], $this->twigEnvVariables )
		);
	}

	protected function getTwigFileSystemLoader() :FilesystemLoader {
		return new FilesystemLoader( $this->getRoots() );
	}

	public function getRoots() :array {
		if ( empty( $this->roots ) ) {
			foreach ( ( new LocateTemplateDirs() )->run() as $dir ) {
				$this->addRoot( $dir );
			}
			$this->addRoot( self::con()->getPath_Templates() );
		}
		return $this->roots;
	}

	public function setData( array $vars, bool $replace = true ) :self {
		$this->renderVars = $replace ? $vars : \array_merge( $this->renderVars, $vars );
		return $this;
	}

	public function setTemplate( string $template ) :self {
		$this->template = $template;
		return $this;
	}

	public function addRoot( string $path ) :void {
		if ( !empty( $path ) && Services::WpFs()->isAccessibleDir( $path ) ) {
			$this->roots[] = trailingslashit( $path );
			$this->roots = \array_unique( $this->roots );
		}
	}

	public function setEnvironmentVars( array $vars ) :self {
		$this->twigEnvVariables = $vars;
		return $this;
	}

	public function templateExists( string $template ) :bool {
		return $this->getTwigFileSystemLoader()->exists( $template );
	}
}
