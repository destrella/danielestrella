<?php defined('TIPO') or die();
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">';
if(!empty($entrada['videos'][0]['iframe'])):
	$cuerpo .= $entrada['videos'][0]['iframe'];
elseif(!empty($entrada['videos'][0]['html'][0][0]['src'])):
	$cuerpo .= '<video controls>';
	foreach($entrada['videos'][0]['html'][0] as $vid):
		$cuerpo .= '<source src="'.$vid['src'].'" type="'.$vid['mime'].'">';
	endforeach;
	$cuerpo .= '</video>';
else:
	$cuerpo .= '<img src="'.NOFOTO.'" alt="">';
endif;
$cuerpo .=
'<div class="contenido">'.
'<h2>'.$entrada['título'].'</h2>';
if($resumen && !empty($entrada['resumen'])):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;
$cuerpo .= '<footer>'.$timetag.'</footer></div></article>';
?>