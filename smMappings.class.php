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

class smMappings implements Iterator {
	public $sourcemap = null;

	private $_mappings = null;
	private $_position = 0;
	private $_segmentNumber = 0;

	private $_previousMinifiedColumn = 0;
	private $_previousSourcesIndex = 0;
	private $_previousOriginalLine = 0;
	private $_previousOriginalColumn = 0;
	private $_previousNamesIndex = 0;

	public function __construct(smSourcemap $sourcemap) {
		$this->sourcemap = $sourcemap;
	}

	public function import($string) {
		$this->_mappings = $string;
		$this->rewind();
	}

	public function export() {
		return $this->_mappings;
	}

	public function rewind() {
		$this->_position = 0;
		$this->_segmentNumber = 0;
	}

	public function current() {
		$start = $this->_position;
		$end = strpos($this->_mappings,';',$start);
		if ($end===false) {
			$end = strlen($this->_mappings);
		}
		$line = substr($this->_mappings,$start,$end-$start);

		return $this->parseLine($line);
	}

	public function key() {
		return $this->_segmentNumber;
	}

	public function next() {
		$pos = strpos($this->_mappings,';',$this->_position);
		if ($pos===false) {
			$pos = strlen($this->_mappings);
		}
		$this->_position = $pos+1;
		$this->_segmentNumber++;
	}

	public function valid() {
		return ($this->_position<strlen($this->_mappings));
	}

	public function parseLine($line) {
		$segments = array();
		if ($line) {
			$this->_previousMinifiedColumn = 0;
			$rawSegments = explode(',',$line);
			foreach ($rawSegments as $rawSegment) {
				// should actually decode the segments here
				$segments[] = $this->decodeSegment($rawSegment);
			}
		}
		return $segments;
	}

	private function decodeSegment($string) {

		if ($this->sourcemap->debug) {
			print 'DECODING '.str_pad($string,8).' | ';
		}

		// all the indexes are based on the results of the last segment
		$vlq = Base64VLQ::decode($string,true);

		if ($this->sourcemap->debug) {
			print str_pad(!isset($vlq[0])?'-':$vlq[0],4,' ',STR_PAD_LEFT).','.
				str_pad(!isset($vlq[1])?'-':$vlq[1],4,' ',STR_PAD_LEFT).','.
				str_pad(!isset($vlq[2])?'-':$vlq[2],4,' ',STR_PAD_LEFT).','.
				str_pad(!isset($vlq[3])?'-':$vlq[3],4,' ',STR_PAD_LEFT).','.
				str_pad(!isset($vlq[4])?'-':$vlq[4],4,' ',STR_PAD_LEFT).' | ';
		}

		switch (count($vlq)) {
			case 1:
			case 4:
			case 5:
				break;
			default:
				throw new smException('Invalid number of variables while decoding segment '.$string.'. Found '.count($vlq).', expecting 1, 4, or 5.');
		}

		$segment = array(
			'string'=>$string,
			'vlq'=>$vlq,
			'minifiedColumn'=>null,
			'sourcesIndex'=>null,
			'source'=>null,
			'originalLine'=>null,
			'originalColumn'=>null,
			'namesIndex'=>null,
			'name'=>null,
		);

		$segment['minifiedColumn'] = $this->_previousMinifiedColumn + $vlq[0];
		$this->_previousMinifiedColumn = $segment['minifiedColumn'];

		// figure out the new index for this source
		if (isset($vlq[1]) ||
			isset($vlq[2]) ||
			isset($vlq[3])) {

			$segment['sourcesIndex'] = $this->_previousSourcesIndex + $vlq[1];
			if (!array_key_exists($segment['sourcesIndex'],$this->sourcemap->sources)) {
				throw new smException('Invalid sources index ('.$segment['sourcesIndex'].') while parsing segment '.$string);
			}
			$segment['source'] = $this->sourcemap->sourceFromIndex($segment['sourcesIndex']);
			$this->_previousSourcesIndex = $segment['sourcesIndex'];

			$segment['originalLine'] = $this->_previousOriginalLine + $vlq[2];
			if ($segment['originalLine']<0) {
				throw new smException('Invalid source line ('.$segment['originalLine'].') while parsing segment '.$string);
			}
			$this->_previousOriginalLine = $segment['originalLine'];

			$segment['originalColumn'] = $this->_previousOriginalColumn + $vlq[3];
			if ($segment['originalColumn']<0) {
				throw new smException('Invalid source column ('.$segment['originalColumn'].') while parsing segment '.$string);
			}
			$this->_previousOriginalColumn = $segment['originalColumn'];

			// figure out the new index for this name
			if (isset($vlq[4])) {

				$segment['namesIndex'] = $this->_previousNamesIndex + $vlq[4];
				if (!array_key_exists($segment['namesIndex'],$this->sourcemap->names)) {
					throw new smException('Invalid names index ('.$segment['namesIndex'].') while parsing segment '.$string);
				}
				$segment['name'] = $this->sourcemap->nameFromIndex($segment['namesIndex']);
				$this->_previousNamesIndex = $segment['namesIndex'];

			}

		}

		if ($this->sourcemap->debug) {
			print str_pad(is_null($segment['minifiedColumn'])?'-':$segment['minifiedColumn'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['sourcesIndex'])?'-':$segment['sourcesIndex'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['originalLine'])?'-':$segment['originalLine'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['originalColumn'])?'-':$segment['originalColumn'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['namesIndex'])?'-':$segment['namesIndex'],4,' ',STR_PAD_LEFT).
				"\n";
		}

		return $segment;
	}

	private function encodeSegment($segment) {

		if ($this->sourcemap->debug) {
			print 'ENCODING ';
			print str_pad($this->_previousMinifiedColumn,4,' ',STR_PAD_LEFT).','.
				str_pad($this->_previousSourcesIndex,4,' ',STR_PAD_LEFT).','.
				str_pad($this->_previousOriginalLine,4,' ',STR_PAD_LEFT).','.
				str_pad($this->_previousOriginalColumn,4,' ',STR_PAD_LEFT).','.
				str_pad($this->_previousNamesIndex,4,' ',STR_PAD_LEFT).
				" | ";
			print str_pad(is_null($segment['minifiedColumn'])?'-':$segment['minifiedColumn'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['sourcesIndex'])?'-':$segment['sourcesIndex'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['originalLine'])?'-':$segment['originalLine'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['originalColumn'])?'-':$segment['originalColumn'],4,' ',STR_PAD_LEFT).','.
				str_pad(is_null($segment['namesIndex'])?'-':$segment['namesIndex'],4,' ',STR_PAD_LEFT).
				" | ";
		}

		$rawSegment = '';

		if (!is_null($segment['minifiedColumn'])) {

			$minifiedColumn = $segment['minifiedColumn'] - $this->_previousMinifiedColumn;
			$rawSegment .= Base64VLQ::encode($minifiedColumn);
			$this->_previousMinifiedColumn = $segment['minifiedColumn'];

			if (!is_null($segment['sourcesIndex']) ||
				!is_null($segment['originalLine']) ||
				!is_null($segment['originalColumn']) ||
				!is_null($segment['namesIndex'])) {

				if ($segment['source']) {
					$segment['sourcesIndex'] = $this->sourcemap->sourceToIndex($segment['source']);
				}
				$sourcesIndex = $segment['sourcesIndex'] - $this->_previousSourcesIndex;
				$this->_previousSourcesIndex = $segment['sourcesIndex'];
				$rawSegment .= Base64VLQ::encode($sourcesIndex);

				$originalLine = $segment['originalLine'] - $this->_previousOriginalLine;
				$this->_previousOriginalLine = $segment['originalLine'];
				$rawSegment .= Base64VLQ::encode($originalLine);

				$originalColumn = $segment['originalColumn'] - $this->_previousOriginalColumn;
				$this->_previousOriginalColumn = $segment['originalColumn'];
				$rawSegment .= Base64VLQ::encode($originalColumn);

				if (!is_null($segment['name'])) {

					$segment['namesIndex'] = $this->sourcemap->nameToIndex($segment['name']);
					$namesIndex = $segment['namesIndex'] - $this->_previousNamesIndex;
					$rawSegment .= Base64VLQ::encode($namesIndex);
					$this->_previousNamesIndex = $segment['namesIndex'];

				}

			}

		}


		if ($this->sourcemap->debug) {
			print str_pad($rawSegment,8)."\n";
		}

		return $rawSegment;
	}

	public function appendLine($line=array()) {
		$strings = array();
		$this->_previousMinifiedColumn = 0;
		foreach ($line as $segment) {
			$string = $this->encodeSegment($segment);
			$strings[] = $string;
		}
		$this->_mappings .= ($this->_mappings?';':'').implode(',',$strings);
	}

	public function __toString() {
		return $this->_mappings;
	}

	public function __debugInfo() {
		return $_mappings;
	}

}
