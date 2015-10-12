<?php
/*
Copyright (c) 2009-2015, Dan "Ducky" Little & GeoMOOSE.org

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

#
# Base class for GeoMOOSE Query Services
# (c) 2009-2015 Dan "Ducky" Little
#

include('config.php');

class Comparitor {
	protected $p = array();

	public function __construct($msFormat, $sqlFormat) {
		$this->p['ms'] = $msFormat;
		$this->p['sql'] = $sqlFormat;
	}

	public function toMapServer($field_name, $value) {
		return sprintf($this->p['ms'], $field_name, $value);
	}

	public function toSQL($field_name, $value) {
		return sprintf($this->p['sql'], $field_name, $value);
	}

}

class Operator {
	protected $ms_format = "";
	protected $sql_format = "";

	public function __construct($msFormat, $sqlFormat) {
		$this->ms_format = $msFormat;
		$this->sql_format = $sqlFormat;
	}

	public function toMapServer($v) {
		return sprintf($this->ms_format, $v);
	}

	public function toSQL($v) { 
		return sprintf($this->sql_format, $v);
	}
}

#
# In is "special" and requires a dedicated class
# Mostly, to deal with the fact that value = an array and
# different datasets will want to deal with the delimiter
# in a variable fashion. Frankly, we need more SQL injection filtering.
#
class InComparitor {
	protected $p = array();
	public function __construct() {
		$this->p['delim'] = ';';
	}

	public function setDelim($d) {
		$this->p['delim'] = $d;
	}

	public function convert_value($value, $out_delim) {
		return implode($out_delim, array_map('trim', explode($this->p['delim'], $value)));
	}

	public function toMapServer($field_name, $value) {
		return sprintf('"[%s]" in "%s"', $field_name, $this->convert_value($value, ","));
	}

	public function toSQL($field_name, $value) {
		return sprintf("%s in ('%s')", $field_name, $this->convert_value($value, "','"));
	}
}

#
# This is a special case of the incomparitor that 
# also performs an 'all caps' search.  This is used by Orgeon Counties
# and could be potential useful for others who are searching data
# with a legacy standard of some sort.
#
class InUpperCaseComparitor extends InComparitor {
	public function convert_value($value, $out_delim) {
		return strtoupper(parent::convert_value($value, $out_delim));
	}
}

# 
# This comparitor was contirbuted by Oregon Counties. It's
# a bit modified from its original form but gets the job done.
#
class LikeAllComparitor extends InComparitor {
	public function __construct() {
		$this->p['delim'] = ' ';
		$this->p['delim-join'] = ' AND ';
		# if multiple field names are passed in, 
		#  then we join them with the multi-op field.
		$this->p['multi-op'] = ' AND ';
		$this->p['field-delim'] = ',';
	}
	
	public function clean_values($value) {
		return array_map('trim', array_map('strtoupper', explode($this->p['delim'], $value)));
	}

	private function join_fields($field_name, $value, $logic) {
		$fields = explode($this->p['field-delim'], $field_name);
		$atoms = array();
		foreach($fields as $fname) {
			$arr = array();
			foreach($this->clean_values($value) as $v) {
				$arr[] = sprintf($logic, $fname, $v);
			}
			$atoms[]  = '('.implode($this->p['delim-join'], $arr).')';
		}
		return implode($this->p['multi-op'], $atoms); 
	}

	public function toMapServer($field_name, $value) {
		return $this->join_fields($field_name, $value, '("[%s]" ~* "%s")');
	}

	public function toSQL($field_name, $value) {
		return $this->join_fields($field_name, $value, "%s like '%%'||%s||'%%'");
	}
}

#
# Allows for any fields to match any value.
#
class LikeAnyComparitor extends LikeAllComparitor {
	public function __construct() {
		parent::__construct();
		$this->p['delim'] = ' ';
		$this->p['delim-join'] = ' OR ';
		# if multiple field names are passed in, 
		#  then we join them with the multi-op field.
		$this->p['multi-op'] = ' OR ';
		$this->p['field-delim'] = ',';
	}
}


class Predicate {
	protected $self = array();
	
	/*
	 * field_name = Field Name to search
	 * value = value to test against
	 * operator = operator class
	 * comparitor = comparitor class
	 * blank_okay (boolean) = set whether or not a blank value should be evaluated
	 */

	public function __construct($layer, $field_name, $value, $operator, $comparitor, $blank_okay = true) {
		$this->self['layer'] = $layer;
		$this->self['fname'] = $field_name;
		$this->self['val'] = $value;
		$this->self['op'] = $operator;
		$this->self['comp'] = $comparitor;
		$this->self['blank'] = $blank_okay;
	}

	public function getLayer() {
		return $this->self['layer'];
	}

	public function toMapServer() {
		if(((string)$this->self['val'] == '') and $this->self['blank']) {
			return '';
		}
		return $this->self['op']->toMapServer($this->self['comp']->toMapServer($this->self['fname'], $this->self['val']));
	}

	public function toSQL() {
		return $this->self['op']->toSQL($this->self['comp']->toSQL($this->self['fname'], $this->self['val']));
	}
}




class Service {
	protected $comparitors = array();
	protected $operators = array();
	protected $predicates = array();
	protected $queryLayers = array();
	protected $queryTemplates = array();

	protected $templateResults = '';
	protected $resultFeatures = array();
	protected $resultCount = 0;

	protected $mode = '';

	protected $mapbook;

	# TODO: Throw this farther down in the class.

	## Get the number of results.
	public function getResultCount() {
		return $this->resultCount;
	}

	public function __construct($mapbook, $config, $debug=false) {
		$this->mapbook = $mapbook;
		$this->conf = $config;

		$this->DEBUG = true;

		# string specific operations
		# mapserver doesn't quite honor this the way I'd like it to but at the very least,
		# the SQL databases will support it.
		$cmps = array();
		$cmps['eq-str'] = new Comparitor('"[%s]" == "%s"', "%s = '%s'");
		$cmps['like'] = new Comparitor('"[%s]" =~ /.*%s.*/', "%s like '%%%s%%'");
		$cmps['left-like'] = new Comparitor('"[%s]" =~ /.*%s/', "%s like '%%%s'");
		$cmps['right-like'] = new Comparitor('"[%s]" =~ /%s.*/', "%s like '%s%%'");
		$cmps['like-icase'] = new Comparitor('"[%s]" ~* "%s"', "upper(%s) like '%%'||upper('%s')||'%%'");
		$cmps['left-like-icase'] = new Comparitor('"[%s]" ~* "%s$"', "%s like '%%'||upper('%s')");
		$cmps['right-like-icase'] = new Comparitor('"[%s]" ~* "^%s"', "%s like upper('%s')||'%%'");

		# all other types
		$cmps['eq'] = new Comparitor('[%s] == %s', "%s = %s");
		$cmps['ge'] = new Comparitor('[%s] >= %s', '%s >= %s');
		$cmps['gt'] = new Comparitor('[%s] > %s', '%s > %s');
		$cmps['le'] = new Comparitor('[%s] <= %s', '%s <= %s');
		$cmps['lt'] = new Comparitor('[%s] < %s', '%s < %s');

		$cmps['in'] = new InComparitor();
		$cmps['in-ucase'] = new InUpperCaseComparitor();
		$cmps['like-all'] = new LikeAllComparitor();
		$cmps['like-any'] = new LikeAnyComparitor();

		$this->comparitors = $cmps;


		# MS, SQL formats
		# this is probably a little redundant but C'est la Vie.
		$ops = array();
		$ops['init'] = new Operator('(%s)', '%s');
		$ops['and'] = new Operator('AND (%s)', 'and %s');
		$ops['or'] = new Operator('OR (%s)', 'or %s');
		$ops['nand'] = new Operator('AND (NOT (%s))', 'and not (%s)');
		$ops['nor'] = new Operator('OR (NOT (%s))', 'or not (%s)');

		$this->operators = $ops;
	}

	public function parseQuery() {
		# the mode!
		$this->mode = get_request_icase('mode');
		if(!isset($mode)) {
			if(get_request_icase('service') == 'WMS') {
				$mode = 'map';
			}
		}
		$highlightResults = parseBoolean(get_request_icase('highlight'));
		$zoomToFirst = parseBoolean(get_request_icase('zoom_to_first'));

		# layers to search
		$this->queryLayers= array();
		$this->queryLayers[0] = get_request_icase('layer0');

		# this will check to see which template format should be used
		# query/itemquery/select/popup/etc.
		$this->queryTemplates = array();
		$this->queryTemplates[0] = get_request_icase('template0');

		if($this->DEBUG) {
			error_log("Got parameters.<br/>");
		}

		# get set of predicates
		# I've only allowed for 255 right now... people will have to deal with this
		for($i = 0; $i < 255; $i++) {
		#	if(array_key_exists('operator'.$i, $_REQUEST) and $_REQUEST['operator'.$i] != NULL or $i == 0) {
			if(isset_icase('operator'.$i) or get_request_icase('operator'.$i) != NULL or $i == 0) {
				# see if the layer is different
				$layer = $this->queryLayers[0];
				if(isset_icase('layer'.$i)) {
					$layer = get_request_icase('layer'.$i);
				}
				
				$template = $this->queryTemplates[0];
				if(isset_icase('template'.$i)) {
					$template = get_request_icase('template'.$i);
				}

				if(!in_array($layer, $this->queryLayers) and $i > 0) {
					$this->queryLayers[] = $layer;
					$this->queryTemplates[] = $template;
				}
				# check the opeartor
				$operator = false; $comparitor = false;

				if($i == 0) {
					$operator = $this->operators['init'];
				} else if(isset_icase('operator'.$i) and $this->operators[get_request_icase('operator'.$i)]) {
					$operator = $this->operators[get_request_icase('operator'.$i)];
				} else {
					# return error saying no valid operator found
				}

				if(isset_icase('comparitor'.$i) and $this->comparitors[get_request_icase('comparitor'.$i)]) {
					$comparitor = $this->comparitors[get_request_icase('comparitor'.$i)];
				} else {
					# return error saying there is no valid comparitor
				}

				$blank_okay = true;
				if(isset_icase('blanks'.$i) and strtolower(get_request_icase('blanks'.$i)) == 'false') {
					$blank_okay = false;
				}


				# if a value is not set for subsequent inputs, use the first input
				# this allows queries to permeate across multiple layers
				if(isset_icase('value'.$i)) {
					$value = get_request_icase('value'.$i);
					$p = new Predicate($layer, get_request_icase('fieldname'.$i), $value, $operator, $comparitor, $blank_okay);
					# put the predicates on the internal stack
					$this->predicates[] = $p;
				}

			}
		}

		if($this->DEBUG) {
			error_log("Parsed.<br/>");
		}
	}


	public function withEachFeature($feature) {
	}


	public function queryLayers() {
		# These are all the connection types, we ID the ones to be used as SQL versus MS regular expressions
		# MS_INLINE, MS_SHAPEFILE, MS_TILED_SHAPEFILE, 
		#  MS_OGR, MS_TILED_OGR, MS_POSTGIS, MS_WMS, 
		#  MS_ORACLESPATIAL, MS_WFS, MS_GRATICULE, MS_MYGIS, MS_RASTER, MS_PLUGIN
		$SQL_LAYER_TYPES = array(MS_POSTGIS, MS_ORACLESPATIAL);
		$NOT_SUPPORTED = array(MS_INLINE, MS_WMS, MS_WFS, MS_GRATICULE, MS_RASTER, MS_PLUGIN, MS_OGR);

		$LATLONG_PROJ = ms_newprojectionobj('epsg:4326');

		# parse the mapsources from the mapbook		
		$map_sources = $this->mapbook->getElementsByTagName('map-source');
		#TODO: This loops is not as efficient as it could be, realistiically,
		#      this should use in_array to only loop through the map-sources once.
		for($la = 0; $la < sizeof($this->queryLayers); $la++) {
			# get the layer.
			for($map_source_i = 0; $map_source_i < $map_sources->length; $map_source_i++) {
				$node = $map_sources->item($map_source_i);
				$layers = $node->getElementsByTagName('layer');
				for($l = 0; $l < $layers->length; $l++) {
					$layer = $layers->item($l);
					$layerName = $layer->getAttribute('name');
					$path = $node->getAttribute('name').'/'.$layerName;
					if($path == $this->queryLayers[$la]) {
						$file = $node->getElementsByTagName('file')->item(0)->firstChild->nodeValue;
						# Okay, now it's time to cook
						if(substr($file,0,1) == '.') {
							$file = $this->conf['root'].$file;
						}
						$map = ms_newMapObj($file);

						# Create an array of query layers
						$queryLayers = array();
						if($layerName == 'all') {
							for($ml = 0; $ml < $map->numlayers; $ml++) {
								array_push($queryLayers, $map->getLayer($ml));
							}
						} else {
							# Turn on the specific layer
							array_push($queryLayers, $map->getLayerByName($layerName));
						}

						# Iterate through the queryLayers...
						foreach($queryLayers as $queryLayer) {
							$ext = $queryLayer->getExtent();
							if($this->DEBUG) {
								error_log(implode(',', array($ext->minx,$ext->miny,$ext->maxx,$ext->maxy)));
								error_log("<br/>extent'd.<br/>");
							}

							$predicate_strings = array();
							$is_sql = in_array($queryLayer->connectiontype, $SQL_LAYER_TYPES);
							for($i = 0; $i < sizeof($this->predicates); $i++) {
								if($this->predicates[$i]->getLayer() == $this->queryLayers[$la]) {
									if($is_sql) {
										$predicate_strings[] = $this->predicates[$i]->toSQL();
									} else {
										$predicate_strings[] = $this->predicates[$i]->toMapServer();
									}
								}
							}
							# the filter string.
							$filter_string = implode(' ', $predicate_strings);

							# diag message
							if($this->DEBUG) {
							  error_log( 'Search Layer: '.$this->queryLayers[$la].' Template: '.$this->queryTemplates[$la].' FILTER: '.$filter_string);
							  error_log( $is_sql);
							  error_log( $queryLayer->getMetaData($this->queryTemplates[$la]));
							}

							$queryLayer->set('status', MS_DEFAULT);

							if($queryLayer->getMetadata('itemquery_header')) {
								$queryLayer->set('header', $queryLayer->getMetadata('itemquery_header'));
							}
							if($queryLayer->getMetadata('itemquery_footer')) {
								$queryLayer->set('footer', $queryLayer->getMetadata('itemquery_footer'));
							}
							# we no long need to delineate between handling of SQL and Shapefile type layers.
							if($filter_string) {
								# WARNING! This will clobber existing filters on a layer.  
								if($is_sql) {
									$queryLayer->setFilter($filter_string);
								} else {
									$queryLayer->setFilter('('.$filter_string.')');
								}

							}
							$queryLayer->set('template', $queryLayer->getMetaData($this->queryTemplates[$la]));

							$queryLayer->open();
							if($this->DEBUG) { error_log('queryLayer opened'); }

							#$queryLayer->whichShapes($ext); #queryLayer->getExtent());
							$queryLayer->queryByRect($ext);
							if($this->DEBUG) { error_log('queryLayer queried'); }


							$numResults = 0;

							$projection = $map->getProjection();
							if($queryLayer->getProjection() != NULL) {
								$projection = $queryLayer->getProjection();
							}
							if($projection != NULL) {
								# reproject the query shape as available.
								$projection = ms_newProjectionObj($projection);
							}
			
							for($i = 0; $i < $queryLayer->getNumResults(); $i++) {	
								$shape = $queryLayer->getShape($queryLayer->getResult($i));
								if($projection) {
									$shape->project($projection, $LATLONG_PROJ);
								}
								$this->withEachFeature($shape);
								$this->resultFeatures[] = $shape;
								$numResults += 1;
							}
							if($this->DEBUG) { error_log('queryLayer iterated through.'); }

							$this->resultCount += $numResults;
							if($this->DEBUG) {
								error_log('Total Results: '.$numResults);
							}

							if($this->DEBUG) { error_log('qLayer finished'); }

							$map->queryByRect($ext);
							$results = $map->processquerytemplate(array(), MS_FALSE);
							#if($DEBUG) { error_log('Results from MS: '.$results); }
							$this->templateResults = $this->templateResults . $results;
							#if($DEBUG) { error_log('Current content'); error_log($content); error_log('end current content'); }
						}
					}
				}
			}

		}
	}


	public function handleBuiltinMode() {
		if($this->getResultCount() == 0) {
			# TODO: This is not set right now because the GeoMOOSE client does
			#       not properly handle non-200 erro codes yet.
			#http_response_code(404);
			$this->resultsMiss();
		} else {
			if($this->mode == '') {
				http_response_code(400);
				header('Content-type: text/html');
				print '<html><body>Error! Unknown mode!</body></html>';
			} elseif($this->mode == 'search') {
				$this->searchResults();
			} elseif($this->mode == 'map') {
				$this->mapResults();
			} elseif($this->mode == 'results' or $this->mode == 'raw') {
				header("Content-type: text/plain");
				print $service->templateResults;
			}
		}
	}

	public function searchResults() {
	}

	public function mapResults() {
	}

	## Called when there are no results found.
	public function resultsMiss() {
	}


	## Main function, parses the query, runs the query,
	#  returns the results based on the mode that was set.
	public function run() {
		$this->parseQuery();
		$this->queryLayers();
		$this->handleBuiltinMode();
	}

}

?>
