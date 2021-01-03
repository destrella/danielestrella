<?php defined('TIPO') or die();

$cuerpo .=
'<div class="gal">'.
'<input type="radio" id="fot0" class="nada" name="galería">';
$lasfotos = array_keys($entrada['fotos']);
foreach($entrada['fotos'] as $k => $f):
	$etipo = $entrada['tipo'];
	$fotant = $fotsig = '';
	if(isset($lasfotos[array_search($k, $lasfotos)-1])):
		$fantid = $lasfotos[array_search($k, $lasfotos)-1];
		$fotant = '<label for="fot'.($k).'" class="modal ant">'.
		'&#62298;'.
		'</label>';
	endif;
	if(isset($lasfotos[array_search($k, $lasfotos)+1])):
		$fsigid = $lasfotos[array_search($k, $lasfotos)+1];
		$fotsig = '<label for="fot'.($k+2).'" class="modal sig">'.
		'&#62299;'.
		'</label>';
	endif;
	$cuerpo .=
	'<label for="fot'.($k+1).'" tabindex="'.$k.'" class="min">'.
	imgtag($f, $entrada).
	'</label>';
	$entrada['tipo'] = 'fs';
	$cuerpo .=
	'<input type="radio" id="fot'.($k+1).'" name="galería" class="selector">'.
	'<div class="visor">'.
	imgtag($f, $entrada).$fotant.$fotsig.
	'<label for="fot0" class="cerrar">&#62216;</label>'.
	'</div>';
	$entrada['tipo'] = $etipo;
endforeach;

$cuerpo .= '</div>';
?>