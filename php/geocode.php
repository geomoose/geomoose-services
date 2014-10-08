<?php
/*
Copyright (c) 2009-2013, Dan "Ducky" Little & GeoMOOSE.org

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

# Test Addresses: 
#  * 101 Main St, Anoka, MN
#  * 25 W 4th st, saint paul, mni 55101

$address = $_REQUEST['address'];
$error_msg = "Could not find any matching geocoder results.";

header('Content-Type: application/xml');

$results = file_get_contents("http://geocoder.us/service/csv?address=".urlencode($address));
$addresses = array();
if($results) {
	if(preg_match("/[0-9]+\:/", $results )) {
		$error_msg = $results;
	} else {
		$lines = explode("\n", $results);
		foreach($lines as $line) {
			if($line) {
				$addresses[] = explode(",", $line);
			}
		}
	}
}

if(count($addresses) > 0) {
	print "<results>";
	print "<html><![CDATA[";
	print "<div><b>Search Results:</b></div>";
	print "<ul>";
	foreach($addresses as $addr) {
		$lat = $addr[0];
		$lon = $addr[1];
		$addr_str = implode(", ", array_slice($addr, 2));
		print "<li><a href='javascript:GeoMOOSE.zoomToLonLat($lon, $lat)'>$addr_str</a></li>";
	}
	print "</ul>";
	print "<p>";
	print "Geocoder results provided by <a href='http://geocoder.us' target='_blank'>Geocoder.us</a>.";
	print "</p>";
	print "]]></html>";
	print "</results>";
} else {
	print "<results>";
	print "<html>";
	print $error_msg;
	print "</html>";
	print "</results>";
}
?>
