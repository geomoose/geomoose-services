#
# Common Classes and Functions for 
# testing
#

import unittest
import requests
import os
import os.path

class GeoMOOSETest(unittest.TestCase):
	host = "localhost:8080"
	geomoose_base = "/geomoose"
	mapserver_base = "/cgi-bin/mapserv"
	temp_dir = '/tmp/gm_tests/'

	def setUp(self):
		if(not os.path.isdir(self.temp_dir)):
			os.mkdir(self.temp_dir)

	def get(self, url, **kwargs):
		return requests.get(url, **kwargs)

	def post(self, url, **kwargs):
		return requests.post(url, **kwargs)


