<?php
/*
Copyright (c) 2009-2012, Dan "Ducky" Little & GeoMOOSE.org

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

/*
 * Code contributed by Brian Fischer @ Houston Engineering
 * Updated on 10/8/2014 by theduckylittle to the V3 API.
 */

$address = $_REQUEST['address'];
$request_url = "https://maps.googleapis.com/maps/api/geocode/xml?address=".urlencode($address);

error_log('query url: '.$request_url);

$xml = simplexml_load_file($request_url) or die("url not loading");

$status = $xml->status;

error_log('GC Status '.$status);

if (strcmp($status, "OK") == 0) {
      // Successful geocode
      $resolvedaddress = $xml->result->formatted_address;

//print_r($xml);

 $coordinates = $xml->result->geometry->location;
      // Format: Longitude, Latitude, Altitude
      $lat = $coordinates->lat;
      $lng = $coordinates->lng;

 header('Content-type: application/xml');
      print "<results>";
      print "<script>";
      print "<![CDATA[";
      print "";
      echo <<<ENDOFJS
      var p = new OpenLayers.Geometry.Point({$lng},{$lat});
      OpenLayers.Projection.transform(p,new OpenLayers.Projection('WGS84'),Map.getProjectionObject());
      GeoMOOSE.zoomToPoint(p.x,p.y,100);
      var px = Map.getPixelFromLonLat(new OpenLayers.LonLat(p.x, p.y));
      GeoMOOSE.addPopup(px.x,px.y,150,50,'<b>Address Resolved To:</b> <br/> {$resolvedaddress}', 'Geocoder Result');
ENDOFJS;
      print "]]>";
      print "</script>";
      print "<html>";
      print "<![CDATA[";
      print "";
      print "<b>Address Resolved To:</b> <br/> $resolvedaddress";
      print "]]>";
      print"</html>";
      print "</results>";
} else{
  header('Content-type: application/xml');
  print "<results>";
  print "<script>";
  print "</script>";
  print "<html>";
  print "<![CDATA[";
  print "";
  print "<h3><p style='color:red'>Unable to resolve address.</p></h3>";
  print "]]>";
  print"</html>";
  print "</results>";
}

?>
