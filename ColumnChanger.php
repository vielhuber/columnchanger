<?php
namespace App\Helpers;
class ColumnChanger
{

	public static $quotes;

	public static $path;

	public static function swap($table, $col1, $col2)
	{

		// initialize

		self::$quotes = self::getQuotes();
		self::$path = self::getPath();

		// export

		$content = self::exportDB();

		// swap

		$content = self::swapNow($table, $col1, $col2, $content);

		// import

		self::importDB($content);
	}

	public static function getQuotes()
	{
		if (getenv("DB_CONNECTION") == "pgsql")
		{
			return "";
		}

		if (getenv("DB_CONNECTION") == "mysql")
		{
			return "`";
		}
	}

	public static function getPath()
	{
		if (getenv("DB_CONNECTION") == "pgsql")
		{
			$sql = new \PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') , getenv('DB_USERNAME') , getenv('DB_PASSWORD'));
			$stmt = $sql->prepare("SHOW data_directory");
			$stmt->execute();
			$path = str_replace("data", "bin", $stmt->fetchObject()->data_directory) . "/";
		}

		if (getenv("DB_CONNECTION") == "mysql")
		{
			$sql = new \PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') , getenv('DB_USERNAME') , getenv('DB_PASSWORD'));
			$stmt = $sql->prepare("SHOW VARIABLES LIKE 'basedir'");
			$stmt->execute();
			$path = $stmt->fetchObject()->Value . "bin/";
		}

		return $path;
	}

	public static function exportDB()
	{
		if (getenv("DB_CONNECTION") == "pgsql")
		{
			putenv("PGPASSWORD=" . getenv('DB_PASSWORD'));
			exec('"' . self::$path . 'pg_dump" --clean --inserts -h ' . getenv('DB_HOST') . ' -p ' . getenv('DB_PORT') . ' -U ' . getenv('DB_USERNAME') . ' ' . getenv('DB_DATABASE') . ' > db.sql');
		}

		if (getenv("DB_CONNECTION") == "mysql")
		{
			exec('"' . self::$path . 'mysqldump" -h ' . getenv('DB_HOST') . ' --port ' . getenv('DB_PORT') . ' -u ' . getenv('DB_USERNAME') . ' -p"' . getenv('DB_PASSWORD') . '" ' . getenv('DB_DATABASE') . ' > db.sql');
		}

		return file_get_contents("db.sql");
	}

	public static function importDB($content)
	{
		file_put_contents("db.sql", $content);
		if (getenv("DB_CONNECTION") == "pgsql")
		{
			putenv("PGPASSWORD=" . getenv('DB_PASSWORD'));
			exec('"' . self::$path . 'psql" -h ' . getenv('DB_HOST') . ' -p ' . getenv('DB_PORT') . ' -U ' . getenv('DB_USERNAME') . ' -d ' . getenv('DB_DATABASE') . ' -1 -f db.sql');
		}

		if (getenv("DB_CONNECTION") == "mysql")
		{
			exec('"' . self::$path . 'mysql" -h ' . getenv('DB_HOST') . ' --port ' . getenv('DB_PORT') . ' -u ' . getenv('DB_USERNAME') . ' -p"' . getenv('DB_PASSWORD') . '" ' . getenv('DB_DATABASE') . ' --default-character-set=utf8 < db.sql');
		}

		unlink("db.sql");
	}

	public static function getPositions($haystack, $needle)
	{
		$positions = [];
		$lastPos = 0;
		while (($lastPos = strpos($haystack, $needle, $lastPos)) !== false)
		{
			$positions[] = $lastPos;
			$lastPos = $lastPos + strlen($needle);
		}

		return $positions;
	}

	public static function getEnd($content, $begin)
	{
		$end = $begin;
		$outside = true;
		while ($outside !== true || $content[$end] != ";")
		{
			if ($content[$end] == "'")
			{
				if ($end === 0 || $content[$end - 1] != "\\")
				{
					$outside = !$outside;
				}
			}

			$end++;
		}

		return ++$end;
	}

	public static function splitString($string)
	{
		return preg_split('/(?<!^)(?!$)/u', $string);
	}

	public static function getColumns($query)
	{
		$i = 0;
		$outside = true;
		$query = self::splitString($query);
		foreach($query as $i => $char)
		{
			if ($query[$i] == "'")
			{
				if ($i === 0 || $query[$i - 1] != "\\")
				{
					$outside = !$outside;
				}
			}

			if ($outside === true && $query[$i] == ",")
			{
				$query[$i] = "♥";
			}

			$i++;
		}

		$query = implode("", $query);
		$cols = explode("♥", $query);
		return $cols;
	}

	public static function swapNow($table, $col1, $col2, $content)
	{

		// loop through relevant statements

		foreach(["CREATE TABLE " . self::$quotes . $table . self::$quotes, "INSERT INTO " . self::$quotes . $table . self::$quotes] as $skey => $statement)
		{
			$positions = self::getPositions($content, $statement);
			foreach($positions as $position)
			{
				$begin = $position;
				$end = self::getEnd($content, $begin);
				$query_all = substr($content, $begin, $end - $begin);
				$query_inside = substr($query_all, strpos($query_all, "(") + 1, strrpos($query_all, ")") - strpos($query_all, "(") - 1);

				// get columns

				$cols = self::getColumns($query_inside);

				// get relevant column indexes

				if ($skey == 0)
				{
					$col1pos = 0;
					$col2pos = 0;
					foreach($cols as $pos => $col)
					{
						$col = trim($col);
						if (strpos($col, self::$quotes . $col1 . self::$quotes) === 0)
						{
							$col1pos = $pos;
						}

						if (strpos($col, self::$quotes . $col2 . self::$quotes) === 0)
						{
							$col2pos = $pos;
						}
					}
				}

				// swap columns

				$tmp = $cols[$col1pos];
				$cols[$col1pos] = $cols[$col2pos];
				$cols[$col2pos] = $tmp;
				$query_inside_new = implode(",", $cols);

				// insert query into content

				$query_all_new = str_replace($query_inside, $query_inside_new, $query_all);
				$content = str_replace($query_all, $query_all_new, $content);
			}
		}

		return $content;
	}
}

// usage from the command line

if (isset($argv) && is_array($argv))
{
	$args = [];
	foreach($argv as $key => $arg)
	{
		switch ($arg)
		{
		case "-e":
			$args["engine"] = $argv[$key + 1];
			break;

		case "-h":
			$args["hostname"] = $argv[$key + 1];
			break;

		case "-P":
			$args["port"] = $argv[$key + 1];
			break;

		case "-u":
			$args["username"] = $argv[$key + 1];
			break;

		case "-p":
			$args["password"] = $argv[$key + 1];
			break;

		case "-d":
			$args["database"] = $argv[$key + 1];
			break;

		case "-t":
			$args["table"] = $argv[$key + 1];
			break;
		}
	}

	// set default values

	if (!isset($args["hostname"]))
	{
		$args["hostname"] = "127.0.0.1";
	}

	if (!isset($args["port"]) && isset($args["engine"]) && $args["engine"] == "mysql")
	{
		$args["port"] = "3306";
	}

	if (!isset($args["port"]) && isset($args["engine"]) && $args["engine"] == "pgsql")
	{
		$args["port"] = "5432";
	}

	foreach($args as $option => $arg)
	{
		if (!isset($arg))
		{
			die('missing option ' . $option);
		}
	}

	if (count($argv) < 2)
	{
		die('error');
	}

	$args["col1"] = $argv[count($argv) - 2];
	$args["col2"] = $argv[count($argv) - 1];
	putenv("DB_CONNECTION=" . $args["engine"]);
	putenv("DB_HOST=" . $args["hostname"]);
	putenv("DB_PORT=" . $args["port"]);
	putenv("DB_DATABASE=" . $args["database"]);
	putenv("DB_USERNAME=" . $args["username"]);
	putenv("DB_PASSWORD=" . $args["password"]);
	ColumnChanger::swap($args["table"], $args["col1"], $args["col2"]);
	
}