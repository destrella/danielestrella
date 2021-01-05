<?php defined('TIPO') or die();
$autoplay = false;
//Diapositivas CSS Inicio
$gc = [];
$durfxms = 32000;//duración de las animaciones en milisegundos
$retfxms = -2000;//retraso en las animaciones en milisegundos
$gc['bprevnext'] = '';
$gc['bplayprevnext'] = '';
$gc['bplaypause'] = '';
$gc['bplaypausefx'] = '';
$gc['fxin'] = '';
$gc['pointin'] = '.gslider > .cs_bullets > label:hover > .cs_point,';
$gc['bullplaypausefx'] = '';
$gc['dscvisible'] = '';
$gc['dscvisiblewrap'] = '';
$gc['dscfx'] = '';
//Diapositivas HTML Inicio
$gh = [];
$gh['ancslides'] = '';
$gh['ancplay'] = '<input name="cs_anchor1" id="cs_play1" type="radio" class="cs_anchor"'.($autoplay?' checked':'').'>';
$gh['ancpause'] = '';
$gh['imgslide'] =
'<ul>'.
'<li class="cs_skeleton">';
//Busca la imagen más alta
$másalta = ['dim'=>0, 'idx'=>false];
foreach($entrada['fotos'] as $k=>$f):
	if($f > $másalta['dim']):
		$másalta['idx'] = $k;
	endif;
endforeach;
if(!empty($entrada['fotos'][$másalta['idx']]['src'])):
	$gh['imgslide'] .= imgtag($entrada['fotos'][$másalta['idx']], $entrada);
else:
	$gh['imgslide'] .= '<img src="'.NOFOTO.'" alt="">';
endif;
$gh['imgslide'] .=
'</li>';
$gh['description'] = '<div class="cs_description">';
$gh['bplaypause'] =<<<HTML
<div class="cs_play_pause">
<label class="cs_play" for="cs_play1"><span><i></i><b></b></span></label>
HTML;
$gh['bprev'] = '<div class="cs_arrowprev">';
$gh['bnext'] = '<div class="cs_arrownext">';
$gh['bullets'] = '<div class="cs_bullets">';
foreach($entrada['fotos'] as $k=>$f):
	//Diapositivas CSS Cuerpo
	if(isset($entrada['fotos'][$k-1])):
		$sprev = $k-1;
	else:
		$sprev = array_key_last($entrada['fotos']);
	endif;
	if(isset($entrada['fotos'][$k+1])):
		$snext = $k+1;
	else:
		$snext = array_key_first($entrada['fotos']);
	endif;
	$gc['bprevnext'] .=<<<CSS
.gslider > #cs_slide1_{$k}:checked ~ .cs_arrowprev > label.num{$sprev},
.gslider > #cs_pause1_{$k}:checked ~ .cs_arrowprev > label.num{$sprev},
.gslider > #cs_slide1_{$k}:checked ~ .cs_arrownext > label.num{$snext},
.gslider > #cs_pause1_{$k}:checked ~ .cs_arrownext > label.num{$snext},
CSS;
	$gc['bplayprevnext'] .=
	'.gslider > #cs_play1:checked ~ .cs_arrowprev > label.num'.$sprev.','.
	'.gslider > #cs_play1:checked ~ .cs_arrownext > label.num'.$snext.
	'{animation:arrow1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
	$gc['bplaypause'] .= '.gslider > #cs_play1:checked ~ .cs_play_pause > .cs_pause.num'.$k.'{animation:pauseChange1 '.$durfxms.'ms infinite '.$retfxms.'ms;opacity:0;z-index:-1}';
	$gc['bplaypausefx'] .=<<<CSS
.gslider > #cs_slide1_{$k}:checked ~ ul > .slide.num{$k},
.gslider > #cs_pause1_{$k}:checked ~ ul > .slide.num{$k},
CSS;
	$gc['fxin'] .= '.gslider > #cs_play1:checked ~ ul > .slide.num'.$k.'{animation:fade-in1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
	$gc['pointin'] .=<<<CSS
.gslider > #cs_slide1_{$k}:checked ~ .cs_bullets > label.num{$k} > .cs_point,
.gslider > #cs_pause1_{$k}:checked ~ .cs_bullets > label.num{$k} > .cs_point,
CSS;
	$gc['bullplaypausefx'] .=
	'.gslider > #cs_play1:checked ~ .cs_bullets > label.num'.$k.' > .cs_point,'.
	'.gslider > #cs_pause1:checked ~ .cs_bullets > label.num'.$k.' > .cs_point'.
	'{animation: bullet1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
	$gc['dscvisible'] .=<<<CSS
.gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} > .cs_title,
.gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} > .cs_descr,
.gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} > .cs_title,
.gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} > .cs_descr,
CSS;
	$gc['dscvisiblewrap'] .=<<<CSS
.gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} .cs_wrapper,
.gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} .cs_wrapper,
CSS;
	if(!empty($f['tit'])):
		$gc['dscfx'] .=
		'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' > .cs_title'.
		'{animation: cs_title1 '.$durfxms.'ms infinite '.($retfxms+600).'ms ease}'.
		'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' .cs_title > .cs_wrapper'.
		'{animation: cs_title_text1 '.$durfxms.'ms infinite '.($retfxms+750).'ms ease}';
	endif;
	if(!empty($f['dsc'])):
		$gc['dscfx'] .=
		'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' > .cs_descr'.
		'{animation: cs_descr1 '.$durfxms.'ms infinite '.($retfxms+850).'ms ease}'.
		'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' .cs_descr > .cs_wrapper'.
		'{animation: cs_descr_text1 '.$durfxms.'ms infinite '.($retfxms+1000).'ms ease}';
	endif;
	$retfxms += 8000;
	//Diapositivas HTML Cuerpo
	$gh['ancslides'] .=<<<HTML
<input name ="cs_anchor1" id="cs_slide1_{$k}" type="radio" class="cs_anchor slide">
HTML;
	if($k==0 && !$autoplay):
		$checked = ' checked';
	else:
		$checked = '';
	endif;
	$gh['ancpause'] .=<<<HTML
<input name ="cs_anchor1" id="cs_pause1_{$k}" type="radio" class="cs_anchor pause"{$checked}>
HTML;
	$gh['imgslide'] .=
	'<li class="num'.$k.' img slide">'.
	imgtag($f, $entrada).
	'</li>';
	if(!empty($f['tit']) || !empty($f['dsc'])):
		$gh['description'] .= '<label class="num'.$k.'">';
	endif;
	if(!empty($f['tit'])):
		$gh['description'] .=<<<HTML
<span class="cs_title"><span class="cs_wrapper">{$f['tit']}</span></span>
HTML;
	endif;
	if(!empty($f['dsc'])):
		$gh['description'] .=<<<HTML
<span class="cs_descr"><span class="cs_wrapper">{$f['dsc']}</span></span>
HTML;
	endif;
	if(!empty($f['tit']) || !empty($f['dsc'])):
		$gh['description'] .= '</label>';
	endif;
	$gh['bplaypause'] .=<<<HTML
<label class="cs_pause num{$k}" for="cs_pause1_{$k}"><span><i></i><b></b></span></label>
HTML;
	$gh['bprev'] .=<<<HTML
<label class="num{$k}" for="cs_slide1_{$k}"><span><i></i><b></b></span></label>
HTML;
	$gh['bnext'] .=<<<HTML
<label class="num{$k}" for="cs_slide1_{$k}"><span><i></i><b></b></span></label>
HTML;
	$gh['bullets'] .=<<<HTML
<label class="num{$k}" for="cs_slide1_{$k}"><span class="cs_point"></span></label>
HTML;
endforeach;
//Diapositivas CSS Fin
$gc['bprevnext'] = rtrim($gc['bprevnext'],',').'{opacity:1;z-index:5}';
$gc['bplaypausefx'] .= rtrim($gc['bplaypausefx'],',').'{opacity:1;z-index:2;transform:scale(1)}';
$gc['pointin'] .= rtrim($gc['pointin'],',').'{background:#FFF}';
$gc['dscvisible'] .= rtrim($gc['dscvisible'],',').'{opacity:1;visibility:visible;transform:translateY(0)}';
$gc['dscvisiblewrap'] .= rtrim($gc['dscvisiblewrap'],',').'{opacity:1;transform:translateY(0)}';
//Diapositivas HTML Fin
$gh['imgslide'] .= '</ul>';
$gh['description'] .= '</div>';
$gh['bplaypause'] .= '</div>';
$gh['bprev'] .= '</div>';
$gh['bnext'] .= '</div>';
$gh['bullets'] .= '</div>';

$cssadicional .=
'<link rel="stylesheet" href="'.$uri.'/css/slider'.$min.'.css">'.
'<style>'.implode(PHP_EOL,$gc).'</style>';
$cuerpo .=
'<article class="'.TIPO[$entrada['tipo']].'">'.
'<div class="gslider">'.
implode(PHP_EOL,$gh).
'</div><div class="contenido">'.
'<h2>'.$entrada['título'].'</h2>';
if(!empty($entrada['resumen'])
&& '<p>'.$entrada['resumen'].'</p>' != $entrada['contenido']):
	$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
endif;
if(!empty($entrada['contenido'])):
	$cuerpo .= $entrada['contenido'];
endif;
$cuerpo .= '<footer>'.$timetag.'</footer></div></article>';
?>