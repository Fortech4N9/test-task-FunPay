<?php

namespace FpDbTest;
use Exception;
use mysqli;
require_once("/mnt/c/Users/treyy/OneDrive/Рабочий стол/FpDbTest/FpDbTest/DatabaseInterface.php");
class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

	public function buildQuery(string $query, array $args = []): string
	{
		$skipToken = $this->skip();

		$escapeValue = function ($value) {
			if (is_null($value)) return 'NULL';
			if (is_bool($value)) return $value ? '1' : '0';
			if (is_string($value)) return "'" . $this->mysqli->real_escape_string($value) . "'";
			if (is_int($value) || is_float($value)) return $value;
			throw new Exception("Invalid value type");
		};

		$query = preg_replace_callback(
			'/\{[^{}]*(\?\#|\?a).*?[^{}]*\}/',
			function ($matches) use ($skipToken) {
				return strpos($matches[0], $skipToken) !== false ? '' : $matches[0];
			},
			$query
		);

		$offset = 0;
		foreach ($args as $arg) {
			if ($arg === $skipToken) {
				continue;
			}

			$placeholder = substr($query, $offset);
			$pos = strpos($placeholder, '?');
			if ($pos === false) {
				throw new Exception("Not enough placeholders in the query");
			}
			$pos += $offset;

			$specifier = $query[$pos + 1] ?? '';
			switch ($specifier) {
				case 'd':
					$replacement = intval($arg);
					$pos++;
					break;
				case 'f':
					$replacement = floatval($arg);
					$pos++;
					break;
				case 'a':
					if (!is_array($arg)) {
						throw new Exception("Expected an array for ?a placeholder");
					}
					$replacement = implode(', ', array_map($escapeValue, $arg));
					$pos++;
					break;
				case '#':
					if (is_array($arg)) {
						$replacement = implode(', ', array_map($escapeValue, $arg));
					} else {
						$replacement = "`" . $this->mysqli->real_escape_string($arg) . "`";
					}
					$pos++;
					break;
				default:
					$replacement = $escapeValue($arg);
					break;
			}

			$query = substr_replace($query, $replacement, $pos, 1);
			$offset = $pos + strlen($replacement);
		}

		$query = preg_replace('/\{[^{}]*\}/', '', $query);

		return $query;
	}

	public function skip()
	{
		return uniqid('skip_', true);
	}
}
