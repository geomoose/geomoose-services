#
# Nosetests Testing Classes for the query.php
# script.
#

from . import GeoMOOSETest

from select_php import ParcelTest 

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


	def check_parcels(self, search_opts, expected_parcels):
		super(QueryTest, self).check_parcels(search_opts, expected_parcels, self.pin_re)

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
