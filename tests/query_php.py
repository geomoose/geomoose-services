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
	
	def test_in_operator(self):
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







