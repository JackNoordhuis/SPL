<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

use Composer\Autoload\ClassLoader as ComposerClassLoader;

class ThreadedComposerClassLoader extends \Threaded implements \ClassLoader{

	/**
	 * thread-local variable for storing current loader instance
	 * @var ComposerClassLoader
	 */
	private static $composerAutoloader;
	/** @var bool[] path => bool */
	private static $alreadyAdded = [];

	/** @var string */
	private $autoloaderPath;

	/** @var \Threaded */
	private $dynamicPaths;

	public function __construct(string $autoloaderPath){
		$this->autoloaderPath = $autoloaderPath;
		$this->dynamicPaths = new \Threaded();
	}

	public function getComposerLoader() : ?ComposerClassLoader{
		return self::$composerAutoloader;
	}

	public function register($prepend = false){
		//composer always prepends itself
		self::$composerAutoloader = require $this->autoloaderPath;
		//make sure we're before the composer loader so we can inject extra classes
		spl_autoload_register([$this, 'loadClass'], true, true);
	}

	public function loadClass(string $class) : bool{
		if($this->dynamicPaths->count() > 0){
			foreach((array) $this->dynamicPaths as $path){
				if(!isset(self::$alreadyAdded[$path])){
					self::$composerAutoloader->add(false, $path);
					self::$alreadyAdded[$path] = true;
				}
			}
		}
		return false;
	}

	public function addPath($path, $prepend = false){
		if($prepend){
			$this->dynamicPaths->synchronized(function() use ($path) : void{
				$paths = $this->dynamicPaths->chunk($this->dynamicPaths->count());
				$this->dynamicPaths[] = $path;
				foreach($paths as $p){
					$this->dynamicPaths[] = $p;
				}
			});
		}else{
			$this->dynamicPaths[] = $path;
		}
	}
}
