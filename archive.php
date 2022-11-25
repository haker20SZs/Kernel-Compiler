<?php

function preg_quote_array(array $strings, string $delim = null) : array{
	return array_map(function(string $str) use ($delim) : string{ return preg_quote($str, $delim); }, $strings);
}

function buildPhar(string $pharPath, string $basePath, array $includedPaths, string $stub, int $signatureAlgo = \Phar::SHA1, ?int $compression = null){

	if(file_exists(getcwd() . "/src")){
		yield "Все нужные файлы найденный идёт компиляция ядра";
	}else{
		yield "Не удалось найти ядро в формате src";
		exit();
	}

	if(file_exists($pharPath)){
		yield "Файл Phar уже существует, идёт перезапись...";
		try{
			\Phar::unlinkArchive($pharPath);
		}catch(\PharException $e){
			unlink($pharPath);
		}
	}

	yield "Добавление файлов...";
	$start = microtime(true);
	$phar = new \Phar($pharPath);
	$phar->setStub($stub);
	$phar->setSignatureAlgorithm($signatureAlgo);
	$phar->startBuffering();
	$excludedSubstrings = preg_quote_array([
		realpath($pharPath),
	], '/');
	$folderPatterns = preg_quote_array([
		DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
		DIRECTORY_SEPARATOR . '.'
	], '/');
	$basePattern = preg_quote(rtrim($basePath, DIRECTORY_SEPARATOR), '/');
	foreach($folderPatterns as $p){
		$excludedSubstrings[] = $basePattern . '.*' . $p;
	}

	$regex = sprintf('/^(?!.*(%s))^%s(%s).*/i',
		 implode('|', $excludedSubstrings),
		 preg_quote($basePath, '/'),
		 implode('|', preg_quote_array($includedPaths, '/'))
	);

	$directory = new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::CURRENT_AS_PATHNAME);
	$iterator = new \RecursiveIteratorIterator($directory);
	$regexIterator = new \RegexIterator($iterator, $regex);
	$count = count($phar->buildFromIterator($regexIterator, $basePath));
	yield "Добавлены $count файла";

	if($compression !== null){
		yield "Проверка сжимаемых файлов...";
		foreach($phar as $file => $finfo){
			if($finfo->getSize() > (1024 * 512)){
				yield "Идёт сжатие " . $finfo->getFilename();
				$finfo->compress($compression);
			}
		}
	}

	$phar->stopBuffering();
	yield "Завершен за " . round(microtime(true) - $start, 3) . "сек";
}

define('PATH', dirname(__FILE__, 1) . DIRECTORY_SEPARATOR);
define('DEVTOOLS_REQUIRE_FILE_STUB', '<?php require("phar://" . __FILE__ . "/%s"); __HALT_COMPILER();');

$pharPath = PATH . "PocketMine-MP.phar";
$stub = sprintf(DEVTOOLS_REQUIRE_FILE_STUB, "src/pocketmine/PocketMine.php");
$filePath = realpath(PATH) . DIRECTORY_SEPARATOR;
$filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

foreach(buildPhar($pharPath, $filePath, ['src', 'vendor'], $stub, \Phar::SHA1, \Phar::GZ) as $line){
	echo $line . "\n";
}

echo "Phar файл создан!\n";

?>