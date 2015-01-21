<?php

/*
smSourcemap is licensed under the Modified BSD License (aka the 3 Clause BSD). Basically you can 
use it for any purpose, including commercial, so long as you leave the copyright notice intact and 
don't use my name or the names of any other contributors to promote products derived from 
smSourcemap.

	Copyright (c) 2012, ravisorg
	All rights reserved.
	
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
	    * Redistributions of source code must retain the above copyright
	      notice, this list of conditions and the following disclaimer.
	    * Redistributions in binary form must reproduce the above copyright
	      notice, this list of conditions and the following disclaimer in the
	      documentation and/or other materials provided with the distribution.
	    * Neither the name of the author nor the names of its contributors may 
	      be used to endorse or promote products derived from this software 
	      without specific prior written permission.
	
	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL TRAVIS RICHARDSON BE LIABLE FOR ANY
	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF HTIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

require_once(__DIR__.'/Base64VLQ.php');
require_once(__DIR__.'/smMappings.class.php');

class smSourcemap {
	public $version = 3;
	public $file = null;
	public $sourceRoot = null;
	public $sources = array();
	public $sourcesContent = array();
	public $names = array();
	public $mappings = array();

	public $sourceMappingUrl = null;
	public $includeOriginals = false;

	public $debug = false;

	private $_sourcesFlipped = array();
	private $_namesFlipped = array();
	private $_urlMappings = array();
	private $_minified = null;
	private $_sourcemap = null;

	public function __construct() {
		$this->mappings = new smMappings($this);
	}

	public function setDefaultPath($path,$url) {

	}

	public function addUrlMapping($path,$url) {
		$path = realpath($path);
		$this->_urlMappings[$path] = $url;
	}

	public function urlToLocalFilename($url) {
		$longestLocalUrl = '';
		$longestLocalPath = '';
		foreach ($this->_urlMappings as $localPath=>$localUrl) {
			$match = strpos($url,$localUrl);
			if ($match===0 && strlen($localUrl)>strlen($longestLocalUrl)) {
				$longestLocalUrl = $localUrl;
				$longestLocalPath = $localPath;
			}
		}
		if ($longestLocalUrl) {
			$suffix = substr($url,strlen($longestLocalUrl));
			$suffix = str_replace('/',DIRECTORY_SEPARATOR,$suffix);
			return $longestLocalPath.$suffix;
		}
		return null;
	}

	public function localFilenameToUrl($path) {
		$path = realpath($path);
		$longestLocalUrl = '';
		$longestLocalPath = '';
		foreach ($this->_urlMappings as $localPath=>$localUrl) {
			$match = strpos($path,$localPath);
			if ($match===0 && strlen($localPath)>strlen($longestLocalPath)) {
				$longestLocalUrl = $localUrl;
				$longestLocalPath = $localPath;
			}
		}
		if ($longestLocalPath) {
			$suffix = substr($path,strlen($longestLocalPath));
			$suffix = str_replace(DIRECTORY_SEPARATOR,'/',$suffix);
			return $longestLocalUrl.$suffix;
		}
		return null;
	}

	public function setMinified($minifiedString,$autoload=true) {
		// make sure there's a single blank line at the end of the file
		$minifiedString = rtrim($minifiedString,"\r\n")."\r\n";

		// if the sourcemap hasn't been loaded yet, try and load it
		if (!$this->_sourcemap && $autoload) {
			if (preg_match('/^\/\/[\#\@] *sourceMappingUrl *\= *([^\r\n]*)$(\r\n|\r|\n)?/im',$minifiedString,$temp)) {
				$this->sourceMappingUrl = trim($temp[1]);
				$localFilename = $this->urlToLocalFilename($this->sourceMappingUrl);
				if ($localFilename) {
					$this->loadSourcemap($localFilename);
				}
			}
		}

		// remove the sourcemappingurl from the file (we can add it back later if needed)
		$this->_minified = preg_replace('/^\/\/[\#\@] *sourceMappingUrl *\= *[^\r\n]*(\r\n|\r|\n)?/im','',$minifiedString);
	}

	public function getMinified($includeMappingUrl=true) {
		$minified = $this->_minified;
		if ($this->sourceMappingUrl && $includeMappingUrl) {
			$minified .= "//# sourceMappingUrl=".$this->sourceMappingUrl."\r\n";
		}
		return $minified;
	}

	public function loadMinified($filename) {
		$realFilename = $this->checkReadPath($filename);
		$this->file = $this->localFilenameToUrl($realFilename);
		$this->setMinified(file_get_contents($realFilename));
	}

	public function saveMinified($filename,$includeMappingUrl=true) {
		$realDirname = $this->checkWritePath($filename);
		$this->file = $this->localFilenameToUrl($filename);
		file_put_contents($filename,$this->getMinified($includeMappingUrl));
	}

	public function setSourcemap($sourcemapString) {
		// if the first line of either map file is ")]}'", strip it and the rest of the first line 
		// off (it's part of the spec and there to break js compilers to help prevent XSS attacks)
		$sourcemapString = preg_replace("/^\)\]\}\'.*?(\r\n|\r|\n)/","",$sourcemapString);

		$this->_sourcemap = $sourcemapString;

		$sourcemapJson = json_decode($this->_sourcemap);

		if ($sourcemapJson->version!==3) {
			throw new smException('Can only import version 3 source maps');
		}
		foreach (array('version','file','names','sources','sourcesContent','sourceRoot') as $k) {
			if (isset($sourcemapJson->$k)) {
				$this->$k = $sourcemapJson->$k;
			}
		}
		// if there are sources, and there's a source root, combine them
		if ($this->sources && $this->sourceRoot) {
			foreach ($this->sources as &$source) {
				$source = $this->sourceRoot.$source;
				unset($source);
			}
			$this->sourceRoot = null;
		}
		// if there are any included source files, include them all
		if ($this->sourcesContent) {
			foreach ($this->sourcesContent as $temp) {
				if ($temp) {
					$this->includeOriginals = true;
					break;
				}
			}
		}
		if (isset($sourcemapJson->mappings)) {
			$mappings = $sourcemapJson->mappings;
		}
		else {
			$mappings = '';
		}
		// make sure there's a single blank line at the end of the file
		$mappings = rtrim($mappings,';').';';
		// always ensure we have a sources array
		if (!$this->sources) {
			$this->sourceToIndex($this->file);
		}
		$this->mappings->import($mappings);
	}

	public function getSourcemap() {
		$data = array('version'=>$this->version);
		if (isset($this->file)) {
			$data['file'] = $this->file;
		}
		// see if we can find a common root for all the sources
		$sources = $this->sources;
		$sourceRoot = $this->sourceRoot;
		if ($sources && count($sources)>1 && !$sourceRoot) {
			// we don't really want to find substrings inside a directory path, we only want to 
			// match full directory parts.
			$parts = explode('/',$sources[0]);
			$root = array();
			for ($partNum=0; $partNum<count($parts); $partNum++) {
				for ($t=1; $t<count($sources); $t++) {
					$sourceParts = explode('/',$sources[$t]);
					if ($sourceParts[$partNum]!==$parts[$partNum]) {
						break 2;
					}
				}
				$root[] = $parts[$partNum];
			}
			// if we found a common root, use it
			if ($root) {
				$sourceRoot = implode('/',$root);
				// if there are still parts left on the path that didn't match, append a directory
				// separator.
				if ($parts) {
					$sourceRoot .= '/';
				}
			}
			// now remove the new sourceRoot from all the sources
			foreach ($sources as &$source) {
				$source = substr($source,strlen($sourceRoot));
				unset($source);
			}
		}
		// only write out the sourceRoot if there's at least 2 sources
		if ($sources && count($sources)>1 && isset($sourceRoot)) {
			$data['sourceRoot'] = $sourceRoot;
		}
		// only write out the sources if there is more than one of them
		if ($sources && count($sources)>1) {
			ksort($this->sources);
			$data['sources'] = $sources;
		}
		$data['mappings'] = $this->mappings->export();
		if ($this->includeOriginals) {
			$sourcesContent = array();
			foreach ($this->sources as $k=>$url) {
				if (!array_key_exists($k,$this->sourcesContent)) {
					$this->addOriginal($this->sources[$k]);
				}
			}
			ksort($this->sourcesContent);
			$data['sourcesContent'] = $this->sourcesContent;
		}
		if (isset($this->names)) {
			$data['names'] = $this->names;
		}
		return json_encode($data);
	}

	public function loadSourcemap($filename) {
		$realFilename = $this->checkReadPath($filename);
		$this->setSourcemap(file_get_contents($realFilename));
	}

	public function saveSourcemap($filename) {
		$realFilename = $this->checkWritePath($filename);
		file_put_contents($realFilename,$this->getSourcemap());
	}

	public function addOriginal($url,$contents=null) {
		if (!$contents) {
			$contents = $this->fetchUrl($url);
		}
		$key = $this->sourceToIndex($url);
		$this->sourcesContent[$key] = $contents;
	}

	public function getOriginal($url) {
		$key = $this->sourceToIndex($url);
		if (!array_key_exists($key,$this->sourcesContent) || !$this->sourcesContent[$key]) {
			$this->sourcesContent[$key] = $this->fetchUrl($url);
		}
	}

	public function loadOriginal($filename,$url=null) {
		if (!$url) {
			$url = $this->localFilenameToUrl($filename);
		}
		$this->addOriginal($url,file_get_contents($filename));
	}

	public function appendSourcemap(smSourcemap $sourcemap) {

		// remap and append all the mapping lines (they'll be different for everything after the 
		// first file, because mappings are all relative to the previous mapping)
		foreach ($sourcemap->mappings as $line) {
			$this->mappings->appendLine($line);
		}

		// append the second file
		$this->_minified .= $sourcemap->getMinified(false);

		// remove reference(s) to the sourceMappingUrl, if any, as they'll be incorrect once the two maps
		// are merged. Initially the spec said this should be at the beginning of the file, then it
		// was moved to the end, so we'll check for and remove both possibilities. We also support
		// both //# (new style) and //@ (old style) for this line
		$this->sourceMappingUrl = null;

		// append the sources, if needed
		if ($sourcemap->sources && $sourcemap->sourcesContent) {
			foreach ($sourcemap->sources as $k=>$sourceUrl) {
				if (array_key_exists($k,$sourcemap->sourcesContent) && $sourcemap->sourcesContent[$k]) {
					$this->addOriginal($k,$sourcemap->sourcesContent[$k]);
				}
			}
		}

	}

	private function checkReadPath($path) {
		$path = realpath($path);
		if (!$path || !file_exists($path)) {
			throw new smException($path.' does not exist or is not readable');
		}
		if (!is_file($path)) {
			throw new smException($path.' is not a file');
		}
		if (!is_readable($path)) {
			throw new smException($path.' is not readable (permissions?)');
		}
		return $path;
	}

	private function checkWritePath($inpath) {
		$path = realpath($inpath);
		if (file_exists($path)) {
			if (!is_file($path)) {
				throw new smException($path.' is not a file');
			}
			if (!is_writable($path)) {
				throw new smException($path.' is not writable (permissions?)');
			}
		}
		else {
			$dir = realpath(dirname($inpath));
			if (!$dir || !file_exists($dir)) {
				throw new smException('Output directory ('.$dir.') does not exist');
			}
			if (!is_dir($dir)) {
				throw new smException('Output directory ('.$dir.') is not a directory');
			}
			if (!is_writable($dir)) {
				throw new smException('Output directory ('.$dir.') is not writable');
			}
			$path = $dir.DIRECTORY_SEPARATOR.basename($inpath);
		}
		return $path;
	}

	private function fetchUrl($url) {
		// try to get it off the local filesystem first
		$filename = $this->urlToLocalFilename($url);
		if ($filename && file_exists($filename) && is_readable($filename)) {
			return file_get_contents($filename);
		}
		return file_get_contents($url);
	}

	/**
	 * If the source doesn't already exist, it's added and the source index is returned. 
	 * If it does exist, nothing is changed and the source index is returned.
	 */
	public function sourceToIndex($filename) {
		if (!array_key_exists($filename,$this->_sourcesFlipped)) {
			$key = array_search($filename,$this->sources);
			if ($key===false) {
				$key = count($this->sources);
				$this->sources[$key] = $filename;
			}
			$this->_sourcesFlipped[$filename] = $key;
		}
		return $this->_sourcesFlipped[$filename];
	}

	public function sourceFromIndex($index) {
		if (!$this->sources) {
			return null;
		}
		if (array_key_exists($index,$this->sources)) {
			$name = $this->sources[$index];
			$_this->_sourcesFlipped[$name] = $index;
			return $name;
		}
		throw new smException('Invalid source index '.$index);
	}

	/**
	 * If the name doesn't already exist, it's added and the name index is returned. 
	 * If it does exist, nothing is changed and the name index is returned.
	 */
	public function nameToIndex($name) {
		if (!array_key_exists($name,$this->_namesFlipped)) {
			$key = array_search($name,$this->names);
			if ($key===false) {
				$key = count($this->names);
				$this->names[$key] = $name;
			}
			$this->_namesFlipped[$name] = $key;
		}
		return $this->_namesFlipped[$name];
	}

	public function nameFromIndex($index) {
		if (!$this->names) {
			return null;
		}
		if (array_key_exists($index,$this->names)) {
			$name = $this->names[$index];
			$_this->_namesFlipped[$name] = $index;
			return $name;
		}
		throw new smException('Invalid names index '.$index);
	}

	public function __isset($name) {
		switch ($name) {
			case 'version':
			case 'file':
			case 'sourceRoot':
			case 'sources':
			case 'sourcesContent':
			case 'names':
			case 'mappings':
				if (is_null($this->$name)) {
					return false;
				}
				return true;
		}
	}

	public function __debugInfo() {
		$data = array('version'=>$this->version);
		if (isset($this->file)) {
			$data['file'] = $this->file;
		}
		if (isset($this->sourceRoot)) {
			$data['sourceRoot'] = $this->sourceRoot;
		}
		if (isset($this->sources)) {
			$data['sources'] = $this->sources;
		}
		if (isset($this->sourcesContent)) {
			$data['sourcesContent'] = $this->sourcesContent;
		}
		if (isset($this->names)) {
			$data['names'] = $this->names;
		}
		$data['mappings'] = $this->mappings;
		return $data;
	}

	public function __toString() {
		return $this->exportJson();
	}

}




class smException extends Exception {}
