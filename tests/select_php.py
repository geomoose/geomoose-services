##
# Testing suite for select.php
# 

import requests

import re
import sys
from xml.dom import minidom

from copy import copy

from . import GeoMOOSETest

class ParcelTest(GeoMOOSETest):
	def setUp(self):
		self.select_php = "http://" + self.host + self.geomoose_base + "/php/select.php"
		self.default_params = {
			"layers" : "blank:blank/blank:borders:borders/city_labels:borders/county_labels:borders/city_poly:borders/county_borders:parcels:parcels/parcels:highlight:highlight/highlight:sketch",
			"projection": "EPSG:3857",
			"select_layer" : "parcels/parcels",
			"query_layer" : "",
			"shape_buffer" : "0",
			"selection_buffer" : "0",
			"shape" : "POINT(-10373109.338156 5552992.5910145)"
		}
		super(GeoMOOSETest,self).setUp()

	def check_parcels(self, paramOverrides, expectedParcels, pinPattern='(PIN:\<\/b\>\<\/td\>\<td\>)([0-9]+)'):
		"""
		Prototype function to sending a set of parameters
		to the select_php and checking that they are all there.
		"""
		params = copy(self.default_params)
		params.update(paramOverrides)

		results = self.post(self.select_php, params=params)
		# check to make sure we got a valid response from the server.
		self.assertEqual(results.status_code, 200, "Failed to get valid return form service.")

		# parse the XML
		try:
			doc = minidom.parseString(results.text)
		except:
			self.assertTrue(False, 'Failed to parse document:\n%s' % results.text)
		html = doc.getElementsByTagName('html')[0].firstChild.data
		html = html.replace('\n','')
		#<td><b>PIN:</b></td><td>130360001026</td>
		pin_re = re.compile(pinPattern)
		# pull out the PIN entries	
		parcel_ids = [x[1] for x in pin_re.findall(html)]

		# Some diagnostic outputs for when developing...
		# print >> sys.stderr, 'Parcel IDs', parcel_ids
		# print >> sys.stderr, 'Body', html

		# test for all the valid pins here.
		# expected IDs 
		for expected_id in expectedParcels:
			self.assertTrue(expected_id in parcel_ids, 'Parcel ID not found in results: '+expected_id)
				


class SelectTest(ParcelTest):
	def test_ticket24(self):
		"""
		Check that buffered parcels are returning a complete set.
		"""
		p = {
			"select_layer" : "parcels/parcels",
			"shape_buffer" : "0",
			"selection_buffer" : "30.479983540808888", # "100ft" converted to meters
			#"shape" : "POLYGON((-10372942.132157%205552892.2674148,-10373180.99787%205552682.0655871,-10372865.695129%205552672.5109586,-10372942.132157%205552892.2674148))"
			#"shape" : "POLYGON((-10373109.338156%205552992.5910145,-10373276.544156%205552710.7294727,-10372894.359014%205552720.2841012,-10373109.338156%205552992.5910145))"
			"shape" : "POLYGON((-10373109.338156 5552992.5910145,-10373276.544156 5552710.7294727,-10372894.359014 5552720.2841012,-10373109.338156 5552992.5910145))"

		}

		self.assertTrue(False, 'This test is currently failing')

		self.check_parcels(p, [])

	def test_ticket31(self):
		"""
		Testing LAYER versus GROUP versus ALL selection.
		"""
		expected_parcels = ['130270001077']
		layer_test = {
			"shape" : "POINT(-10374958.869833%205552691.0678879)"
		}
		self.check_parcels(layer_test, expected_parcels)
		group_test = {
			"shape" : "POINT(-10374958.869833%205552691.0678879)",
			"select_layer" : "parcels/parcels_group"
		}
		self.check_parcels(group_test, expected_parcels)
		all_test = {
			"shape" : "POINT(-10374958.869833%205552691.0678879)",
			"select_layer" : "parcels/all"
		}
		self.check_parcels(all_test, ['130270001077']);

