<?php
/*Copyright (c) 2015, Dan "Ducky" Little & GeoMOOSE.org

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.*/

/** Minimally compliant server for handing out the contents of the cache.
 *  This can be used to return the cache as a WMS or WFS.
 */

include('service.php');

## Parent class/interface for all of the various cache renderers.
#
class RenderCache {
	protected $cacheHelper;

	protected $cacheId = null;

	protected $cacheContents = null;

	## Constructor
	# 
	#  @param $config Reference to $CONFIGURATION (usually)
	#  @param $cacheId The Cache ID from the request.
	#  @param $debug  Whether to print debug messages from the error log, defaults to false.
	public function __construct($config, $cacheId, $debug=false) {
		$this->conf = $config;
		$this->cacheHelper = new CacheHelper($config, $debug);
		$this->cacheId = $cacheId;
	}

	## Main event loop for the request.   
	#
	#  The function that is always called by the inherited services.
	#
	public function run() {
		try {
			# contents are always JSON 
			$this->cacheContents = json_decode($this->cacheHelper->read($this->cacheId), true);
		} catch (NotFoundException $e) {
			# throw an httperror
			HttpError(404, "Cache item not found.");
			# short circuit
			return false;

		}

		# let the render happen.
		$this->render();
	}

	## Function to be handled by subclasses that will render the cache contents in a specific way.
	#
	public function render() {
	}

}

# JSON handler. 
# As the cached results are stored in a JSON format this becomes pretty
#  easy to handle.
#
class JsonCacheHandler extends RenderCache {
	## Consturctor
	#
	#  @param $subset The 'subset' of the cache to return enum (input_shapes, results, query_shapes)
	#
	public function __construct($config, $cacheId, $subset, $debug=false) {
		parent::__construct($config, $cacheId, $debug);
		$this->subset = $subset;
	}

	public function render() {
		# let's kick out some AWESOME
		header('Content-type: application/json; charset='.$this->conf['output-encoding']);
		print json_encode($this->cacheContents[$this->subset]);
	}
}


## Main Event Loop
#
function main() {
	global $CONFIGURATION;

	# get the render mode
	$mode = get_request_icase('mode');
	if(!isset($mode)) {
		HttpError(400, "Missing mode parameter");
		return false;
	} else {
		$mode = strtolower($mode);
	}

	# get the cache id
	$cache_id = get_request_icase('cache_id');
	if(!isset($mode)) {
		HttpError(400, "Missing valid cache_id parameter");
		return false;
	}

	$handler = null;
	# get the GeoJSON item containing the results set
	if($mode == 'results:geojson') {
		$handler = new JsonCacheHandler($CONFIGURATION, $cache_id, 'results');
	}

	if($handler == null) {
		HttpError(400, "Invalid mode.");
		return false;
	} else {
		$handler->run();
	}
}

# run the main loop
main();

?>
