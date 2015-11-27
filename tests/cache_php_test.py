## Test cases for the new cache handler
#

import requests

from . import GeoMOOSETest

class CacheTest(GeoMOOSETest):

	def setUp(self):
		# get a cache id from a select request.
		self.select_php = "http://" + self.host + self.geomoose_base + "/php/query2.php"
		self.cache_php = "http://" + self.host + self.geomoose_base + "/php/cache.php"

		self.select_params = {
			"mode" : "search",
			"cache" : "true",
			"header0" : "select_header",
			"footer0" : "select_footer",
			"template0" : "select_record", 
			"layer0" : "parcels/parcels",
			"projection": "EPSG:3857",
			"shape0" : "POINT(-10373109.338156 5552992.5910145)",
			"shape0_layer" : "",
			"shape0_buffer" : "0",
			"shape0_layer_buffer" : "0"
		}

		super(CacheTest,self).setUp()

		results = self.post(self.select_php, params=self.select_params)
		# check to make sure we got a valid response from the server.
		self.assertEqual(results.status_code, 200, "Failed to get valid return from select_php.")

		# parse out a cache id 
		# This is not the most machine parseable format but it is something
		#  that is very consistent.
		# <b>Query ID:</b> gm_56589fdca66e0<br>
		contents = results.text
		id_start = contents.find('Query ID:</b>')+14
		id_end = contents.find('<br>', id_start)
		self.cacheId = contents[id_start:id_end]

		
	def test_json(self):
		"""
		Get the Results as GeoJSON
		"""
		import sys ; print >> sys.stderr, self.cacheId

		r = self.get(self.cache_php, params={'cache_id' : self.cacheId, 'mode' : 'results:geojson'})
		self.assertEqual(r.status_code, 200)

		import sys ; print >> sys.stderr, r.text
