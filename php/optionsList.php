<?php
$sample_values = array();
$sample_values[] = 'Red';
$sample_values[] = 'Orange';
$sample_values[] = 'Yellow';
$sample_values[] = 'Green';
$sample_values[] = 'Blue';
$sample_values[] = 'Indigo';
$sample_values[] = 'Violet';

header('Content-type: application/xml');

print "<options>";
for($i = 0; $i < sizeof($sample_values); $i++) {
	print "<option value='$i'>$sample_values[$i]</option>";
}
print "</options>";
?>
