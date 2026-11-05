/**
 * WordPress CSS lint configuration.
 *
 * @since Unreleased Enforces WordPress CSS coding standards.
 */

import { createRequire } from 'node:module';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const globalModuleRoot = process.platform === 'win32' && process.env.APPDATA
	? path.join( process.env.APPDATA, 'npm', 'node_modules' )
	: path.resolve( path.dirname( process.execPath ), '..', 'lib', 'node_modules' );
const moduleRoot = process.env.STYLELINT_MODULE_ROOT || globalModuleRoot;

const require = createRequire(
	pathToFileURL( path.join( moduleRoot, 'stylelint', 'package.json' ) ),
);

export default {
	extends: [ require.resolve( '@wordpress/stylelint-config/stylistic' ) ],
};
