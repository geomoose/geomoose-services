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
		self.select_php = "http://" + self.host + self.geomoose_base + "/php/query2.php"
		self.default_params = {
			"mode" : "search",
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
		super(ParcelTest,self).setUp()

	def fetchHighlightImage(self, selectionId, testName):
		params = {
			"BBOX" : "-10375087.146263,5551951.1365041,-10371676.143877,5554320.6843807",
			"FORMAT": "image/png",
			"HEIGHT": "496",
			"LAYERS" : "highlight",
			"MAP": selectionId,
			"SRS": "EPSG:3857",
			"TRANSPARENT": "true",
			"WIDTH": "714",
			"REQUEST" : "GetMap",
			"SERVICE": "WMS",
			"STYLES": "",
			"VERSION": "1.1.1"
		}
		response = self.get("http://"+self.host+self.mapserver_base, params=params)
		# TODO: Test response results...
		f = open(self.temp_dir+'/'+testName+'.png', 'w')
		f.write(response.content)
		f.close()

		return response



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

		# replacing all the newlines was causing very odd
		#  buffer over flows on long strings, so we just split
		#  each line and parse it individually.
		html = results.text.split('\n')
		# print >> sys.stderr, 'RAW', results.text

		# print >> sys.stderr, 'HTML', html
		#<td><b>PIN:</b></td><td>130360001026</td>
		pin_re = re.compile(pinPattern, re.UNICODE)
		# pull out the PIN entries	
		parcel_ids = []

		for line in html:
			found_ids = [x[1] for x in pin_re.findall(line)]
			parcel_ids += found_ids

		#print >> sys.stderr, 'Found Parcels', parcel_ids

		# test for all the valid pins here.
		# expected IDs 
		missing_parcels = []
		for expected_id in expectedParcels:
			if(expected_id not in parcel_ids):
				missing_parcels.append(expected_id)

		self.assertEqual(len(missing_parcels), 0, 'Parcel ID not found in results: '+';'.join(missing_parcels))

		# now test that we didn't get *more* parcels
		# than we were looking to find.
		expected_set = set(expectedParcels)
		found_set = set(parcel_ids)
		diff = found_set.difference(expected_set)
		
		#print >> sys.stderr, 'Found Length: ', len(found_set), 'Diff Length:', len(diff)

		self.assertTrue(len(diff) == 0, 'More parcels returned than expected: %s' % ';'.join(list(diff)))

		return results
				


class SelectTest(ParcelTest):
	def test_ticket24(self):
		"""
		Check that buffered parcels are returning a complete set.
		"""
		p = {
			"layer0" : "parcels/parcels",
			"shape0_layer" : "parcels/parcels",
			"shape0_buffer" : "0",
			"shape0_layer_buffer" : "30.479983540808888", # "100ft" converted to meters
			"shape0" : "POINT(-10372932.577528 5552764.4742582)"

		}

		# this list was generated with postgresql/postgis,
		#  on 19 October 2015, theduckylittle
		expected_parcels = [
		 "130250001050",
		 "130260001201",
		 "130260001150",
		 "130260001051",
		 "130260001275",
		 "130260001175",
		 "130350002001",
		 "130350001002",
		 "130350001025",
		 "130360001026",
		 "130260001101",
		 "130260001025",
		]

		# this data should return correctly but there will be a missing 
		#  parcel in the highlight
		results = self.check_parcels(p, expected_parcels)

		# Parsing ... it happens.
		find_map = results.text.find("'map'")
		first_apos = results.text.find("'", find_map+5+1)
		second_apos = results.text.find("'", first_apos+1)
		selection_id = results.text[first_apos+1:second_apos]
		self.fetchHighlightImage(selection_id, 'ticket24')
		# TODO : Automate the viewing of this image, even though it downloads reasonably

	def test_ticket31(self):
		"""
		Testing LAYER versus GROUP versus ALL selection.
		"""
		expected_parcels = ['130270001077']
		layer_test = {
			"shape0" : "POINT(-10374958.869833 5552691.0678879)"
		}
		self.check_parcels(layer_test, expected_parcels)
		group_test = {
			"shape0" : "POINT(-10374958.869833 5552691.0678879)",
			"select0_layer" : "parcels/parcels_group"
		}
		self.check_parcels(group_test, expected_parcels)
		all_test = {
			"shape0" : "POINT(-10374958.869833 5552691.0678879)",
			"select0_layer" : "parcels/all"
		}
		self.check_parcels(all_test, expected_parcels)

	def test_ticket85(self):
		"""
		Test a two point polygon with no buffer (ticket #85)
		"""

		test_params = {
			"shape" : "POLYGON((-10375742.69258 5555129.6328634,-10375734.33228 5555115.3009206,-10375742.69258 5555129.6328634))"
		}

		params = copy(self.default_params)
		params.update(test_params)

		results = self.post(self.select_php, params=params)

		self.assertEqual(results.status_code, 200, "Service did not catch the exception!")

	def test_ticket84(self):
		"""
		Ensure point selector is not over selecting (ticket #84)
		"""

		test_params = {
			"shape0" : "POINT(-10375755.352418 5555107.2472463)"
		}
		self.check_parcels(test_params, ['130220003076'])


	def test_header_and_footer(self):
		"""
		Test header0/footer0 support in query.php using select_header/footer
		"""

		test_params = copy(self.default_params)
		test_params.update({
			"shape0" : "POINT(-10373109.338156 5552992.5910145)",
			"shape0_buffer" : "500",
		})


		res = self.post(self.select_php, params=test_params)


	def test_caching(self):
		"""
		Test a parcel query with caching
		"""

		test_params = copy(self.default_params)
		test_params['cache'] = 'true'
		res = self.post(self.select_php, params=test_params)
		#print >> sys.stderr, res.text

		# geomoose cache id's start with 'gm_'
		self.assertTrue('gm_' in res.text, 'Missing query contents!')





