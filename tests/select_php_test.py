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
		#print >> sys.stderr, 'RAW', results.text

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
			"select_layer" : "parcels/parcels",
			"query_layer" : "parcels/parcels",
			"shape_buffer" : "0",
			"selection_buffer" : "30.479983540808888", # "100ft" converted to meters
			"shape" : "POINT(-10372932.577528 5552764.4742582)"

		}

		expected_parcels = [
			#'130250001028', 
			#'130260001025', 
			'130250001050', '130260001101', '130260001201',
			'130260001150', '130260001051', '130260001275',
			'130260001175', '130350002001', '130350001002', '130350001025',
			'130360001026'
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
			"shape" : "POINT(-10374958.869833 5552691.0678879)"
		}
		self.check_parcels(layer_test, expected_parcels)
		group_test = {
			"shape" : "POINT(-10374958.869833 5552691.0678879)",
			"select_layer" : "parcels/parcels_group"
		}
		self.check_parcels(group_test, expected_parcels)
		all_test = {
			"shape" : "POINT(-10374958.869833 5552691.0678879)",
			"select_layer" : "parcels/all"
		}
		self.check_parcels(all_test, expected_parcels)

