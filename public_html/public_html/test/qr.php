<?php
require_once 'db_connect.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if (empty($code)) {
    die('No code provided');
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

$result = Builder::create()
    ->writer(new PngWriter())
    ->data($code)
    ->encoding(new Encoding('UTF-8'))
    ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
    ->size(150)
    ->margin(5)
    ->build();

header('Content-Type: ' . $result->getMimeType());
echo $result->getString();