## Geocoder Tests
#


import requests

from . import GeoMOOSETest

class GeocoderTest(GeoMOOSETest):

	def test_basic_google_geocode(self):
		"""
		Do a basic geocode
		"""

		# good ole american test address
		address = "1600 Pennsylvania Ave, Washington, DC"

		# path to our testing script
		url = "http://" + self.host + self.geomoose_base + "/php/google_geocoder.php"

		req = self.get(url, params={'address' : address})

		self.assertEqual(req.status_code, 200, "Returned bad status code (%d != 200)" % (req.status_code))

		#import sys ; print >> sys.stderr, req.text

		self.assertFalse('error-message' in req.text, "Returned error message.")

		self.assertTrue('-76.9818437,38.8791981' in req.text, "Failed to return expected coordinates")


