<?php

require('render.php');

$name = 'index.html';
$data = array('name' => 'Goran', 'action' => 'Hello Index');

$render = new fw_ViewRenderer('tmpl', $name, $data);
$output = $render->render();

echo $output->get_data();

?>
