#
# Nosetests Testing Classes for the query.php
# script.
#

from . import GeoMOOSETest

from select_php_test import ParcelTest 

import re

class QueryTest(ParcelTest):
	def setUp(self):
		super(QueryTest,self).setUp()
		# now override with our parameters
		self.select_php = "http://" + self.host + self.geomoose_base + "/php/query.php"
		self.default_params = {
			'comparitor0' : 'eq-str',
			'fieldname0' : 'PIN',
			'layer0' : 'parcels/parcels',
			'mode' : 'search',
			'template0' : 'itemquery'
		}

		self.pin_re = '(data-pin=")([0-9]+)"'


	def check_parcels(self, search_opts, expected_parcels, regex=None):
		if(regex is None):
			regex = self.pin_re
		return super(QueryTest, self).check_parcels(search_opts, expected_parcels, regex)

	#
	# TODO: Test more operators and combinations and standards.
	#

	def test_single_parcel(self):
		"""
		Test a single parcel search
		"""
		expected_parcels = ['130270001077']
		query_test = {
			"value0" : expected_parcels[0]
		}
		self.check_parcels(query_test, expected_parcels)
	
	def test_in_comparitor(self):
		"""
		Test an 'in' search 
		"""
		expected_parcels = ['160010001001', '130270001077']
		in_test = {
			"comparitor0" : "in",
			"value0" : ';'.join(expected_parcels)
		}
		self.check_parcels(in_test, expected_parcels)


	def test_in_with_spaces(self):
		"""
		Test an 'in' search with uneven spaces (ticket #35)
		"""
		expected_parcels = ['160010001001', '130270001077']
		search_parcels = [expected_parcels[0]+'   ',] + expected_parcels[1:]
		in_test = {
			"comparitor0" : "in",
			"value0" : ';'.join(search_parcels)
		}
		self.check_parcels(in_test, expected_parcels)

	def test_in_with_names(self):
		"""
		Test an 'in' search using names instead of PINs
		"""
		expected_parcels = ['160010001001', '130270001077']
		names = ['Kevin Smith', 'Theo Allen']
		in_test = {
			"comparitor0" : "in",
			"fieldname0" : "OWNER_NAME",
			"value0" : ';'.join(names)
		}
		self.check_parcels(in_test, expected_parcels)
		

	def test_uppercase_in(self):
		"""
		Test an 'in-ucase' search using PINs and CITY (ticket #35) 
		"""
		# The problem with this test is that there are far too
		# many pins when we use 'CITY' as a search field
		# and the names are case sensitive. So this also requires
		# the 'and' operator to work.
		expected_parcels = ['160010001001', '130270001077']
		cities = ['EUREKA TWP', 'GREENVALE TWP']
		ucase_test = {
			"comparitor0" : "in",
			"value0" : ';'.join(expected_parcels),
			"operator1" : "and",
			"fieldname1" : "CITY",
			"comparitor1" : "in",
			"value1" : ';'.join(cities)
		}

		# should return as normal.
		self.check_parcels(ucase_test, expected_parcels)
		# under case our cities
		ucase_test['value1']  = ucase_test['value1'].lower()
		# we should get no parcels
		self.check_parcels(ucase_test, [])

		# now we should get our parcels back.
		ucase_test['comparitor1'] = 'in-ucase'
		self.check_parcels(ucase_test, expected_parcels)

	def test_like_all(self):
		"""
		Test the 'like-all' search operator (ticket #35)
		"""

		# This test has the same problems as the 
		# uppercase-in test.

		expected_parcels = ['130270001077']
		names_to_try = [
			'theo allen',
			'Allen Theo'
		]
		likeall_test = {
			"comparitor0" : "like-all",
			"fieldname0" : "OWNER_NAME",
		}

		# should return as normal.
		for name in names_to_try:
			likeall_test['value0'] = name
			self.check_parcels(likeall_test, expected_parcels)
	
	def test_like_any_multifield(self):
		"""
		Test the 'like-any' search operator across multiple fields (ticket #35)
		"""
		kevin_parcel = ['160010001001']
		village_parcels = ['160300001150', '160300002075', '160300001076', '160300001002', '160300001251', '160300001025', '160300001101', '160300003051', '160300001201', '160300001052', '160300002051', '160300001175']
		names_to_try = [
			('kevin', kevin_parcel),
			('Kevin', kevin_parcel),
			('duck village kevin', kevin_parcel+village_parcels)
		]

		like_any_test = {
			"comparitor0" : "like-any",
			"fieldname0" : "OWNER_NAME,CITY",
		}

		# should return as normal.
		for name in names_to_try:
			like_any_test['value0'] = name[0]
			self.check_parcels(like_any_test, name[1])

	
	def test_utf8(self):
		"""
		Search for an umlaut, an enye, and a cedilla
		
		This test will FAIL! It will fail because mapserver
		is not returning the international characters in the template
		and I don't want to write the exceptions to make it look like
		it is passing.
		"""
		search_words = [
			u'M\xe4dchen', # german
			u'Girl', # english
			u'Gar\xe7on', # french / portuguese
			u'Ni\xf1a', # spanish
		]

		utf8_test = {
			'layer0' : 'international/testing',
			'fieldname0' : 'name',
			'comparitor0' : 'eq-str'
		}

		#pattern: id;name!
		test_regex = '([0-9]+)\;(\w+)\!'
		for name in search_words:
			utf8_test['value0'] = name
			# check parcels is a bit misleading here because our
			# test template returns things formatted nice we just
			# abuse the regex
			self.check_parcels(utf8_test, [name,], test_regex)


	def test_null_case(self):
		"""
		Check that XML is returned when no results are found. (Ticket #35)
		"""

		like_any_test = {
			"comparitor0" : "like-any",
			"fieldname0" : "OWNER_NAME,CITY",
			"value0" : "AintNoNullCaseLikeAGeoMOOSENullCase"
		}
		# these are errors, they are bad.
		regex = 'mapObj..queryByRect'
		r = self.check_parcels(like_any_test, [], regex)

	def test_bad_zoom_coordinates(self):
		"""
		Check that zoom coordinates are valid from searches. (Ticket #90)
		"""

		# this test does not check for parcel validity, 
		#  it needs to parse zoom coordinates, so it does not use
		#  "check_parcels", hence a manually setup test.
		query_php = "http://" + self.host + self.geomoose_base + "/php/query.php"

		params = {
			'highlight' : 'true',
			'mode' : 'search',
			'layer0' : 'parcels/parcels',
			'template0' : 'itemquery',
			'fieldname0' : 'PIN',
			'comparitor0' : 'like-icase',
			'value0' : '130220003076',
			'fieldname1' : 'FIN_SQ_FT',
			'operator1' : 'or',
			'comparitor1' : 'gt',
			'value1' : ''
		}


		req = self.post(query_php, params=params) 

		#GeoMOOSE.zoomToExtent(483512.041622,4935870.833973,483612.169900,4936007.072803)
		self.assertEqual(req.status_code, 200, "Zoom test returned invalid status code")
		pattern = r".*GeoMOOSE\.zoomToExtent\((?P<minx>[\-0-9\.]+),(?P<miny>[\-0-9\.]+),(?P<maxx>[\-0-9\.]+),(?P<maxy>[\-0-9\.]+), .EPSG.(?P<proj>[0-9]+).\).*"

		zoom_match = re.match(pattern, req.text.replace('\n', ' '))

		self.assertIsNotNone(zoom_match, "Could not find zoomToExtent string in search results.")

		coordinates = zoom_match.groupdict()
		correct = {
			'minx': u'-93.207671', 'miny': u'44.575991', 
			'maxx': u'-93.206415', 'maxy': u'44.577215', 
			'proj': u'4326'
		}

		# verify the results actually pass.
		for coord in correct:
			self.assertEqual(coordinates[coord], correct[coord],
				"Error in coordinates! %s does not match! (%s != %s)" % (coord, coordinates[coord], correct[coord]))


	

