const fs = require( 'fs' );
const path = require( 'path' );

const repoRoot = path.resolve( __dirname, '..' );
const errors = [];

function readJsonFile( relativePath ) {
	const absolutePath = path.join( repoRoot, relativePath );
	try {
		return JSON.parse( fs.readFileSync( absolutePath, 'utf8' ) );
	}
	catch ( error ) {
		errors.push( `Unable to read ${relativePath}: ${error.message}` );
		return null;
	}
}

function collectNonDeclarationTypeScriptFiles( dirPath ) {
	/** @type {string[]} */
	const files = [];
	if ( !fs.existsSync( dirPath ) ) {
		return files;
	}

	for ( const entry of fs.readdirSync( dirPath, { withFileTypes: true } ) ) {
		const entryPath = path.join( dirPath, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...collectNonDeclarationTypeScriptFiles( entryPath ) );
		}
		else if ( /\.(ts|tsx)$/i.test( entry.name ) && !/\.d\.ts$/i.test( entry.name ) ) {
			files.push( path.relative( repoRoot, entryPath ).replaceAll( '\\', '/' ) );
		}
	}

	return files;
}

const tsconfig = readJsonFile( 'tsconfig.checkjs.json' );
if ( tsconfig !== null ) {
	const compilerOptions = tsconfig.compilerOptions || {};
	if ( compilerOptions.allowJs !== true ) {
		errors.push( 'tsconfig.checkjs.json must keep compilerOptions.allowJs set to true.' );
	}
	if ( compilerOptions.checkJs !== true ) {
		errors.push( 'tsconfig.checkjs.json must keep compilerOptions.checkJs set to true.' );
	}
	if ( compilerOptions.noEmit !== true ) {
		errors.push( 'tsconfig.checkjs.json must keep compilerOptions.noEmit set to true.' );
	}
}

const nonDeclarationTsFiles = collectNonDeclarationTypeScriptFiles(
	path.join( repoRoot, 'assets', 'js' )
);
if ( nonDeclarationTsFiles.length > 0 ) {
	errors.push(
		'Checker-only JS policy forbids non-declaration TypeScript source files under assets/js: '
		+ nonDeclarationTsFiles.join( ', ' )
	);
}

const packageJson = readJsonFile( 'package.json' );
if ( packageJson !== null ) {
	const dependencies = {
		...( packageJson.dependencies || {} ),
		...( packageJson.devDependencies || {} ),
	};
	[ 'ts-loader', '@babel/preset-typescript' ].forEach( ( packageName ) => {
		if ( Object.prototype.hasOwnProperty.call( dependencies, packageName ) ) {
			errors.push(
				`Checker-only JS policy forbids adding ${packageName} without explicit approval.`
			);
		}
	} );

	const testJsScript = String( packageJson.scripts?.[ 'test:js' ] || '' ).trim();
	if ( testJsScript.length < 1 ) {
		errors.push( 'package.json must define a test:js script for the checked-JS slice.' );
	}
	else {
		[
			'npm run check:js-policy',
			'npm run lint:js',
			'npm run typecheck:js',
			'npm run build',
		].forEach( ( expectedSegment ) => {
			if ( !testJsScript.includes( expectedSegment ) ) {
				errors.push( `package.json test:js must include "${expectedSegment}".` );
			}
		} );
		[ 'test:browser', 'playwright', 'php bin/shield test:browser' ].forEach( ( forbiddenSegment ) => {
			if ( testJsScript.includes( forbiddenSegment ) ) {
				errors.push( `package.json test:js must stay static-only and not include "${forbiddenSegment}".` );
			}
		} );
	}
}

if ( errors.length > 0 ) {
	console.error( 'JavaScript tooling policy check failed:' );
	errors.forEach( ( error ) => {
		console.error( `- ${error}` );
	} );
	process.exit( 1 );
}

console.log( 'JavaScript tooling policy check passed.' );
