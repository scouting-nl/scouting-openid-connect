import { createRequire } from 'node:module';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const globalModuleRoot = process.platform === 'win32' && process.env.APPDATA
	? path.join( process.env.APPDATA, 'npm', 'node_modules' )
	: path.resolve( path.dirname( process.execPath ), '..', 'lib', 'node_modules' );
const moduleRoot = process.env.ESLINT_MODULE_ROOT || globalModuleRoot;

const require = createRequire(
	pathToFileURL( path.join( moduleRoot, 'eslint', 'package.json' ) ),
);
const globals = require( 'globals' );
const wordpress = require( '@wordpress/eslint-plugin' );

export default [
	{
		ignores: [ 'eslint.config.mjs', '**/node_modules/**', '**/vendor/**' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		files: [ 'src/**/*.js' ],
		languageOptions: {
			globals: globals.browser,
		},
		settings: {
			react: {
				version: '18.0',
			},
		},
	},
];