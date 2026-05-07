<?php
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "status" => "OK",
    "message" => "PHP funciona correctamente",
    "php_version" => phpversion(),
    "tiempo" => date("Y-m-d H:i:s")
]);
?>
