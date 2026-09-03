<?php
require_once '../config/config.php';
header('Content-Type: application/json');
$in=$_GET['check_in']??'';$out=$_GET['check_out']??'';$guests=max(1,(int)($_GET['guests']??1));
if(!$in||!$out||$in>=$out){http_response_code(422);echo json_encode(['error'=>'Invalid dates']);exit;}
$s=db()->prepare("SELECT r.id,r.room_number,r.price,rt.name type_name,rt.capacity FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.status='available' AND rt.capacity>=?");$s->execute([$guests]);
$outRooms=[];foreach($s as $r){if(is_room_available((int)$r['id'],$in,$out))$outRooms[]=$r;}echo json_encode(['rooms'=>$outRooms]);
