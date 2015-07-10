##
# Testing suite for fido.php
# 

import requests

import re
import sys
from xml.dom import minidom

from copy import copy

from . import GeoMOOSETest

class FidoTest(GeoMOOSETest):
	def setUp(self):
		self.fido_php = "http://" + self.host + self.geomoose_base + "/php/fido.php"
		super(FidoTest,self).setUp()

	def test_fido(self):
		"""
		Basic Fido test, ensures demo data is returned.
		"""
		results = self.get(self.fido_php)
