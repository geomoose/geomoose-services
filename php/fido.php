<?php

# Fido - Very basic placename searching for GeoMOOSE.
# 
#  This parses the mapbook looking for map-sources which have "placename-search='true'" 
#  set and will read the mapfile and layers to generate a list of placenames 
#  that can be used in a basic client-side fuzzy-search.
#

# Copyright (c) 2009-2015, Dan "Ducky" Little & GeoMOOSE.org

# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:

# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.

# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
# THE SOFTWARE.


include('config.php');

$DEBUG = false;

# Turn off the warning reporting
if(!$DEBUG) {
	error_reporting(E_ERROR | E_PARSE);
}

# parse the mapbook
$mapbook = getMapbook();
$msXML = $mapbook->getElementsByTagName('map-source');

$RESULTS = '';

# iterate through the specified layers
for($map_source_i = 0; $map_source_i < $msXML->length; $map_source_i++) {
	$mapsource = $msXML->item($map_source_i);
	# check to see if this map-source is supported by fido
	if(parseBoolean($mapsource->getAttribute('fido-search'))) {
		# get the path to the mapfile
		$mapfile = $mapsource->getElementsByTagName('file')->item(0)->firstChild->nodeValue;

		# normalize the mapfile if it has a relative path.
		if(substr($mapfile,0,1) == '.') {
			$mapfile = $CONFIGURATION['root'].$mapfile;
		}
		$map = ms_newMapObj($mapfile);

		# turn off all the layers
		for($ml = 0; $ml < $map->numlayers; $ml++) {
			$layer = $map->getLayer($ml);
			$layer->set('status', MS_OFF);
			# setting the template to '' ensures it
			#  will not be included in queries.
			$layer->set('template', '');
		}

		# iterate through each layer and run the template process.
		for($ml = 0; $ml < $map->numlayers; $ml++) {
			# get the layer from the map.
			$layer = $map->getLayer($ml);
			# enable it if "fido_record" is available.
			$fido_record = $layer->getMetaData('fido_record');

			if($fido_record) {
				# enable the layer
				$layer->set('status', MS_DEFAULT);
				$layer->set('template', $fido_record);

				# query the map
				$ext = $layer->getExtent();
				$map->queryByRect($ext);

				# get the resutls
				$map_results = $map->processquerytemplate(array(), MS_FALSE);

				# split on the new lines, join with commas
				#   add to the results
				$map_results = implode(',', explode(PHP_EOL, $map_results));

				if($RESULTS) {
					$RESULTS = $RESULTS.','.$map_results;
				} else {
					$RESULTS = $map_results;
				}

				# trim trailing commas
				if(substr($RESULTS, -1) == ",") {
					$RESULTS = substr($RESULTS, 0, -1);
				}

				# disable the layer again.
				$layer->set('status', MS_OFF);
				$layer->set('template', '');
			}
		}

	}
}

# this kicks out JSON, so set the appropriate header.
header('Content-type: application/json');
# print out the results.
# @theduckylittle recognizes this is a bit of an evil way to approach this solution.
# A proper JSON parser and emitter would be better but this works well with the 
# mapserver/mapscript templating services and is really here for demo purposes.
print '['.$RESULTS.']';

?>
