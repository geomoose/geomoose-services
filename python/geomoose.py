#!/usr/bin/env python

## GeoMoose Python Services All Wrapped Up
#
#

from os import environ
import os.path

from flask import Flask, Response, request

import geomoose

# initialize the app global
geomoose_app = Flask(__name__, static_folder='../../js', static_url_path='/js')

## Return the mapbook
#
@geomoose_app.route("/mapbook")
def get_mapbook():
	conf_dir = environ['GEOMOOSE_CONF']
	conf = geomoose.get_config(conf_dir)

	mapbook_xml = geomoose.get_mapbook(conf_dir, conf)
	# add the mapserver url and mapfile root 
	params = """
		<configuration>
		<param name="mapserver_url">%(mapserver_url)s</param>
		<param name="mapfile_root">%(root)s</param>
	""" % conf

	# this is a bit of a hack, for two reasons:
	#  1. we are modifying XML using plain strings instead of trying to be all DOM-y
	#  2. Some people may not set a configuration block and this demands that one exists.
	if('<configuration>' in mapbook_xml):
		mapbook_xml = mapbook_xml.replace('<configuration>', params)
	else:
		return Response('Mapbook is missing a <configuration> section', status_code=400)

	return Response(mapbook_xml, mimetype="text/xml")

## Identify a set of layers given a whole pile of optional
#  parameters.
#
@geomoose_app.route("/service/identify", methods=['GET', 'POST'])
def identify():
	conf_dir = environ['GEOMOOSE_CONF']
	conf = geomoose.get_config(conf_dir)
	ms_dict = geomoose.get_map_sources(conf_dir, conf)

	# list of the layers to identify against
	paths = geomoose.parse_paths(request.args.get('layers'), grouped=True)
	# EPSG string describing the WKT projection
	projection = request.args.get('projection')
	# WKT Shape
	shape = request.args.get('shape')
	
	html = open(conf['identify_header'], 'r').read()
	for map_source in paths:
		if(map_source in ms_dict and ms_dict[map_source]['@type'] == 'mapserver'):
			html += geomoose.query_map_source_by_shape(conf, ms_dict[map_source], paths[map_source], 
			                                    shape, projection, 'identify_record')

	footer_html = open(conf['identify_footer'], 'r').read()

	results = "<results>"
	results += "<script>";
	results += " GeoMOOSE.clearLayerParameters('highlight');";
	results += " GeoMOOSE.turnLayerOff('highlight/highlight');";
	results += "</script>";
	results += "<html><![CDATA[";
#	results += processTemplate($contents, $substArray);
	results += html
	results += "]]></html>";
	results += "<footer><![CDATA[";
	results += footer_html
#processTemplate($footer_contents, $substArray);
	results += "]]></footer>";
	results += "</results>";

	return Response(results, mimetype="text/xml")


if(__name__ == "__main__"):
	
	geomoose_app.run(port=8080)

