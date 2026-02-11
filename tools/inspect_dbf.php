<?php
/**
 * DBF File Inspector — saves results to dbf_result.txt
 */
$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
$outPath = __DIR__ . '/dbf_result.txt';

if (!file_exists($dbfPath)) { die("File not found\n"); }

$fh = fopen($dbfPath, 'rb');
$headerData = fread($fh, 32);
$header = unpack('Cversion/CyearMod/CmonthMod/CdayMod/VnumRecords/vheaderSize/vrecordSize', $headerData);

$out = "=== DBF Info ===\n";
$out .= "Records: {$header['numRecords']}\n";
$out .= "Header: {$header['headerSize']} bytes | Record: {$header['recordSize']} bytes\n\n";

// Fields
$fields = [];
$out .= str_pad("#", 4) . str_pad("Name", 15) . str_pad("Type", 6) . str_pad("Len", 6) . str_pad("Dec", 6) . "\n";
$out .= str_repeat("-", 37) . "\n";
$n = 0;
while (true) {
    $fd = fread($fh, 32);
    if (!$fd || strlen($fd) < 32 || ord($fd[0]) === 0x0D) break;
    $f = unpack('A11name/Atype/x4/Clength/Cdecimal', $fd);
    $f['name'] = trim($f['name']);
    $fields[] = $f;
    $n++;
    $out .= str_pad($n, 4) . str_pad($f['name'], 15) . str_pad($f['type'], 6) . str_pad($f['length'], 6) . str_pad($f['decimal'], 6) . "\n";
}
$out .= "\nTotal: $n fields\n";

// Sample 3 records
fseek($fh, $header['headerSize']);
$out .= "\n=== Sample (3 records) ===\n";
for ($i = 0; $i < min(3, $header['numRecords']); $i++) {
    fread($fh, 1); // deleted flag
    $out .= "\n--- Record " . ($i+1) . " ---\n";
    foreach ($fields as $f) {
        $v = trim(fread($fh, $f['length']));
        if ($v !== '') $out .= "  {$f['name']}: $v\n";
    }
}
fclose($fh);

file_put_contents($outPath, $out);
echo "Saved to $outPath\n";
