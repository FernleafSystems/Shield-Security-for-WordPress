const http = require( 'http' );
const https = require( 'https' );
const { spawn } = require( 'child_process' );

const port = process.env.SHIELD_PLAYGROUND_PORT || '9400';
const phpVersion = process.env.SHIELD_PLAYGROUND_PHP || '8.2';
const wpVersion = process.env.SHIELD_PLAYGROUND_WP || 'latest';
const baseUrl = `http://127.0.0.1:${port}/wp-admin/`;

function requestUrl( url ) {
	const client = url.startsWith( 'https:' ) ? https : http;

	return new Promise( ( resolve ) => {
		const req = client.get( url, ( response ) => {
			response.resume();
			resolve( response.statusCode && response.statusCode < 500 );
		} );

		req.on( 'error', () => resolve( false ) );
		req.setTimeout( 5_000, () => {
			req.destroy();
			resolve( false );
		} );
	} );
}

async function waitForServerReady( url, timeoutMs ) {
	const startedAt = Date.now();

	while ( Date.now() - startedAt < timeoutMs ) {
		if ( await requestUrl( url ) ) {
			return true;
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 2_000 ) );
	}

	return false;
}

function buildPlaywrightCommand() {
	return {
		command: process.execPath,
		args: [ require.resolve( '@playwright/test/cli' ), 'test', ...process.argv.slice( 2 ) ],
	};
}

async function main() {
	let playgroundProcess = null;
	let startedHere = false;

	if ( !( await requestUrl( baseUrl ) ) ) {
		playgroundProcess = spawn(
			'php',
			[
				'bin/run-playground-local.php',
				`--port=${port}`,
				`--php=${phpVersion}`,
				`--wp=${wpVersion}`,
			],
			{
				stdio: 'inherit',
				shell: false,
			}
		);
		startedHere = true;

		const ready = await waitForServerReady( baseUrl, 180_000 );
		if ( !ready ) {
			if ( playgroundProcess.exitCode === null ) {
				playgroundProcess.kill( 'SIGTERM' );
			}
			console.error( `Playground server did not become ready at ${baseUrl} within 180 seconds.` );
			process.exit( 1 );
		}
	}

	const playwright = buildPlaywrightCommand();
	const runner = spawn( playwright.command, playwright.args, {
		stdio: 'inherit',
		shell: false,
		env: process.env,
	} );

	const shutdown = () => {
		if ( startedHere && playgroundProcess && playgroundProcess.exitCode === null ) {
			playgroundProcess.kill( 'SIGTERM' );
		}
	};

	process.on( 'SIGINT', () => {
		if ( runner.exitCode === null ) {
			runner.kill( 'SIGINT' );
		}
		shutdown();
	} );
	process.on( 'SIGTERM', () => {
		if ( runner.exitCode === null ) {
			runner.kill( 'SIGTERM' );
		}
		shutdown();
	} );

	runner.on( 'exit', ( code ) => {
		shutdown();
		process.exit( code === null ? 1 : code );
	} );

	runner.on( 'error', ( error ) => {
		shutdown();
		console.error( error );
		process.exit( 1 );
	} );
}

main().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );
