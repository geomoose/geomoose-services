<?php
/*Copyright (c) 2009-2015, Dan "Ducky" Little & GeoMOOSE.org

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
THE SOFTWARE.*/

$DEBUG = false;
if(!$DEBUG) {
	error_reporting(E_ERROR | E_PARSE);
}

include('config.php');
include('print_util.php');

#require('fpdf/fpdf.php');
#require('fpdi/fpdi.php');
require('print_geopdf.php');



date_default_timezone_set($CONFIGURATION['timezone']);

# Turn off the warning reporting

$tempDir = $CONFIGURATION['temp'];
$uniqueId = 'print_'.time().getmypid();

$mapbook = getMapbook();

/*
 * This gets us all the basic information we need
 * to crank out a PDF
 */

#$layers = explode(':', $_REQUEST['layers']);
$mode = $_REQUEST['mode'];
$padding = $_REQUEST['padding'];
$extent = explode(',', $_REQUEST['extent']);
$units = $_REQUEST['units'];
if(!isset($units)) {
	$units = 'm';
}

$enable_geo = $_REQUEST['geo'];
if(!isset($enable_geo)) {
	$enable_geo = true;
} else {
	$enable_geo = parseBoolean($enable_geo);
}

$renderLegends = $_REQUEST['legends'];
if(isset($renderLegends)) {
	$renderLegends = parseBoolean($renderLegends);
} else { 
	$renderLegends = true;
}

$template_path = '../../conf/print/';
$template = $_REQUEST['template'];
# some sanitization...
$template = preg_replace('/\/\\\./', '', $template).'.xml';

$template_info = new DOMDocument();
$template_info->load($template_path.$template);

$quality = (float)$_REQUEST['quality'];

$preserveScale = $_REQUEST['scale'];

# strip slashes fixes an escaping problem with PHP and posting a JSON ball.
$print_info = json_decode(stripslashes($_REQUEST['layers']), true);

# Add some bounds...
if($quality <= 0) { $quality = 1; }
if($quality > 3) { $quality = 3; }

if( $mode == "feature_report") {

	$mapfile = getMapfile($mapbook, $_REQUEST['src']);
	$mapObj = ms_newMapObj($CONFIGURATION['root'].$mapfile);

	$path = explode('/', $_REQUEST['src']);
	$layer = false;
	if($path[1] == 'all') {
		$layer = $mapObj->getLayer(0); # Don't use all...
	} else {
		$layer = $mapObj->getLayerByName($path[1]);
	}
	# get the query information and set it on the layer
	$query_info = $template_info->getElementsByTagName('query')->item(0);
	$qItem = $query_info->getAttribute('item');
	$layer->set('filteritem', $qItem);
	$layer->setFilter(str_replace('%qstring%', $_REQUEST[$qItem], $query_info->getAttribute('string')));

	$shape = false;
	$layer->set('template', 'dummy.html');
	$layer->set('status', MS_DEFAULT);
	$layer->queryByRect($mapObj->extent);
	$layer->open();
	$result = $layer->getResult(0);
	$shape = $layer->getShape($result);
	$wkt = $shape->toWkt();
	$sketches_json[0]['wkt'] = $wkt;
	$sketches_json[0]['stroke'] = "yellow";
	$sketches_json[0]['opacity'] = ".8";

	$extent = array();
	$padding_percent = ((float)$shape->bounds->maxx - (float)$shape->bounds->minx) * $padding/100;
	$extent[0] = (float)$shape->bounds->minx - $padding_percent;
	$extent[1] = (float)$shape->bounds->miny - $padding_percent;
	$extent[2] = (float)$shape->bounds->maxx + $padding_percent;
	$extent[3] = (float)$shape->bounds->maxy + $padding_percent;

	$layer->close();
}
# The image width is effected by the placement on the page and the print quality
$mapImageWidth = (float)$CONFIGURATION['html_image_width'] * $quality;
$mapImageHeight = (float)$CONFIGURATION['html_image_height'] * $quality;

$image_extent = preserveScale($preserveScale, $mapImageWidth, $mapImageHeight, $extent, $quality, $units);



#$debug = array('mw' => $mapImageWidth, 'mh' => $mapImageHeight, 'ext' => $image_extent, 'layers' => $layers_json, 'sketches' => $sketches_json);
#foreach($debug as $k => $v) {
#	print $k;
#	print ':';
#	print json_encode($v);
#	print '<br/>';
#}


$printImage = renderImage($mapbook, $print_info,  $mapImageWidth, $mapImageHeight, $image_extent, $DEBUG); #, $sketches_json, $vector_json);

imagejpeg($printImage, $tempDir.$uniqueId.'.jpg');

header('Content-type: application/xml; charset='.$CONFIGURATION['output-encoding']);
print "<results><script>";

if($mode == "feature_report"){
	print '<![CDATA[window.open("php/download.php?id='.$uniqueId.'&ext=pdf");GeoMOOSE.changeTab("catalog-tab");]]>';
}

if($mode == "template_test_report"){
	print '<![CDATA[window.open("php/download.php?id='.$uniqueId.'template&ext=html");]]>';
}

print "</script>";

print "<html><![CDATA[";
	print "Print Formats";
	print "<hr/><br/>";

if($CONFIGURATION['print_image'] == 1) {
	printf('<a target="_blank" href="php/download.php?id=%s&ext=jpg">View Image</a><br/>', $uniqueId);
}

if($CONFIGURATION['print_html'] == 1) {
	$lines = file('../../conf/'.$CONFIGURATION['html_template']);
	$htmlOut = "";
	foreach($lines as $line) {
		$line = str_replace('%MAP%', sprintf('download.php?id=%s&ext=jpg', $uniqueId), $line);
		$htmlOut = $htmlOut . $line;
	}
	foreach($_REQUEST as $k => $v) {
		$htmlOut = str_replace('%'.$k.'%', utf8_decode($v), $htmlOut);
	}

	$out = fopen($tempDir.$uniqueId.'.html', 'w');
	fwrite($out, $htmlOut);
	fclose($out);

	printf('<a target="_blank" href="php/download.php?id=%s&ext=html">View HTML</a><br/>', $uniqueId);
}

if($CONFIGURATION['print_pdf'] == 1) {
	# Open the PDF Template
	$pdf = new GeoFPDI();

	# force everything to points.
	$pdf->k = 1.0;

	# load the template...

	$page_info = $template_info->getElementsByTagName('page')->item(0);

	$templateSize = array(((float)$page_info->getAttribute('w'))*72.0,((float)$page_info->getAttribute('h'))*72.0);

	$template_file = $template_info->getElementsByTagName('template')->item(0)->firstChild->nodeValue;

	$pdf->setSourceFile($template_path.$template_file);

	$pdf->SetAutoPageBreak(false);
	$pdf->addPage('P', $templateSize);

	$tplidx = $pdf->importPage(1, '/MediaBox');
	$pdf->useTemplate($tplidx, 0, 0, $templateSize[0], $templateSize[1]);

	# Set the map location
	$map_info = $template_info->getElementsByTagName('map')->item(0);
	$imageW = round(((float)$map_info->getAttribute('w'))*72.0);
	$imageH = round(((float)$map_info->getAttribute('h'))*72.0);
	$imageX = round(((float)$map_info->getAttribute('x'))*72.0);
	$imageY = round(((float)$map_info->getAttribute('y'))*72.0);

	$pdf_extent = preserveScale($preserveScale, $imageW*$quality, $imageH*$quality, $extent, $quality, $units);

	if($DEBUG) {
		error_log( "Image W: ".$imageW."<br/>");
		error_log("Image H: ".$imageH."<br/>");
		error_log("Image X: ".$imageX."<br/>");
		error_log("Image Y: ".$imageY."<br/>");
		error_log("Quality: ".$quality."<br/>");
		error_log("Extent: ".implode(',',$extent)."<br/>");
		error_log("PDF Extent: ".implode(',',$pdf_extent)."<br/>");

		error_log("<br/>");
		error_log("Ground W: ".($pdf_extent[2] - $pdf_extent[0])."<br/>");
		error_log("Ground H: ".($pdf_extent[3] - $pdf_extent[1])."<br/>");
	}

	if($enable_geo) {
		$bbox_y = $templateSize[1] - $imageH - $imageY;
		$bbox_h = $bbox_y + $imageH;
		$pdf->setMapCoordinates(array($imageX, $bbox_y, $imageX+$imageW, $bbox_h), $pdf_extent);
	}


	imagejpeg(renderImage($mapbook, $print_info,  $imageW*$quality, $imageH*$quality, $pdf_extent, $DEBUG), $tempDir.$uniqueId.'_pdf.jpg');
	$pdf->Image($tempDir.$uniqueId.'_pdf.jpg', $imageX, $imageY, $imageW, $imageH);

	# Render the text fields
	$pdf->SetTextColor(0,0,0);
	$texts = $template_info->getElementsByTagName('text');
	for($i = 0; $i < $texts->length; $i++) {
		$text = $texts->item($i);

		# Set the font size
		$pdf->SetFont('Helvetica', '', (float)$text->getAttribute('size'));

		# Process the string
		if($mode == "feature_report") {
			$printString = $text->getAttribute('content').' '.$text->getAttribute('src');
			foreach($shape->values as $k => $v) {
				$printString = str_replace('%'.$k.'%', $v, $printString);
			}
		} else {
			$printString = $text->getAttribute('content');
			foreach($_REQUEST as $k => $v) {
				//LK Check for hidden date field, if set to "true", add the date to the map
				if($k == "date" && $v == "true"){
					$v = "Printed " . date("m/d/Y") . " ";
				}
				$printString = str_replace('%'.$k.'%', utf8_decode($v), $printString);
			}
		}

		# Put the string on the form
		$pdf->SetXY(((float)$text->getAttribute('x'))*72.0, ((float)$text->getAttribute('y'))*72.0);
		$pdf->Cell(0,.25,$printString);
	}

 
	if($renderLegends) {
		# put the legends on a second page.
		$pdf->addPage('P', $templateSize);
		$pdf->SetFont('Helvetica', '', 36.0);
		$pdf->SetXY(72.0, 72.0);
		# TODO: Bad for internationalization.
		$pdf->Cell(0, .25, "Legends");
		$legend_images = getLegendImages($mapbook, $print_info);

		$pdf_legend_i = 0;
		$legend_x = 36;
		$legend_y = 100;
		$legend_w = 0;
		foreach($legend_images as $legend_image) {
			$legend_filename = $tempDir.$uniqueId.'_pdf_legend'.$pdf_legend_i.'.jpg';
			# write the file out as a jpeg
			imagejpeg($legend_image, $legend_filename);
			# get the size of the legend for placement.
			$legend_size = getimagesize($legend_filename);
			# check that the legend image is valid.
			if($legend_size[0] > 0 and $legend_size[1] > 0) {
				# place the new legend
				$pdf->Image($legend_filename, $legend_x, $legend_y, $legend_size[0], $legend_size[1]);
			
				# move the legend down the page.
				$legend_y += $legend_size[1];

				# track the widest legend image.
				$legend_w = max($legend_size[0], $legend_w);

				# when the legend's height rolls over, go back to the top,
				#  and shift over to the right.
				if($legend_y > 7*72 ) {
					$legend_y = 100;
					$legend_x += $legend_w + 10;
					# the width is only needed on a column-by-column basis.
					$legend_w = 0;
				}
			}
			$pdf_legend_i += 1;
		} # end legends loop
	} # end render legends 

	$pdf->__enddoc = $pdf->_enddoc;

	$pdf->_enddoc = function() {
		error_log("This does, in fact, get called!");
		$this->__enddoc();
	};

	$pdf->Output($tempDir.$uniqueId.'.pdf');

	printf('<a target="_blank" href="php/download.php?id=%s&ext=pdf">Download PDF</a><br/>', $uniqueId);
	if($DEBUG) {
		printf('<a target="_blank" href="php/download.php?id=%s_pdf&ext=jpg">Download PDF JPEG</a><br/>', $uniqueId);
	}


}

print "]]></html></results>";
exit(0);

# Get the extent with the scale preserved
function preserveScale($scale_setting, $width, $height, $extent, $dpi_factor, $ground_units) {
	$dpi = 72.0 * $dpi_factor;
	$minx = $extent[0];
	$miny = $extent[1];
	$maxx = $extent[2];
	$maxy = $extent[3];

	# Step Uno: Calculate the scales, choose the "densist"
	$scale = ($maxx - $minx) / $width;
	$scaleY = ($maxy - $miny) / $height;
	if($scaleY < $scale) {
		$scale = $scaleY;
	}

	# Step Zwei: Recalculate the extent
	$centerX = ($maxx + $minx) / 2.0;
	$centerY = ($maxy + $miny) / 2.0;

	$inches_per_unit = 39.3701; #meters
	if($ground_units == 'ft') {
		$inches_per_unit = 12.0;
	}

	if(isset($scale_setting) and $scale_setting != 'map') {
		$normScale = floatval($scale_setting);
		if($normScale <= 0.0) {
			$normScale = 1.0; # we'll just fix this if it's null.
		}
		if($normScale > 1.0) {
			$normScale = 1.0 / floatval($scale_setting);
		}
		$scale = 1.0 / ($normScale * $inches_per_unit * $dpi);
		# $scale = $scale * (72.6/72.0); # magic number?

	}
	$scale = $scale * .5;


	$minx = $centerX - $scale * $width;
	$maxx = $centerX + $scale * $width;
	$miny = $centerY - $scale * $height;
	$maxy = $centerY + $scale * $height;



	return array($minx, $miny, $maxx, $maxy);
}


?>
