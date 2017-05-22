<?php

$xmlReader = new \XMLReader();

$xmlReader->open('http://www.cbr.ru/mcirabis/PluginInterface/GetBicCatalog.aspx?type=db');
$xmlReader->open('biclistDB.xml');

$path = 'http://www.cbr.ru';

while ($xmlReader->read()) {
    if($xmlReader->nodeType == XMLReader::ELEMENT) {
        if ($xmlReader->name == 'BicDBList') {
            $path .= $xmlReader->getAttribute('Base');
        } elseif ($xmlReader->name == 'item') {
            $path .= $xmlReader->getAttribute('file');
            break;
        }
    }
}

$xmlReader->close();

@mkdir('/tmp/bik-dic/', 0777, true);

$fp = fopen ('/tmp/bik-dic/tmp.zip', 'w+');
//Here is the file we are downloading, replace spaces with %20
$ch = curl_init(str_replace(" ","%20", $path));
curl_setopt($ch, CURLOPT_TIMEOUT, 50);
// write curl response to file
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// get curl response
curl_exec($ch);
curl_close($ch);
fclose($fp);

$zip = new \ZipArchive();

$res = $zip->open('/tmp/bik-dic/tmp.zip');

if ($res === true) {
    $zip->extractTo('/tmp/bik-dic/', 'bnkseek.dbf');
    $zip->close();
} else {
    echo 'failed, code:' . $res;
    exit(-1);
}

$db = \dbase_open('/tmp/bik-dic/bnkseek.dbf', 0);
if (!$db) {
    echo "fail";
    exit(-1);
}

$dataRecordsCount = \dbase_numrecords($db);


$banks = [];

for ($i = 1; $i < $dataRecordsCount; ++$i) {
    $bankInfo = \dbase_get_record_with_names($db, $i);

    $banks[] = [
        'bik' => $bankInfo["NEWNUM"],
        'real' => iconv('CP866', 'utf-8', $bankInfo["REAL"]),
        'okpo' => $bankInfo["OKPO"],
        'full_name' => iconv('CP866', 'utf-8', $bankInfo["NAMEP"]),
        'short_name' => iconv('CP866', 'utf-8', $bankInfo["NAMEN"]),
        'ks' => $bankInfo["KSNP"],
        'city' => iconv('CP866', 'utf-8', $bankInfo["NNP"]),
        'zip' => (int)$bankInfo["IND"],
        'address' => iconv('CP866', 'utf-8', $bankInfo["ADR"]),
        'tel' => iconv('CP866', 'utf-8', $bankInfo["TELEF"])
    ];
}

header('Content-Type: application/json');
echo json_encode($banks);
