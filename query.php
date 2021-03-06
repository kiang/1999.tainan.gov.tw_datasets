<?php

$endDate = mktime();
$lastResultCount = 1;
$startDate = strtotime('-4 days');

$basePath = __DIR__ . "/requests";

if (false === getXml($startDate, $endDate)) {
    while ($endDate > $startDate) {
        $newStart = $endDate - 7200;
        getXml($newStart, $endDate);
        $endDate = $newStart;
    }
}

function getXml($startDate, $endDate) {
    global $basePath;
    $xmlWriter = new XMLWriter();
    $xmlWriter->openMemory();
    $xmlWriter->startDocument('1.0', 'UTF-8');
    $xmlWriter->startElement('root');

    $xmlWriter->startElement('city_id');
    $xmlWriter->writeCData('tainan.gov.tw');
    $xmlWriter->endElement();

    $xmlWriter->startElement('start_date');
    $xmlWriter->writeCData(date('Y-m-d H:i:s', $startDate));
    $xmlWriter->endElement();

    $xmlWriter->startElement('end_date');
    $xmlWriter->writeCData(date('Y-m-d H:i:s', $endDate));
    $xmlWriter->endElement();

    $xmlWriter->endElement();

    $url = 'http://open1999.tainan.gov.tw:82/ServiceRequestsQuery.aspx';
    $xml = $xmlWriter->flush(true);
    $options = array(
        'http' => array(
            'header' => "Content-type: text/xml\r\n",
            'method' => 'POST',
            'content' => $xml,
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if (false !== strpos($result, '<returncode>0</returncode>')) {
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        $lastResultCount = (int) $xml->count;

        echo 'lastEndDate: ' . date('Y-m-d H:i:s', $endDate) . ', got ' . $lastResultCount . " records\n";

        if ($lastResultCount > 0) {
            foreach ($xml->records->record AS $record) {
                $timeStamp = strtotime($record->requested_datetime);
                if ($timeStamp < $endDate) {
                    $endDate = $timeStamp - 1;
                }
                $d = date('Y/m/d', $timeStamp);
                $requestPath = "{$basePath}/{$d}";
                if (!file_exists($requestPath)) {
                    mkdir($requestPath, 0777, true);
                }
                if (!empty($record->Pictures)) {
                    $picCount = 0;
                    foreach ($record->Pictures AS $pic) {
                        ++$picCount;
                        file_put_contents("{$requestPath}/{$record->service_request_id}-{$picCount}.jpg", base64_decode((string) $pic->Picture->file));
                    }
                    unset($record->Pictures);
                }
                file_put_contents("{$requestPath}/{$record->service_request_id}.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    } else {
        return false;
    }
}
