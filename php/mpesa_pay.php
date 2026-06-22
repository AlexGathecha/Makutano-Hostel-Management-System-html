<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

define('MPESA_CONSUMER_KEY',    'y6U5Am1Z2BKT1rDZSGN2ArduDSMUj7cgS5pLxwvQSZkTWxhu');   
define('MPESA_CONSUMER_SECRET', 'GGbbXzHTTh77ntlxGuA0X0X8Z5oXku8oCtcJPuwRQtAeS8Pr9vRX8IF5LaZ24Uz1');   
define('MPESA_SHORTCODE',       '174379');    
define('MPESA_PASSKEY',         'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); 
define('MPESA_CALLBACK_URL',    'https://quarters-handbag-geiger.ngrok-free.dev/makutano/php/mpesa_callback.php');
define('MPESA_ENV',             'live');
