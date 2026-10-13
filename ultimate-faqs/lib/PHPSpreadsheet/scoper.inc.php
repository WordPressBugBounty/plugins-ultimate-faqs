<?php
/** Build-time isolation of the locked spreadsheet runtime and its dependencies. */
use Symfony\Component\Finder\Finder;

return array(
	'prefix'                  => 'EwdUfaqVendor',
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
	'expose-global-constants' => false,
	// Composer regenerates its own loader. Keep InstalledVersions consistent
	// with that global loader while isolating Composer\Pcre like all dependencies.
	'exclude-files'           => array_merge(
		glob( getcwd() . '/vendor/composer/*.php' ),
		// Enum implements the native global interface on PHP 8 and this guarded
		// compatibility interface on PHP 7.4. It must not become a private symbol.
		array( getcwd() . '/vendor/myclabs/php-enum/stubs/Stringable.php' )
	),
	'finders'                 => array(
		Finder::create()->files()->in( 'vendor' ),
		Finder::create()->append( array( 'composer.json', 'composer.lock' ) ),
	),
);
