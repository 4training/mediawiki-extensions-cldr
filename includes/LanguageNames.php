<?php

use MediaWiki\MediaWikiServices;

/**
 * A class for querying translated language names from CLDR data.
 *
 * @author Niklas Laxström
 * @author Ryan Kaldari
 * @copyright Copyright © 2007-2011
 * @license GPL-2.0-or-later
 */
class LanguageNames extends CldrNames {

	private static $cache = [];

	public const FALLBACK_NATIVE = 0; // Missing entries fallback to native name
	public const FALLBACK_NORMAL = 1; // Missing entries fallback through the fallback chain
	public const LIST_MW_SUPPORTED = 0; // Only names that have localisation in MediaWiki
	public const LIST_MW = 1; // All names that are in Names.php
	public const LIST_MW_AND_CLDR = 2; // Combination of Names.php and what is in cldr

	/**
	 * Get localized language names for a particular language, using fallback languages for missing
	 * items.
	 *
	 * @param string $code
	 * @param int $fbMethod
	 * @param int $list
	 * @throws Exception
	 * @return array an associative array of language codes and localized language names
	 */
	public static function getNames( $code, $fbMethod = self::FALLBACK_NATIVE,
		$list = self::LIST_MW
	) {
		$xx = self::loadLanguage( $code );

		$native = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageNames(
				null,
				$list === self::LIST_MW_SUPPORTED ? 'mwfile' : 'mw'
		);

		if ( $fbMethod === self::FALLBACK_NATIVE ) {
			$names = array_merge( $native, $xx );
		} elseif ( $fbMethod === self::FALLBACK_NORMAL ) {
			// Load missing language names from fallback languages
			$fb = $xx;

			$fallbacks = Language::getFallbacksFor( $code );
			foreach ( $fallbacks as $fallback ) {
				// Overwrite the things in fallback with what we have already
				$fb = array_merge( self::loadLanguage( $fallback ), $fb );
			}

			/* Add native names for codes that are not in cldr */
			$names = array_merge( $native, $fb );

			/* As a last resort, try the native name in Names.php */
			if ( !isset( $names[$code] ) && isset( $native[$code] ) ) {
				$names[$code] = $native[$code];
			}
		} else {
			throw new Exception( "Invalid value for 2:\$fallback in " . __METHOD__ );
		}

		switch ( $list ) {
			case self::LIST_MW:
			/** @noinspection PhpMissingBreakStatementInspection */
			case self::LIST_MW_SUPPORTED:
				/* Remove entries that are not in fb */
				$names = array_intersect_key( $names, $native );
				/* And fall to the return */
			case self::LIST_MW_AND_CLDR:
				return $names;
			default:
				throw new Exception( "Invalid value for 3:\$list in " . __METHOD__ );
		}
	}

	/**
	 * Load currency names localized for a particular language. Helper function for getNames.
	 *
	 * @param string $code The language to return the list in
	 * @return array an associative array of language codes and localized language names
	 */
	private static function loadLanguage( $code ) {
		if ( isset( self::$cache[$code] ) ) {
			return self::$cache[$code];
		}

		self::$cache[$code] = [];

		if ( !MediaWikiServices::getInstance()->getLanguageNameUtils()
			->isValidBuiltInCode( $code )
		) {
			return [];
		}

		/* Load override for wrong or missing entries in cldr */
		$override = __DIR__ . '/../LocalNames/' . self::getOverrideFileName( $code );
		if ( file_exists( $override ) ) {
			$languageNames = false;
			require $override;
			// @phan-suppress-next-line PhanImpossibleCondition
			if ( is_array( $languageNames ) ) {
				self::$cache[$code] = $languageNames;
			}
		}

		$filename = __DIR__ . '/../CldrNames/' . self::getFileName( $code );
		if ( file_exists( $filename ) ) {
			$languageNames = false;
			require $filename;
			// @phan-suppress-next-line PhanImpossibleCondition
			if ( is_array( $languageNames ) ) {
				self::$cache[$code] = self::$cache[$code] + $languageNames;
			}
		} else {
			wfDebug( __METHOD__ . ": Unable to load language names for $filename\n" );
		}

		return self::$cache[$code];
	}

	/**
	 * @param array &$names
	 * @param string $code
	 * @return bool
	 */
	public static function coreHook( &$names, $code ) {
		$names += self::getNames( $code, self::FALLBACK_NORMAL, self::LIST_MW_AND_CLDR );

		return true;
	}
}
