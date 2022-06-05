<?php

namespace Utils;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;

/**
 * Usar essa classe apenas para scripts de codegen
 * Não utilize para código em produção
 */
abstract class CodeGen {

	/**
	 * Realiza um glob recursivo no pattern informado
	 * @param string $pattern
	 * @param int $flags
	 * @return array
	 */
	public static function globRecursive($pattern, $flags = 0) {
		$files = glob($pattern, $flags);
		foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			$files = array_merge($files, self::globRecursive($dir . '/' . basename($pattern), $flags));
		}
		return $files;
	}

	public static function getClassFromFileName($file) {
		$class = str_replace("src/", "", $file);
		$class = "\\" . str_replace("/", "\\", $class);
		$class = str_replace(".php", "", $class);

		return $class;
	}

	public static function getViewName($file) {
		$class = self::getClassFromFileName($file);
		return (new ReflectionClass($class))->getShortName();
	}

	public static function getViewPath($file) {
		$path = [];
		$achou = false;
		foreach (explode("/", $file) as $part) {
			if ($part == "View") {
				$achou = true;
				continue;
			} elseif (!$achou) {
				continue;
			}

			$path[] = $part;
		}

		return str_replace(".php", "", implode("/", $path));
	}

	public static function getViewMethods($file) {
		$reflection = new \ReflectionClass(self::getClassFromFileName($file));
		$methods = [];
		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getShortName() == "__construct") {
				continue;
			}

			if (mb_strpos($method->getShortName(), "_") === 0) {
				continue;
			}

			$returnType = $method->getReturnType();

			$methods[] = [
				"name" => $method->getShortName(),
				"parameters" => self::getMethodParameters($method),
				"returnType" => self::getMethodReturnType($method),
			];
		}

		return $methods;
	}

	public static function getMethodParameters(ReflectionMethod $method) {
		$parameters = [];
		$methodDocParams = self::getMethodDocParameters($method);
		foreach ($method->getParameters() as $parameter) {
			$data = [
				"name" => $parameter->getName(),
				"type" => self::toTypescriptType($parameter, $methodDocParams),
				"optional" => $parameter->isOptional(),
			];

			$parameters[] = $data;
		}
		return $parameters;
	}

	private static function getTypeFromDefaultValue($value) {
		if (is_bool($value)) {
			return "bool";
		} elseif (is_string($value)) {
			return "string";
		} elseif (is_null($value)) {
			return "mixed";
		} elseif (is_array($value)) {
			return "array";
		} elseif (is_numeric($value)) {
			return "number";
		} elseif(is_int($value)) {
			return "number";
		} elseif (is_float($value)) {
			return "number";
		} else {
			return "mixed";
		}
	}

	/**
	 * @param ReflectionParameter|string $parameter
	 */
	public static function toTypescriptType($parameter, array $docParams = []): string {
		$type = "any";
		if ($parameter instanceof ReflectionType) {
			$type = $parameter->getType()->getName();
		} elseif (is_string($parameter)) {
			$type = $parameter;
		} elseif (isset($docParams[$parameter->getName()])) {
			$type = $docParams[$parameter->getName()];
		} elseif ($parameter->isOptional() && !is_null($parameter->getDefaultValue())) {
			$type = self::toTypescriptType(self::getTypeFromDefaultValue($parameter->getDefaultValue()), $docParams);
		}

		$isArray = fn ($type) => mb_strpos($type, "[]") > 0;
		$isTuple = fn ($type) => mb_strpos($type, "<") !== false && mb_strpos($type, ">") !== false && mb_strpos($type, ",") !== false && mb_strpos($type, "<") < mb_strpos($type, ">");

		$parseType = function ($type, $key = "", $val = "") use (&$parseType) {
			switch ($type) {
				case 'array':
				case 'object':
					if (!empty($key) && !empty($val)) {
						$parsedKey = $parseType($key);
						$parsedVal = $parseType($val);
						return "Record<" . $parsedKey . "," . $parsedVal . ">";
					}

					if (!empty($val)) {
						$parsedVal = $parseType($val);
						return $parsedVal . "[]";
					}

					return "Record<string, any>";

				case 'string':
				case 'void':
					return $type;
				case 'mixed':
					return "any";
				case 'int':
				case 'float':
					return 'number';
				case 'bool':
				case 'boolean':
					return 'boolean';
				default:
					return "any";
			}
		};

		$parseUnionType = function ($type) use ($isArray, $isTuple, $parseType) {
			$types = [];

			$fnGetStringBetweenStrings = function ($string, $first, $second) {
				$firstPos = mb_strpos($string, $first);
				$secondPos = mb_strpos($string, $second);
				if ($firstPos === false || $secondPos === false) {
					return "";
				}
				$firstPos += mb_strlen($first);
				return mb_substr($string, $firstPos, $secondPos - $firstPos);
			};

			foreach (explode("|", $type) as $_type) {
				if ($isArray($_type)) {
					$types[] = $parseType("array", "", trim($_type, "[]"));
					continue;
				}

				if ($isTuple($_type)) {
					$key = $fnGetStringBetweenStrings($_type, "<", ",");
					$val = $fnGetStringBetweenStrings($_type, ",", ">");
					$types[] = $parseType("array", $key, $val);
					continue;
				}

				$types[] = $parseType($_type);
			}

			return implode(" | ", $types);
		};

		$parsedType = $parseUnionType($type);

		return $parsedType;
	}

	public static function getMethodDocParameters(ReflectionMethod $method): array {
		$docComment = $method->getDocComment();
		if ($docComment === false) {
			return [];
		}

		$doc = str_replace(["/**", "*/", "\n", "\r", "\t"], "", $docComment);
		$lines = explode("*", $doc);
		$params = [];
		foreach ($lines as $key => &$line) {
			$line =	trim($line);
			$isDocParam = mb_strpos($line, "@param") === 0;
			if (!$isDocParam) {
				continue;
			}

			$paramString = str_replace("@param", "", $line);
			$parts = array_values(array_filter(explode(" ", $paramString), fn ($part) => !empty($part)));
			if (empty($parts) || count($parts) !== 2) {
				continue;
			}

			$paramName = str_replace("$", "", $parts[1]);
			$paramType = $parts[0];
			$params[$paramName] = $paramType;
		}

		return $params;
	}

	public static function getMethodReturnType(ReflectionMethod $method) {
		$returnType = $method->getReturnType();
		if ($returnType instanceof ReflectionType && !empty($returnType->getName())) {
			return self::toTypescriptType($returnType->getName());
		}

		$docComment = $method->getDocComment();
		if ($docComment === false) {
			return self::toTypescriptType("");
		}

		$doc = str_replace(["/**", "*/", "\n", "\r", "\t"], "", $docComment);
		$lines = explode("*", $doc);
		foreach ($lines as $key => &$line) {
			$line =	trim($line);
			$isDocParam = mb_strpos($line, "@return") === 0;
			if (!$isDocParam) {
				continue;
			}

			$paramString = str_replace("@return", "", $line);
			$parts = array_values(array_filter(explode(" ", $paramString), fn ($part) => !empty($part)));
			if (empty($parts) || count($parts) !== 1) {
				continue;
			}

			$type = $parts[0];

			return self::toTypescriptType($type);
		}

		return self::toTypescriptType("");
	}


	/**
	 * Retorna um array de métodos da classe que estão com a flag '@codegen replicate' no docblock
	 * @param string $class
	 * @return array
	 */
	public static function getMethodsToReplicate($class) {
		$methods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);
		$response = [];
		foreach ($methods as $method) {
			if (str_replace("\\", "", $method->class) != str_replace("\\", "", $class)) {
				continue;
			}

			$matches = [];
			$comment_string = $method->getdoccomment();
			$pattern = "#(@codegen [a-zA-Z]+\s*[a-zA-Z0-9, ()_].*)#";

			preg_match_all($pattern, $comment_string, $matches, PREG_PATTERN_ORDER);

			$comments = [];
			array_walk_recursive($matches, function ($value) use (&$comments) {
				$comments[trim($value)] = true;
			});

			if (empty($comments)) {
				continue;
			}

			$params = $method->getParameters();
			if (!empty($params) && !$method->isStatic()) {
				continue;
			}

			$name = $method->getName();
			$value = call_user_func([$class, $name]);
			if (!is_array($value)) {
				continue;
			}

			$value = json_encode($value, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);

			$response[$name] = $value;
		}

		return $response;
	}

}