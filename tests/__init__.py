#
# Common Classes and Functions for 
# testing
#

import unittest
import requests

class GeoMOOSETest(unittest.TestCase):
	host = "localhost"
	geomoose_base = "/geomoose2"

	def get(self, url, **kwargs):
		return requests.get(url, **kwargs)

	def post(self, url, **kwargs):
		return requests.post(url, **kwargs)


