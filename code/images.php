<?php

// parse API dump files, extract images, dump as Darwin Core media


ini_set("auto_detect_line_endings", true); // vital because some files have Windows ending

$bold_to_dc = array(

	'processid' => 'occurrenceID',
	'sampleid' => 'otherCatalogNumbers',
	'museumid' => 'catalogNumber',
	'fieldnum' => 'recordNumber',
	'bin_uri' => 'taxonID',
	'voucher_type' => 'basisOfRecord',
	'institution_storing' => 'institutionCode',
	
	
	'phylum_name'=>'phylum',
	'class_name'=>'class',
	'order_name'=>'order',
	'family_name'=>'family',
	'genus_name'=>'genus',
	'species_name'=>'scientificName',
	'identification_provided_by'=>'identifiedBy',

	'collectors'=>'recordedBy',
	'collectiondate'=>'eventDate',
	'lifestage'=>'lifestage',
	'lat'=>'decimalLatitude',
	'lon'=>'decimalLongitude',
	
	'exactsite'=>'locality',
	'province'=>'stateProvince',
	'country'=>'country',
	
	'genbank_accession' => 'associatedSequences'
);

$keys_to_export = array(
	'occurrenceID',
	'title',
	'identifier',
	'references',
	'format',
	'license'
);

// Any records that validator flags
$ignore = array(
'http://bins.boldsystems.org/index.php/Public_RecordView?processid=ARSO475-09'
);

$data_dir = dirname(dirname(__FILE__)) . '/data/api'; 

// process all files
$filenames = array();
$list = scandir($data_dir);
foreach ($list as $filename)
{
	if (preg_match('/\.tsv$/', $filename))
	{
		$filenames[] = $filename;
	}
}

// process one file
//$filenames=array('api-iBOL_phase_0.50_COI.tsv');

// header row
echo join("\t", $keys_to_export) . "\n";

foreach ($filenames as $filename)
{
	$keys = array();
	
	$row_count = 0;	
		
	$filename = $data_dir . '/' . $filename;
	
	$file = @fopen($filename, "r") or die("couldn't open $filename");
	
	$file_handle = fopen($filename, "r");
	while (!feof($file_handle)) 
	{
		$line = trim(fgets($file_handle));
		
		$row = explode("\t", $line);
		
		if ($row_count == 0)
		{
			$keys = $row;
		}
		else
		{
			//print_r($row);
			
			$obj = new stdclass;
			
			$n = count($row);
			for ($i = 0; $i < $n; $i++)
			{
				if (trim($row[$i]) != '')
				{
					switch ($keys[$i])
					{
						case 'copyright_licenses':
							break;
						case 'image_urls':
							$image_urls 		= explode("|", $row[$i]);
							$copyright_licenses = explode("|", $row[$i+1]);
			
							$n = count($image_urls);
							for ($j =0; $j < $n; $j++)
							{
								$media = new stdclass;
				
								// $media->occurrenceID = $id;
								$media->occurrenceID = $obj->occurrenceID;
								$media->title = $obj->occurrenceID;
								
								// make URL
								$media->occurrenceID = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $media->occurrenceID;
											
								$media->identifier = $image_urls[$j];
								// some URLs have # symbol (why?)
								$media->identifier = str_replace('#', '%23', $media->identifier);
								// encode '+' otherwise GBIF breaks
								$media->identifier = str_replace('+', '%2B', $media->identifier);
				
								// URL of barcode page 
								$media->references =  'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $obj->occurrenceID;
				
								$media->format = '';
								if (preg_match('/\.(?<extension>[a-z]{3,4})$/i', $image_urls[$j], $m))
								{
									switch (strtolower($m['extension']))
									{
										case 'gif':
											$media->format = 'image/gif';
											break;
										case 'jpg':
										case 'jpeg':
											$media->format = 'image/jpeg';
											break;
										case 'png':
											$media->format = 'image/png';
											break;
										case 'tif':
										case 'tiff':
											$media->format = 'image/tiff';
											break;
										default:
											break;
									}
								}
								$media->license = $copyright_licenses[$j]; //  dcterms.license
				
								// Convert to URL if possible
								switch ($media->license)
								{
									case 'CreativeCommons - Attribution':
										$media->license = 'https://creativecommons.org/licenses/by/3.0/';
										break;
				
									case 'CreativeCommons - Attribution Share-Alike':
										$media->license = 'https://creativecommons.org/licenses/by-sa/3.0/';
										break;
				
									case 'CreativeCommons by-nc-sa':
									case 'CreativeCommons - Attribution Non-Commercial Share-Alike':
									case 'CreativeCommons ñ Attribution Non-Commercial Share-Alike':
									case 'CreativeCommons-Attribution Non-Commercial Share-Alike':
										$media->license = 	'https://creativecommons.org/licenses/by-nc-sa/3.0/';
										break;
				
									case 'CreativeCommons - Attribution by Laurence Packer':
										$media->license = 'https://creativecommons.org/licenses/by/3.0/';
										break;
				
									case 'CreativeCommons - Attribution Non-Commercial No Derivatives':
										$media->license = 'https://creativecommons.org/licenses/by-nc/3.0/';
										break;
				
									case 'CreativeCommons - Attribution Non-Commercial':
										$media->license = 'https://creativecommons.org/licenses/by-nc/3.0/';
										break;
				
									case 'No Rights Reserved':
									case 'No Rights Reserved (nrr)':
										$media->license = 	'http://creativecommons.org/publicdomain/mark/1.0/';
										break;
				
									case 'CreativeCommons': // ?
									default:
										break;
								}	
								
								// store
								
								$obj->associatedMedia[] = $media;
							}
								
													
							break;
							
						default:
							if (isset($bold_to_dc[$keys[$i]]))
							{
								$obj->{$bold_to_dc[$keys[$i]]} = $row[$i];
							}
							break;
					}
					
				}
			}
			//print_r($obj);
			
			if (isset($obj->associatedMedia))
			{
				foreach ($obj->associatedMedia as $media)
				{
					if (!in_array($media->occurrenceID, $ignore))
					{
				
						$row_to_export = array();
			
						foreach ($keys_to_export as $k)
						{
							if (isset($media->{$k}))
							{
								$row_to_export[] = $media->{$k};
							}
							else
							{
								$row_to_export[] = '';
							}
						}
			
						echo join("\t", $row_to_export) . "\n";
					}
				}
			}
			

						
			
		}
		
		$row_count++;
		
		/*
		if ($row_count > 10) 
		{
			break;
		}
		*/
	
	}

	
}

?>
