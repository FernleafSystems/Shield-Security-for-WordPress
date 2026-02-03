<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Services;

class LocateNeedles {

	/**
	 * @var string
	 */
	private $content;

	/**
	 * @var string
	 */
	private $path;

	public function raw( string $needle ) :bool {
		return \strpos( $this->getContent(), $needle ) !== false;
	}

	public function iRaw( string $needle ) :bool {
		return stripos( $this->getContent(), $needle ) !== false;
	}

	public function regex( string $needle ) :bool {
		return \preg_match( '/('.$needle.')/i', $this->getContent() ) > 0;
	}

	public function getContent() :string {
		return $this->content ?? $this->content = (string)Services::WpFs()->getFileContent( $this->getPath() );
	}

	public function getPath() :string {
		return $this->path;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function setPath( string $path ) :self {
		if ( !Services::WpFs()->isAccessibleFile( $path ) ) {
			throw new \InvalidArgumentException( "File doesn't exist" );
		}
		if ( !\is_readable( $path ) ) {
			throw new \Exception( "File isn't readable" );
		}
		$this->reset();
		$this->path = $path;
		return $this;
	}

	protected function reset() :self {
		$this->content = null;
		$this->path = null;
		return $this;
	}
}