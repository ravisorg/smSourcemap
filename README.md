# smSourcemap
Merge sourcemaps in PHP

##Usage

Usage is pretty easy. Create a combined map, load and append all your individual maps, and then
save the resulting merged map.

For example:

```php
require_once('smSourcemap.class.php');

# Mapping a local path to a URL allows the code to find the files it needs.
$commonPath = '/path/to/web/root/js';
$commonURL = 'https://www.example.com/js';

$merged = new smSourcemap();
$merged->addUrlMapping($commonPath,$commonURL);

# You can embed the original code in your sourcemap if you like.
$merged->includeOriginals = false;

$map1 = new smSourcemap();
$map1->addUrlMapping($commonPath,$commonURL);
$map1->loadOriginal($commonPath.'/script1.js');
$map1->loadMinified($commonPath.'/script1.min.js');
$map1->loadSourcemap($commonPath.'/script1.min.js.map');
$merged->appendSourcemap($map1);

$map2 = new smSourcemap();
$map2->addUrlMapping($commonPath,$commonURL);
$map2->loadOriginal($commonPath.'/script2.js');
$map2->loadMinified($commonPath.'/script2.min.js');
$map2->loadSourcemap($commonPath.'/script2.min.js.map');
$merged->appendSourcemap($map2);

# Specify where you're planning on keeping the combined sourcemap so the minified version can 
# reference it.
$merged->sourceMappingUrl = $commonURL.'/merged.min.js.map';

$merged->saveMinified($commonPath.'/merged.min.js');
$merged->saveSourcemap($commonPath.'/merged.min.js.map');
```

##Warning

This code works for me, however I haven't had much time to clean it up and test it properly for 
public distribution. As a result it may not work for you, or it may work partially, or it may
completely mangle the resulting merged maps. If you're not comfortable with that level of stability,
there are other programs that will merge sourcemaps in (eg) Node.js.

Pull Requests that help with cleaning up and fully testing this code would be appreciated.

##License

smSourcemap is licensed under the Modified BSD License (aka the 3 Clause BSD). Basically you can use it for any purpose, including commercial, so long as you leave the copyright notice intact and don't use my name or the names of any other contributors to promote products derived from smSourcemap.

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