## GeoMoose Common Library

import os.path

import mapscript
import xmltodict


## Parse a ".ini" file in the same simplistic, somewhat silly way
#   that the old PHP did.
#
#  @param filename Path of the INI file.
#
# @return a dict of the settings
#
def parse_settings_file(filename):
	d = {}
	for line in open(filename):
		if('=' in line):
			sp = line.split('=')
			d[sp[0].strip()]= sp[1].strip()
	return d

## Get the current config from a conf path
#
#  @param confDir The configuration directory.
#
# @returns a dict of all the config settings
#
def get_config(confDir):
	conf_files = ['settings.ini','local_settings.ini']
	d = {}
	for cf in conf_files:
		fname = os.path.join(confDir, cf)
		if(os.path.isfile(fname)):
			tmp = parse_settings_file(fname)
			d.update(tmp)
	return d
	
## Get the raw mapbook contents
#
#  @param confDir The config directory
#  @param config The config from get_config
#
# @retruns Bytes-and-bytes of mapbook.
#
def get_mapbook(confDir, config):
	# get the mapbook path
	mapbook_path = os.path.join(confDir, config['mapbook'])
	# return the mapbook contents
	return open(mapbook_path).read()

## Parse the mapsources into something that is iterable
#
#  @param confDir the configuration directory
#  @param config Results of get_config
#
# @returns A dict of map-sources
#
def get_map_sources(confDir, config):
	mapbook_xml = get_mapbook(confDir, config)

	mb_dict = xmltodict.parse(mapbook_xml)
	d = {}
	for mapsource in mb_dict['mapbook']['map-source']:
		d[mapsource['@name']] = mapsource
	return d

## Split up a set of GeoMoose specified paths
#
#  @param pathString a ":" delimited list of "/" divided paths
#  @param grouped When true, group the layers by mapsource
#
# @returns List of paths, or a dict of lists if grouped is true
#
def parse_paths(pathString, grouped=False):
	paths = pathString.split(':')
	if(grouped):
		d = {}
		for path in paths:
			sp = path.split('/')
			if(sp[0] not in d):
				d[sp[0]] = []
			else:
				d[sp[0]].append(sp[1])
		return d
	else:
		return paths



## Have mapscript open
def map_source_to_mapfile(config, mapSource):
	mapfile_path = os.path.join(config['root'], mapSource['file'])

	return mapscript.mapObj(mapfile_path)


	


## Query a map-source given a shape and a set of layers in the mapsource
#  
#  @param config The config from get_config
#  @param mapSource the mapsource definition
#  @param layers the list of layers in the map source
#  @param shapeWkt WKT for querying the layers.
#  @param shapeProjcetion The projection for the WKT
#  @param templateMetadata The metadata item with the template files 
#
# @return the HTML.
def query_map_source_by_shape(config, mapSource, layers, shapeWkt, shapeProjection, templateMetadata):
	ms_map = map_source_to_mapfile(config, mapSource)

	# turn on/off the specific layers
	for idx in range(ms_map.numlayers):
		layer = ms_map.getLayer(idx)
		if(layers[0] == 'all' or layer.name in layers):
			layer.status = mapscript.MS_ON
			layer.template = layer.metadata.get(templateMetadata)
		else:
			layer.status = mapscript.MS_OFF
	
	# convert the WKT into a mapscript shape object.
	shape = mapscript.shapeObj_fromWKT(shapeWkt)


	input_proj = mapscript.projectionObj(shapeProjection)
	map_proj = mapscript.projectionObj(ms_map.getProjection())

	shape.project(input_proj, map_proj)


	# Query the map
	ms_map.queryByShape(shape)
	# get the html from the template
	html = ms_map.processQueryTemplate(None, None, 0)
	# return to the caller
	return html


