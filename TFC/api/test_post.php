<?php
$payload = json_encode(['uid'=>'515c9c1e']);
$ctx = stream_context_create([
  'http'=>[
    'method'=>'POST',
    'header'=>"Content-Type: application/json\r\nHost: proyecto.intranet.local\r\n",
    'content'=>$payload
]]);
echo file_get_contents('http://proyecto.intranet.local/api/request_token.php', false, $ctx);

