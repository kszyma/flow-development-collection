<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\I18n\Cldr\Reader;

/* *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * A reader for data placed in "dates" tag in CLDR.
 *
 * This is not full implementation of features from CLDR. These are missing:
 * - support for other calendars than Gregorian
 * - rules for displaying timezone names are simplified
 * - some data from "dates" tag is not used (fields, timeZoneNames)
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @see http://www.unicode.org/reports/tr35/#Date_Elements
 * @see http://www.unicode.org/reports/tr35/#Date_Format_Patterns
 */
class DatesReader {

	/**
	 * @var \F3\FLOW3\I18n\Cldr\CldrRepository
	 */
	protected $cldrRepository;

	/**
	 * @var \F3\FLOW3\Cache\Frontend\VariableFrontend
	 */
	protected $cache;

	/**
	 * Static array of date / time formatters supported by this class, and
	 * maximal lengths particular formats can get.
	 *
	 * For example, era (G) can be defined in three formats, abbreviated (G, GG,
	 * GGG), wide (GGGG), or narrow (GGGGG), so maximal length is set to 5.
	 *
	 * When length is set to zero, it means that corresponding format has no
	 * maximal length.
	 *
	 * @var array
	 */
	static protected $maxLengthOfSubformats = array(
		'G' => 5,
		'y' => 0,
		'Y' => 0,
		'u' => 0,
		'Q' => 4,
		'q' => 4,
		'M' => 5,
		'L' => 5,
		'l' => 1,
		'w' => 2,
		'W' => 1,
		'd' => 2,
		'D' => 3,
		'F' => 1,
		'g' => 0,
		'E' => 5,
		'e' => 5,
		'c' => 5,
		'a' => 1,
		'h' => 2,
		'H' => 2,
		'K' => 2,
		'k' => 2,
		'j' => 2,
		'm' => 2,
		's' => 2,
		'S' => 0,
		'A' => 0,
		'z' => 4,
		'Z' => 4,
		'v' => 4,
		'V' => 4,
	);

	/**
	 * An array of parsed formats, indexed by format strings.
	 *
	 * Example of data stored in this array:
	 * 'HH:mm:ss zzz' => array(
	 *   'HH',
	 *   array(':'),
	 *   'mm',
	 *   array(':'),
	 *   'ss',
	 *   array(' '),
	 *   'zzz',
	 * );
	 *
	 * Please note that subformats are stored as array elements, and literals
	 * are stored as one-element arrays in the same array. Order of elements
	 * in array is important.
	 *
	 * @var array
	 */
	protected $parsedFormats;

	/**
	 * An array which stores references to formats used by particular locales.
	 *
	 * As for one locale there can be defined many formats (at most 2 format
	 * types supported by this class - date, time - multiplied by at most 4
	 * format lengths - full, long, medium, short), references are organized in
	 * arrays.
	 *
	 * Example of data stored in this array:
	 * 'pl' => array(
	 *     'date' => array(
	 *         'full' => 'EEEE, d MMMM y',
	 *         ...
	 *     ),
	 *     ...
	 * );
	 *
	 * @var array
	 */
	protected $parsedFormatsIndices;

	/**
	 * Associative array of literals used in particular locales.
	 *
	 * Locale tags are keys for this array. Values are arrays of literals, i.e.
	 * names defined in months, days, quarters etc tags.
	 *
	 * @var array
	 */
	protected $localizedLiterals;

	/**
	 * @param \F3\FLOW3\I18n\Cldr\CldrRepository $repository
	 * @return void
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function injectCldrRepository(\F3\FLOW3\I18n\Cldr\CldrRepository $repository) {
		$this->cldrRepository = $repository;
	}

	/**
	 * Injects the FLOW3_I18n_Cldr_Reader_DatesReader cache
	 *
	 * @param \F3\FLOW3\Cache\Frontend\VariableFrontend $cache
	 * @return void
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function injectCache(\F3\FLOW3\Cache\Frontend\VariableFrontend $cache) {
		$this->cache = $cache;
	}

	/**
	 * Constructs the reader, loading parsed data from cache if available.
	 *
	 * @return void
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function initializeObject() {
		if ($this->cache->has('parsedFormats') && $this->cache->has('parsedFormatsIndices') && $this->cache->has('localizedLiterals')) {
			$this->parsedFormats = $this->cache->get('parsedFormats');
			$this->parsedFormatsIndices = $this->cache->get('parsedFormatsIndices');
			$this->localizedLiterals = $this->cache->get('localizedLiterals');
		}
	}

	/**
	 * Shutdowns the object, saving parsed format strings to the cache.
	 * 
	 * @return void
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function shutdownObject() {
		$this->cache->set('parsedFormats', $this->parsedFormats);
		$this->cache->set('parsedFormatsIndices', $this->parsedFormatsIndices);
		$this->cache->set('localizedLiterals', $this->localizedLiterals);
	}

	/**
	 * Returns parsed date or time format basing on locale and desired format
	 * length.
	 *
	 * When third parameter ($formatLength) equals 'default', default format for a
	 * locale will be used.
	 *
	 * @param \F3\FLOW3\I18n\Locale $locale
	 * @param string $formatType A type of format (date, time)
	 * @param string $formatLength A length of format (full, long, medium, short) or 'default' to use default one from CLDR
	 * @return array An array representing parsed format
	 * @throws \F3\FLOW3\I18n\Cldr\Reader\Exception\UnableToFindFormatException When there is no proper format string in CLDR
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function parseFormatFromCldr(\F3\FLOW3\I18n\Locale $locale, $formatType, $formatLength) {
		if (isset($this->parsedFormatsIndices[(string)$locale][$formatType][$formatLength])) {
			return $this->parsedFormats[$this->parsedFormatsIndices[(string)$locale][$formatType][$formatLength]];
		}

		$model = $this->cldrRepository->getModelCollection('main', $locale);

		if ($formatLength === 'default') {
			$defaultChoice = $model->getRawArray('dates/calendars/calendar/type="gregorian"/' . $formatType . 'Formats/default');
			$defaultChoice = array_keys($defaultChoice);
			$realFormatLength = \F3\FLOW3\I18n\Cldr\CldrParser::getValueOfAttributeByName($defaultChoice[0], 'choice');
		} else {
			$realFormatLength = $formatLength;
		}

		$format = $model->getElement('dates/calendars/calendar/type="gregorian"/' . $formatType . 'Formats/' . $formatType . 'FormatLength/type="' . $realFormatLength . '"/' . $formatType . 'Format/pattern');

		if (empty($format)) {
			throw new \F3\FLOW3\I18n\Cldr\Reader\Exception\UnableToFindFormatException('Date / time format was not found. Please check whether CLDR repository is valid.', 1280218994);
		}

		if ($formatType === 'dateTime') {
				// DateTime is a simple format like this: '{0} {1}' which denotes where to insert date and time, it needs not to be parsed
			$parsedFormat = $format;
		} else {
			$parsedFormat = $this->doParsing($format);
		}

		$this->parsedFormatsIndices[(string)$locale][$formatType][$formatLength] = $format;
		return $this->parsedFormats[$format] = $parsedFormat;
	}

	/**
	 * Returns parsed date or time format string provided as parameter.
	 *
	 * @param string $format Format string to parse
	 * @return array An array representing parsed format
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function parseCustomFormat($format) {
		if (isset($this->parsedFormats[$format])) {
			return $this->parsedFormats[$format];
		}

		return $this->parsedFormats[$format] = $this->doParsing($format);
	}

	/**
	 * Returns literals array for locale provided.
	 *
	 * If array was not generated earlier, it will be generated and cached.
	 *
	 * @param \F3\FLOW3\I18n\Locale $locale
	 * @return array An array with localized literals
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	public function getLocalizedLiteralsForLocale(\F3\FLOW3\I18n\Locale $locale) {
		if (isset($this->localizedLiterals[(string)$locale])) {
			return $this->localizedLiterals[(string)$locale];
		}

		$model = $this->cldrRepository->getModelCollection('main', $locale);

		$localizedLiterals['months'] = $this->parseLocalizedLiterals($model, 'month');
		$localizedLiterals['days'] = $this->parseLocalizedLiterals($model, 'day');
		$localizedLiterals['quarters'] = $this->parseLocalizedLiterals($model, 'quarter');
		$localizedLiterals['dayPeriods'] = $this->parseLocalizedLiterals($model, 'dayPeriod');
		$localizedLiterals['eras'] = $this->parseLocalizedEras($model);

		return $this->localizedLiterals[(string)$locale] = $localizedLiterals;
	}

	/**
	 * Parses a date / time format (with syntax defined in CLDR).
	 *
	 * Not all features from CLDR specification are implemented. Please see the
	 * documentation for this class for details what is missing.
	 *
	 * @param string $format
	 * @return array Parsed format
	 * @throws \F3\FLOW3\I18n\Cldr\Reader\Exception\InvalidDateTimeFormatException When subformat is longer than maximal value defined in $maxLengthOfSubformats property
	 * @author Karol Gusak <firstname@lastname.eu>
	 * @see \F3\FLOW3\I18n\Cldr\Reader\DatesReader::$parsedFormats
	 */
	protected function doParsing($format) {
		$parsedFormat = array();
		$formatLengthOfFormat = strlen($format);
		$duringCompletionOfLiteral = FALSE;
		$literal = '';

		for ($i = 0; $i < $formatLengthOfFormat; ++$i) {
			$subformatSymbol = $format[$i];

			if ($subformatSymbol === '\'') {
				if ($i < $formatLengthOfFormat - 1 && $format[$i + 1] === '\'') {
						// Two apostrophes means that one apostrophe is escaped
					if ($duringCompletionOfLiteral) {
							// We are already reading some literal, save it and continue
						$parsedFormat[] = array($literal);
						$literal = '';
					}

					$parsedFormat[] = array('\'');
					++$i;
				} else if ($duringCompletionOfLiteral) {
					$parsedFormat[] = array($literal);
					$literal = '';
					$duringCompletionOfLiteral = FALSE;
				} else {
					$duringCompletionOfLiteral = TRUE;
				}
			} else if ($duringCompletionOfLiteral) {
				$literal .= $subformatSymbol;
			} else {
					// Count the length of subformat
				for ($j = $i + 1; $j < $formatLengthOfFormat; ++$j) {
					if($format[$j] !== $subformatSymbol) break;
				}
				
				$subformat = str_repeat($subformatSymbol, $j - $i);

				if (isset(self::$maxLengthOfSubformats[$subformatSymbol])) {
					if (self::$maxLengthOfSubformats[$subformatSymbol] === 0 || strlen($subformat) <= self::$maxLengthOfSubformats[$subformatSymbol]) {
						$parsedFormat[] = $subformat;
					} else throw new \F3\FLOW3\I18n\Cldr\Reader\Exception\InvalidDateTimeFormatException('Date / time pattern is too long: ' . $subformat . ', specification allows up to ' . self::$maxLengthOfSubformats[$subformatSymbol] . ' chars.', 1276114248);
				} else {
					$parsedFormat[] = array($subformat);
				}
				
				$i = $j - 1;
			}
		}

		if ($literal !== '') {
			$parsedFormat[] = array($literal);
		}

		return $parsedFormat;
	}

	/**
	 * Parses one CLDR child of "dates" node and returns it's array representation.
	 *
	 * Many children of "dates" node have common structure, so one method can
	 * be used to parse them all.
	 *
	 * @param \F3\FLOW3\I18n\Cldr\CldrModelCollection $model CldrModelCollection to read data from
	 * @param string $literalType One of: month, day, quarter, dayPeriod
	 * @return array An array with localized literals for given type
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	protected function parseLocalizedLiterals(\F3\FLOW3\I18n\Cldr\CldrModelCollection $model, $literalType) {
		$data = array();
		$context = $model->getRawArray('dates/calendars/calendar/type="gregorian"/' . $literalType . 's/' . $literalType . 'Context');

		foreach ($context as $contextType => $literalsWidths) {
			$contextType = \F3\FLOW3\I18n\Cldr\CldrParser::getValueOfAttributeByName($contextType, 'type');

			foreach ($literalsWidths[$literalType . 'Width'] as $widthType => $literals) {
				$widthType = \F3\FLOW3\I18n\Cldr\CldrParser::getValueOfAttributeByName($widthType, 'type');

				foreach ($literals[$literalType] as $literalName => $literalValue) {
					$literalName = \F3\FLOW3\I18n\Cldr\CldrParser::getValueOfAttributeByName($literalName, 'type');

					$data[$contextType][$widthType][$literalName] = $literalValue;
				}
			}
		}

		return $data;
	}

	/**
	 * Parses "eras" child of "dates" node and returns it's array representation.
	 *
	 * @param \F3\FLOW3\I18n\Cldr\CldrModelCollection $model CldrModel to read data from
	 * @return array An array with localized literals for "eras" node
	 * @author Karol Gusak <firstname@lastname.eu>
	 */
	protected function parseLocalizedEras(\F3\FLOW3\I18n\Cldr\CldrModelCollection $model) {
		$data = array();
		foreach ($model->getRawArray('dates/calendars/calendar/type="gregorian"/eras') as $widthType => $eras) {
			foreach ($eras['era'] as $eraName => $eraValue) {
				$eraName = \F3\FLOW3\I18n\Cldr\CldrParser::getValueOfAttributeByName($eraName, 'type');

				$data[$widthType][$eraName] = $eraValue;
			}
		}

		return $data;
	}
}

?>