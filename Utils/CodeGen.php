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
				"returnType" => [
					"docType" => self::getMethodReturnType($method),
					"type" => $returnType instanceof ReflectionType ? $returnType->getName() : null,
				],
			];
		}

		return $methods;
	}

	public static function getMethodParameters(ReflectionMethod $method) {
		$parameters = [];
		foreach ($method->getParameters() as $parameter) {
			$type = $parameter->getType();
			$data = [
				"name" => $parameter->getName(),
				"type" => $type instanceof ReflectionType ? $type->getName() : null,
				"docType" => self::getParameterDocType($method, $parameter->getName()),
				"optional" => $parameter->isOptional(),
			];

			if ($data["optional"]) {
				$data["defaultValue"] = $parameter->getDefaultValue();
			}

			$parameters[] = $data;
		}
		return $parameters;
	}

	public static function getParameterDocType(ReflectionMethod $method, $parameterName) {
		$docComment = $method->getDocComment();
		if ($docComment === false) {
			return null;
		}

		$doc = str_replace(["/**", "*/", "\n", "\r", "\t"], "", $docComment);
		$lines = explode("*", $doc);
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

			if ($paramName !== $parameterName) {
				continue;
			}

			$paramType = $parts[0];

			return $paramType;
		}

		return null;
	}

	public static function getMethodReturnType(ReflectionMethod $method) {
		$docComment = $method->getDocComment();
		if ($docComment === false) {
			return null;
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

			$returnType = $parts[0];

			return $returnType;
		}

		return null;
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