<?php

include('service.php');

$DEBUG = false;

if(!$DEBUG) {
	error_reporting(E_ERROR | E_PARSE);
}

class QueryService extends Service {

	public function searchResults() {
		$nResults = $this->getResultCount();

		header('Content-type: application/xml; charset='.$this->conf['output-encoding']);
		print "<results n='".$nResults."'>";
		print "<script><![CDATA[";
		$qlayers = implode(':', $this->queryLayers);
		print "GeoMOOSE.turnLayerOn('$qlayers');\n";

		if(true) { #$highlightResults) {
			print "GeoMOOSE.changeLayerUrl('highlight', './php/query.php');";
			$partial_params = array();
			foreach($_REQUEST as $p => $v) {
				if($p != 'mode') {
					array_push($partial_params, sprintf("'%s' : '%s'", $p, $v));
				}
			}
			$partial_params[] = "'TRANSPARENT' : 'true'";
			$partial_params[] = "'FORMAT' : 'image/png'";
			$partial_params[] = "'LAYERS' : 'highlight'";
			$partial_params[] = "'MODE' : 'map'";
			print "GeoMOOSE.clearLayerParameters('highlight');";
			print "GeoMOOSE.updateLayerParameters('highlight', {".implode(',',$partial_params)."});";
			print "GeoMOOSE.turnLayerOn('highlight/highlight');";
			print "GeoMOOSE.refreshLayers('highlight/highlight');";
		}

		# If there is only one results ... zoom to it!
		# or zoom to the first result if requested.
		if(($nResults == 1 and $firstResult != false) or ($nResults >= 1 and $zoomToFirst == true)) {
			$bounds = $resultFeatures[0]->bounds;
			#$outputProjection = ms_newprojectionobj('epsg:4326');
			#$bounds->project($LATLONG_PROJ, $outputProjection);
			printf('GeoMOOSE.zoomToExtent(%f,%f,%f,%f, "EPSG:4326");', $bounds->minx, $bounds->miny, $bounds->maxx, $bounds->maxy);
		}
		print "]]></script>";
		print "<html><![CDATA[";

		if(!array_key_exists('query_header', $this->conf) or $this->conf['query_header'] == NULL) {
			$this->conf['query_header'] = $this->conf['itemquery_header'];
		}

		if(!array_key_exists('query_footer', $this->conf) or $this->conf['query_footer'] == NULL) {
			$this->conf['query_footer'] = $this->conf['itemquery_footer'];
		}

		$headerArray = file($this->conf['query_header']);
		$footerArray = file($this->conf['query_footer']);
		print implode('', $headerArray);
		# this is a bit of a flail but it prevents us from depending
		# on UTF8 or LATIN1 as being predictable inputs-and-outputs.
		print mb_convert_encoding($this->templateResults, $this->conf['output-encoding'], 'LATIN1,ASCII,JIS,UTF-8,EUC-JP,SJIS');
		print implode('', $footerArray);

		print "]]></html>";
		print "</results>";
	}

	public function mapResults() {
		$path = '';

		$dict = array();
		$mapfile = implode('', file($path.'itemquery/highlight.map'));
		$mapfile = processTemplate($mapfile, $dict);

		$highlight_map = ms_newMapObjFromString($mapfile); 
		$polygonsLayer = $highlight_map->getLayerByName('polygons');
		$pointsLayer = $highlight_map->getLayerByName('points');
		$linesLayer = $highlight_map->getLayerByName('lines');

		$poly_features = '';

		error_log('N FEATURES: '.sizeof($resultFeatures));

		for($i = 0; $i < sizeof($resultFeatures); $i++) {
			if($resultFeatures[$i]->type == MS_SHAPE_POINT) {
				$pointsLayer->addFeature($resultFeatures[$i]);
			} elseif($resultFeatures[$i]->type == MS_SHAPE_POLYGON) {
				$polygonsLayer->addFeature($resultFeatures[$i]);
			} elseif($resultFeatures[$i]->type == MS_SHAPE_LINE) {
				$linesLayer->addFeature($resultFeatures[$i]);
			}
		}

		# get the WMS parameters.
		$request = ms_newowsrequestobj();
		$request->loadparams();

		# handle the wms request
		ms_ioinstallstdouttobuffer();

		$highlight_map->owsdispatch($request);
		$contenttype = ms_iostripstdoutbuffercontenttype();

		# put the image out to the stdout with the content-type attached
		header('Content-type: '.$contenttype);
		ms_iogetStdoutBufferBytes();
		ms_ioresethandlers();

	}

	public function resultsMiss() {
		header('Content-type: text/xml');
		print '<results><html><![CDATA[';
		print implode('', file($this->conf['query_miss']));
		print ']]><!-- query miss --></html></results>';
	}
}

# get the mapbook from the environment
$mapbook = getMapbook();

# run the service
$service = new QueryService($mapbook, $CONFIGURATION, $debug=$DEBUG);
$service->run();

?>
