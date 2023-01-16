<?php
/**
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Exception class for serialised data parsing
 */
class AUtilsSerialisedDecodingException extends Exception {};

/**
 * Exception class for serialised data encoding
 */
class AUtilsSerialisedEncodingException extends Exception {};

/**
 * A class to manipulate PHP serialised data without using unserialise(). This is useful if the serialised data contains
 * references to classes which are not present in the PHP code which replaces the data.
 */
class AUtilsSerialised
{
	/**
	 * The simplest and fastest approach, as of September 2018. We use regular expressions to split the serialized
	 * data at the string boundaries, then replace the strings and adjust the length.
	 *
	 * @param $serialized
	 * @param $from
	 * @param $to
	 *
	 * @return string
	 */
	public function replaceWithRegEx($serialized, $from, $to)
	{
		$pattern  = '/s:(\d{1,}):\"/iU';
		$exploded = preg_split($pattern, $serialized, -1, PREG_SPLIT_DELIM_CAPTURE);

		$lastLen = null;

		$exploded = array_map(function ($piece) use (&$lastLen, $from, $to) {
			// Numeric pieces are the string lengths
			if (is_numeric($piece))
			{
				$lastLen = (int) $piece;

				return '';
			}

			// If we have not encountered a string length we are processing the first chunk of the serialised data
			if (is_null($lastLen))
			{
				return $piece;
			}

			// I expect $lastLen + 2 characters (double quote, string, double quote). Break the piece in two parts.
			$toReplace   = substr($piece, 0, $lastLen);
			$theRestOfIt = substr($piece, $lastLen + 1);
			$isInception = $this->isSerialised($toReplace);

			/**
			 * Replace data in the first part.
			 *
			 * If the string contains serialized data we recurse. Otherwise we do a straight up string replacements.
			 * Serialized data inside a string in serialized data (much like the dream-world-inside-a-dream-world
			 * depicted in the movie Inception) is something that reeks of horrid architecture and quite common in the
			 * WordPress world.
			 */
			$toReplace = $isInception ? $this->replaceWithRegEx($toReplace, $from, $to) : str_replace($from, $to, $toReplace);

			// Get the new piece's length
			$newLength = function_exists('mb_strlen') ? mb_strlen($toReplace, 'ASCII') : strlen($toReplace);

			// New piece is s:newLength:"replacedString"TheRestOfIt
			$lastLen = null;

			return 's:' . $newLength . ':"' . $toReplace . '"' . $theRestOfIt;
		}, $exploded);

		// Remove the empty strings
		return implode("", $exploded);
	}

	/**
	 * Does this string look like PHP serialised data? Please note that this is a quick pre-test. You cannot be sure
	 * that it's valid serialised data until you try decoding it.
	 *
	 * @param string $string The string to test
	 *
	 * @return boolean True if it looks like serialised data
	 */
	public function isSerialised($string)
	{
		$scalar = array('s:', 'i:', 'b:', 'd:', 'r:');
		$structured = array('a:', 'O:');

		// Is it null?
		if ($string == 'N;')
		{
			return true;
		}

		// Is it scalar?
		if (in_array(substr($string, 0, 2), $scalar))
		{
			return substr($string, -1) == ';';
		}

		// Is it structured?
		if (!in_array(substr($string, 0, 2), $structured))
		{
			return false;
		}

		// Do we have a semicolon to denote the object length?
		$semicolonPos = strpos($string, ':', 3);

		if ($semicolonPos === false)
		{
			return false;
		}

		// Do we have another semicolon afterwards?
		$secondPos = strpos($string, ':', $semicolonPos + 1);

		if ($secondPos === false)
		{
			return false;
		}

		// Is the length an integer?
		$length = substr($string, $semicolonPos + 1, $secondPos - $semicolonPos - 1);

		return (int)$length == $length;
	}
}
