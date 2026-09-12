<?php

function extract_facturx_from_pdf(string $pdfPath): ?string
{
	$pdf = file_get_contents($pdfPath);
	if ($pdf === false) {
		return null;
	}

	// 1. Construire la map des objets
	if (!preg_match_all('/(\d+)\s+0\s+obj\b([\s\S]*?)endobj\b/m', $pdf, $matches, PREG_SET_ORDER)) {
		return null;
	}

	$objMap = [];
	foreach ($matches as $m) {
		$objNum  = (int)$m[1];
		$objBody = $m[2];
		$objMap[$objNum] = $objBody;
	}

	// 2. Trouver l'objet Filespec (celui qui contient /Type /Filespec)
	$filespecObjNum = null;
	foreach ($objMap as $num => $obj) {
		if (strpos($obj, '/Type /Filespec') !== false) {
			$filespecObjNum = $num;
			break;
		}
	}

	if ($filespecObjNum === null) {
		return null;
	}

	$filespecObj = $objMap[$filespecObjNum];

	// 3. Trouver l'objet EmbeddedFile référencé dans /EF << /F X 0 R >>
	if (!preg_match('/\/EF\s*<<[\s\S]*?\/F\s+(\d+)\s+0\s+R/', $filespecObj, $m)) {
		return null;
	}

	$xmlObjNum = (int)$m[1];

	if (!isset($objMap[$xmlObjNum])) {
		return null;
	}

	$xmlObj = $objMap[$xmlObjNum];

	// 4. Extraire le stream
	if (!preg_match('/stream[\r\n]+([\s\S]*?)endstream/', $xmlObj, $streamMatch)) {
		return null;
	}

	$raw = $streamMatch[1];

	// 5. Décompression FlateDecode
	$xml = @gzuncompress($raw);
	if ($xml === false) {
		$xml = @gzinflate($raw);
	}

	return $xml ?: null;
}

// Test CLI
if (PHP_SAPI === 'cli' && isset($argv[1])) {
	var_dump(extract_facturx_from_pdf($argv[1]));
}

?>
