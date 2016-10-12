<?php
/*Copyright (c) 2009-2016, Dan "Ducky" Little & GeoMOOSE.org

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

require('fpdf/fpdf.php');
require('fpdi/fpdi.php');


class GeoFPDI extends FPDI {
	## Toggle as to whether the geo functions will be enabled.
	private $enable_geo = false;

	function setMapCoordinates($pdfExt, $geoExt) {
		# both of these are assumed to be minx, miny, maxx, maxy
		$this->pdf_ext = $pdfExt; 
		$this->geo_ext = $geoExt; 

		$this->enable_geo = true;
	}

	function _isostyle() {
		$bbox = implode(" ", $this->pdf_ext);
		$this->_newobj();
		$this->_out("<< /BBox [ ".$bbox." ] /Measure ".($this->n+1)." 0 R /Name (Layer) /Type /Viewport >>");
		$this->_out("endobj");

		#$minx = $this->geo_ext[0];
		#$maxx = $this->geo_ext[2];
		#$miny = $this->geo_ext[1];
		#$maxy = $this->geo_ext[3];

		$minx = $this->geo_ext[0];
		$maxx = $this->geo_ext[2];
		$maxy = $this->geo_ext[1];
		$miny = $this->geo_ext[3];

		#$bounds = implode(" ", array($maxx, $miny, $minx, $miny, $minx, $maxy, $maxx, $maxy));
		$bounds = implode(" ", array($minx, $miny, $maxx, $miny, $maxx, $maxy, $minx, $maxy));
		$bounds = implode(" ", array($miny, $minx, $maxy, $minx, $maxy, $maxx, $miny, $maxx));


		$this->_newobj();
		$this->_out("<< /Bounds [ 0 1 0 0 1 0 1 1 ] /GCS ".($this->n+1)." 0 R /GPTS [ ".$bounds." ] /LPTS [ 0 1 0 0 1 0 1 1 ] /Subtype /GEO /Type /Measure >>");
		$this->_out("endobj");


		$this->_newobj();
		$this->_out('<< /EPSG 3857 /Type /PROJCS /WKT (PROJCS["WGS_84_Pseudo_Mercator",GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]],PROJECTION["Mercator"],PARAMETER["central_meridian",0],PARAMETER["false_easting",0],PARAMETER["false_northing",0],UNIT["Meter",1],PARAMETER["standard_parallel_1",0.0]]) >>');
		$this->_out("endobj");

		$this->_newobj();
		$this->_out('<< /Name (User Generated Map) /Type /OCG >>');
		$this->_out("endobj");
	}

	# overridden to provide the id of the new object.
	function _newobj($obj_id=false,$onlynewobj=false) {
		parent::_newobj($obj_id, $onlynewobj);
		return $this->_current_obj_id;
	}

	# overridden to add "/VP" to the Page contents.
	function _putpages() {
		$nb=$this->page;
		if(!empty($this->AliasNbPages))
		{
			//Replace number of pages
			for($n=1;$n<=$nb;$n++)
				$this->pages[$n]=str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
		}
		if($this->DefOrientation=='P')
		{
			$wPt=$this->DefPageFormat[0]*$this->k;
			$hPt=$this->DefPageFormat[1]*$this->k;
		}
		else
		{
			$wPt=$this->DefPageFormat[1]*$this->k;
			$hPt=$this->DefPageFormat[0]*$this->k;
		}
		$filter=($this->compress) ? '/Filter /FlateDecode ' : '';
		for($n=1;$n<=$nb;$n++)
		{
			//Page
			$this->_newobj();
			$this->_out('<</Type /Page');
			$this->_out('/Parent 1 0 R');
			if(isset($this->PageSizes[$n]))
				$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageSizes[$n][0],$this->PageSizes[$n][1]));
			$this->_out('/Resources 2 0 R');
			if(isset($this->PageLinks[$n]))
			{
				//Links
				$annots='/Annots [';
				foreach($this->PageLinks[$n] as $pl)
				{
					$rect=sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
					$annots.='<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
					if(is_string($pl[4]))
						$annots.='/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
					else
					{
						$l=$this->links[$pl[4]];
						$h=isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
						$annots.=sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',1+2*$l[0],$h-$l[1]*$this->k);
					}
				}
				$this->_out($annots.']');
			}
			$this->_out('/VP ['.($this->n+2).' 0 R]');
			$this->_out('/Contents '.($this->n+1).' 0 R>>');
			$this->_out('endobj');
			//Page content
			$p=($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
			$this->_newobj();
			$this->_out('<<'.$filter.'/Length '.strlen($p).'>>');
			$this->_putstream($p);
			$this->_out('endobj');
		}
		//Pages root
		$this->offsets[1]=strlen($this->buffer);
		$this->_out('1 0 obj');
		$this->_out('<</Type /Pages');
		$kids='/Kids [';
		for($i=0;$i<$nb;$i++)
			$kids.=(3+2*$i).' 0 R ';
		$this->_out($kids.']');
		$this->_out('/Count '.$nb);
		$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
		$this->_out('>>');
		$this->_out('endobj');
	}



	function _enddoc() {
		$this->_putheader();
		$this->_putpages();
		# add ISO style Geospatial PDF commands.
		if($this->enable_geo) {
			$this->_isostyle();
		}

		$this->_putresources();
		//Info
		$this->_newobj();
		$this->_out('<<');
		$this->_putinfo();
		$this->_out('>>');
		$this->_out('endobj');
		//Catalog
		$this->_newobj();
		$this->_out('<<');
		$this->_putcatalog();
		$this->_out('>>');
		$this->_out('endobj');
		//Cross-ref
		$o=strlen($this->buffer);
		$this->_out('xref');
		$this->_out('0 '.($this->n+1));
		$this->_out('0000000000 65535 f ');
		for($i=1;$i<=$this->n;$i++)
			$this->_out(sprintf('%010d 00000 n ',$this->offsets[$i]));
		//Trailer
		$this->_out('trailer');
		$this->_out('<<');
		$this->_puttrailer();
		$this->_out('>>');
		$this->_out('startxref');
		$this->_out($o);
		$this->_out('%%EOF');
		$this->state=3;
		$this->_closeParsers();
	}
}



?>
