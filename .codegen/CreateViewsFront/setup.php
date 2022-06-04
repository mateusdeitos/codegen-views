<?php

require_once "Utils/CodeGen.php";

$views = [];
foreach (\Utils\CodeGen::globRecursive("src/Services/View/*.php") as $file) {
	require_once $file;

	$views[] = [
		"name" => \Utils\CodeGen::getViewName($file),
		"path" => \Utils\CodeGen::getViewPath($file),
		"methods" => \Utils\CodeGen::getViewMethods($file),
	];
};

echo json_encode($views, JSON_PRETTY_PRINT);